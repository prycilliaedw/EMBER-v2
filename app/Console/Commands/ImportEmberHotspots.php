<?php

namespace App\Console\Commands;

use App\Services\FireSusceptibilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ImportEmberHotspots extends Command
{
    protected $signature = 'ember:import-hotspots
        {path : Path to the enriched EMBER hotspot CSV}
        {--dry-run : Validate and report without writing records}
        {--chunk=500 : Insert batch size (maximum 1000)}';

    protected $description = 'Idempotently import NASA/MapBiomas-enriched EMBER hotspots and calculate FSI from approved class-level evidence.';

    private const REQUIRED_COLUMNS = [
        'LATITUDE', 'LONGITUDE', 'ACQ_DATE', 'ACQ_TIME', 'SATELLITE', 'INSTRUMENT',
        'CONFIDENCE', 'DAYNIGHT', 'LEVEL_3', 'LEVEL_4', 'LEVEL_5', 'LEVEL_6',
        'MAPBIOMAS_ID', 'LAND_COVER_ID',
    ];

    public function handle(FireSusceptibilityService $susceptibility): int
    {
        $path = $this->argument('path');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('CSV is missing or unreadable: '.$path);
            return self::FAILURE;
        }

        try {
            $analysis = $this->analyzeCsv($path, $susceptibility);
            $existing = $this->findExistingIdentities(
                $analysis['candidate_identities'],
                $analysis['min_date'],
                $analysis['max_date'],
            );
            $summary = $this->summary($analysis, $existing);
            $dryRun = (bool) $this->option('dry-run');
            $this->printSummary($summary, $analysis['invalid_examples'], $dryRun);

            if ($analysis['source_rows'] === 0) {
                $this->error('No source records were found. No rows were written.');
                return self::FAILURE;
            }

            if ($analysis['invalid_rows'] > 0) {
                $this->error('Import aborted because invalid rows were found. No rows were written.');
                return self::FAILURE;
            }

            if ($dryRun) {
                return self::SUCCESS;
            }

            $this->assertUniqueExternalIdIndex();
            $chunkSize = max(1, min(1000, (int) $this->option('chunk')));
            $result = DB::transaction(
                fn () => $this->insertFromCsv($path, $analysis, $susceptibility, $chunkSize),
                3,
            );

            $this->line('Records inserted: '.$result['inserted']);
            $this->line('Existing matching records: '.$result['existing']);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }

    /** @return array<string,mixed> */
    private function analyzeCsv(string $path, FireSusceptibilityService $susceptibility): array
    {
        $sourceRows = 0;
        $validRows = 0;
        $invalidRows = 0;
        $duplicateRows = 0;
        $candidateIdentities = [];
        $classDistribution = array_fill_keys(array_keys(FireSusceptibilityService::LAND_COVER_NAMES), 0);
        $invalidExamples = [];
        $minDate = null;
        $maxDate = null;

        foreach ($this->csvRows($path) as $entry) {
            $sourceRows++;
            if ($entry['error'] !== null) {
                $invalidRows++;
                if (count($invalidExamples) < 20) {
                    $invalidExamples[] = "CSV line {$entry['line']}: {$entry['error']}";
                }
                continue;
            }

            try {
                $record = $this->normalizeRecord($entry['row'], $susceptibility);
            } catch (RuntimeException $exception) {
                $invalidRows++;
                if (count($invalidExamples) < 20) {
                    $invalidExamples[] = "CSV line {$entry['line']}: {$exception->getMessage()}";
                }
                continue;
            }

            $validRows++;
            $classDistribution[$record['class_id']]++;
            $minDate = $minDate === null ? $record['date'] : min($minDate, $record['date']);
            $maxDate = $maxDate === null ? $record['date'] : max($maxDate, $record['date']);

            if (isset($candidateIdentities[$record['external_id']])) {
                $duplicateRows++;
            } else {
                $candidateIdentities[$record['external_id']] = true;
            }
        }

        $sourceHash = hash_file('sha256', $path);
        if ($sourceHash === false) {
            throw new RuntimeException('Unable to fingerprint the source CSV.');
        }

        return [
            'duplicate_source_records' => $duplicateRows,
            'candidate_identities' => $candidateIdentities,
            'class_distribution' => $classDistribution,
            'invalid_examples' => $invalidExamples,
            'source_rows' => $sourceRows,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'min_date' => $minDate,
            'max_date' => $maxDate,
            'source_hash' => $sourceHash,
        ];
    }

    /** Yield each non-empty CSV data row while keeping source memory bounded. */
    private function csvRows(string $path): \Generator
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open the source CSV.');
        }

        try {
            $header = fgetcsv($handle, escape: '');
            if (! is_array($header)) {
                throw new RuntimeException('The source CSV has no header.');
            }

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
            $header = array_map(static fn ($value) => strtoupper(trim((string) $value)), $header);
            $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $header));
            if ($missing !== []) {
                throw new RuntimeException('Required source columns are missing: '.implode(', ', $missing));
            }

            $lineNumber = 1;
            while (($values = fgetcsv($handle, separator: ',', escape: '')) !== false) {
                $lineNumber++;
                if (count(array_filter($values, static fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                if (count($values) !== count($header)) {
                    yield ['line' => $lineNumber, 'row' => [], 'error' => 'column count does not match the header'];
                    continue;
                }

                $row = array_combine($header, $values);
                yield ['line' => $lineNumber, 'row' => is_array($row) ? $row : [], 'error' => is_array($row) ? null : 'could not read row fields'];
            }
        } finally {
            fclose($handle);
        }
    }

    private function insertFromCsv(string $path, array $analysis, FireSusceptibilityService $susceptibility, int $chunkSize): array
    {
        if (hash_file('sha256', $path) !== $analysis['source_hash']) {
            throw new RuntimeException('The source CSV changed after preflight. No rows were written.');
        }

        $existing = $this->findExistingIdentities(
            $analysis['candidate_identities'],
            $analysis['min_date'],
            $analysis['max_date'],
        );
        $seen = [];
        $batch = [];
        $inserted = 0;

        foreach ($this->csvRows($path) as $entry) {
            if ($entry['error'] !== null) {
                throw new RuntimeException("CSV line {$entry['line']}: {$entry['error']}. No rows were written.");
            }

            $record = $this->normalizeRecord($entry['row'], $susceptibility);
            $identity = $record['external_id'];
            if (! isset($analysis['candidate_identities'][$identity])) {
                throw new RuntimeException('The source CSV changed after preflight. No rows were written.');
            }
            if (isset($seen[$identity])) {
                continue;
            }
            $seen[$identity] = true;

            if (isset($existing[$identity])) {
                continue;
            }

            $batch[] = $record['database_row'];
            if (count($batch) >= $chunkSize) {
                DB::table('titik_lokasi')->insert($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }

        if (count($seen) !== count($analysis['candidate_identities'])
            || hash_file('sha256', $path) !== $analysis['source_hash']) {
            throw new RuntimeException('The source CSV changed during import. The transaction was rolled back.');
        }

        if ($batch !== []) {
            DB::table('titik_lokasi')->insert($batch);
            $inserted += count($batch);
        }

        return ['inserted' => $inserted, 'existing' => count($existing)];
    }

    /** @return array{external_id:string,class_id:int,database_row:array} */
    private function normalizeRecord(array $row, FireSusceptibilityService $susceptibility): array
    {
        foreach (self::REQUIRED_COLUMNS as $column) {
            if (! isset($row[$column]) || trim((string) $row[$column]) === '') {
                throw new RuntimeException("{$column} is empty");
            }
        }

        $latitude = $this->number($row['LATITUDE']);
        $longitude = $this->number($row['LONGITUDE']);
        $confidence = $this->number($row['CONFIDENCE']);
        if ($latitude === null || $latitude < -90 || $latitude > 90) {
            throw new RuntimeException('LATITUDE is invalid');
        }
        if ($longitude === null || $longitude < -180 || $longitude > 180) {
            throw new RuntimeException('LONGITUDE is invalid');
        }
        if ($confidence === null || $confidence < 0 || $confidence > 100) {
            throw new RuntimeException('CONFIDENCE must be numeric from 0 to 100');
        }

        $date = $this->parseDate((string) $row['ACQ_DATE']);
        $acqTime = $this->parseAcqTime((string) $row['ACQ_TIME']);
        $satellite = trim((string) $row['SATELLITE']);
        $instrument = trim((string) $row['INSTRUMENT']);
        $sourceProduct = $this->sourceProduct($satellite, $instrument);
        if ($sourceProduct === null) {
            throw new RuntimeException("Unsupported satellite/instrument identity: {$satellite}/{$instrument}");
        }

        $mapBiomasId = filter_var(trim((string) $row['MAPBIOMAS_ID']), FILTER_VALIDATE_INT);
        $landCoverId = filter_var(trim((string) $row['LAND_COVER_ID']), FILTER_VALIDATE_INT);
        if ($mapBiomasId === false || $landCoverId === false || $mapBiomasId !== $landCoverId) {
            throw new RuntimeException('MAPBIOMAS_ID and LAND_COVER_ID must be matching integer class IDs');
        }
        if (! array_key_exists($landCoverId, FireSusceptibilityService::LAND_COVER_NAMES)) {
            throw new RuntimeException("Class ID {$landCoverId} is not an approved EMBER class");
        }

        $evidence = $susceptibility->classEvidence($landCoverId);
        if (! is_array($evidence)
            || ! isset($evidence['hotspot_evidence'], $evidence['historical_burn_evidence'])) {
            throw new RuntimeException("Class ID {$landCoverId} has no approved HSE/HBE baseline");
        }

        $hse = (float) $evidence['hotspot_evidence'];
        $hbe = (float) $evidence['historical_burn_evidence'];
        $fsi = $susceptibility->fsi($hse, $hbe);
        $timestamp = now();
        $identity = $this->externalId($sourceProduct, $date, $acqTime, $latitude, $longitude);
        $landCoverName = FireSusceptibilityService::LAND_COVER_NAMES[$landCoverId];
        $daynight = strtoupper(trim((string) $row['DAYNIGHT']));
        if (! in_array($daynight, ['D', 'N'], true)) {
            throw new RuntimeException('DAYNIGHT must be D or N');
        }

        return [
            'external_id' => $identity,
            'class_id' => $landCoverId,
            'date' => $date,
            'database_row' => [
                'external_id' => $identity,
                'data_source' => 'NASA FIRMS',
                'source_product' => $sourceProduct,
                'is_nrt' => false,
                'provinsi' => $row['LEVEL_3'] ?: null,
                'kabupaten_kota' => $row['LEVEL_4'] ?: null,
                'kecamatan' => $row['LEVEL_5'] ?: null,
                'desa' => $row['LEVEL_6'] ?: null,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'date' => $date,
                'confidence' => $confidence,
                'satellite' => $satellite,
                'instrument' => $instrument,
                'daynight' => $daynight,
                'acq_time' => $acqTime,
                'land_cover_id' => $landCoverId,
                // The existing titik_lokasi schema names the class-name column `land_cover`.
                'land_cover' => $landCoverName,
                'raw_mapbiomas_class_id' => $mapBiomasId,
                'classification_status' => 'classified',
                'classification_error' => null,
                'classification_attempts' => 0,
                'classified_at' => $timestamp,
                'prior_lcs' => null,
                'empirical_evidence' => null,
                'hotspot_evidence' => $hse,
                'historical_burn_evidence' => $hbe,
                'model_version' => FireSusceptibilityService::MODEL_VERSION,
                'fsi_score' => $fsi,
                'fsi_class' => $susceptibility->fsiClass($fsi),
                'context_flag' => $susceptibility->contextFlag($hse, $hbe),
                'source_payload' => json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ];
    }

    private function parseDate(string $value): string
    {
        if (! preg_match('/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/', trim($value), $matches)
            || ! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            throw new RuntimeException('ACQ_DATE is invalid');
        }

        return sprintf('%04d-%02d-%02d', $matches[1], $matches[2], $matches[3]);
    }

    private function parseAcqTime(string $value): int
    {
        $value = trim($value);
        if (! preg_match('/^\d{1,4}$/', $value)) {
            throw new RuntimeException('ACQ_TIME is invalid');
        }

        $time = (int) $value;
        if ($time > 2359 || ($time % 100) > 59) {
            throw new RuntimeException('ACQ_TIME is outside the valid HHMM range');
        }

        return $time;
    }

    private function sourceProduct(string $satellite, string $instrument): ?string
    {
        $instrument = preg_replace('/[^A-Z0-9]/', '', strtoupper($instrument)) ?? '';
        $satellite = preg_replace('/[^A-Z0-9]/', '', strtoupper($satellite)) ?? '';

        if ($instrument === 'MODIS') {
            return 'MODIS_NRT';
        }
        if ($instrument !== 'VIIRS') {
            return null;
        }

        return match (true) {
            str_contains($satellite, 'NOAA20'), str_contains($satellite, 'JPSS1') => 'VIIRS_NOAA20_NRT',
            str_contains($satellite, 'NOAA21'), str_contains($satellite, 'JPSS2') => 'VIIRS_NOAA21_NRT',
            str_contains($satellite, 'SUOMINPP'), str_contains($satellite, 'SNPP') => 'VIIRS_SNPP_NRT',
            default => null,
        };
    }

    private function externalId(string $sourceProduct, string $date, int $acqTime, float $latitude, float $longitude): string
    {
        // Keep the same identity recipe as NasaFirmsSyncService so overlapping NRT rows upsert in place.
        return hash('sha256', implode('|', [
            $sourceProduct,
            $date,
            (string) $acqTime,
            number_format($latitude, 6, '.', ''),
            number_format($longitude, 6, '.', ''),
        ]));
    }

    /** @param array<string,true> $candidateIdentities @return array<string,true> */
    private function findExistingIdentities(array $candidateIdentities, ?string $minDate, ?string $maxDate): array
    {
        if ($candidateIdentities === [] || $minDate === null || $maxDate === null) {
            return [];
        }

        $found = [];
        foreach (array_chunk(array_keys($candidateIdentities), 500) as $ids) {
            foreach (DB::table('titik_lokasi')->whereIn('external_id', $ids)->pluck('external_id') as $id) {
                if (isset($candidateIdentities[$id])) {
                    $found[$id] = true;
                }
            }
        }

        $legacyRows = DB::table('titik_lokasi')
            ->whereBetween('date', [$minDate, $maxDate])
            ->get(['external_id', 'source_product', 'satellite', 'instrument', 'date', 'acq_time', 'latitude', 'longitude']);

        foreach ($legacyRows as $row) {
            $product = filled($row->source_product ?? null)
                ? (string) $row->source_product
                : $this->sourceProduct((string) ($row->satellite ?? ''), (string) ($row->instrument ?? ''));
            if ($product === null || ! is_numeric($row->acq_time ?? null)
                || ! is_numeric($row->latitude ?? null) || ! is_numeric($row->longitude ?? null)) {
                continue;
            }

            $identity = $this->externalId(
                $product,
                substr((string) $row->date, 0, 10),
                (int) $row->acq_time,
                (float) $row->latitude,
                (float) $row->longitude,
            );
            if (isset($candidateIdentities[$identity])) {
                $found[$identity] = true;
            }
        }

        return $found;
    }

    private function assertUniqueExternalIdIndex(): void
    {
        $hasUniqueExternalId = collect(Schema::getIndexes('titik_lokasi'))->contains(
            static fn (array $index): bool => ($index['unique'] ?? false) && ($index['columns'] ?? []) === ['external_id']
        );

        if (! $hasUniqueExternalId) {
            throw new RuntimeException('A unique index on titik_lokasi.external_id is required for safe, concurrent idempotent imports.');
        }
    }

    private function summary(array $analysis, array $existing): array
    {
        $new = count($analysis['candidate_identities']) - count($existing);

        return [
            'source_rows' => $analysis['source_rows'],
            'valid_rows' => $analysis['valid_rows'],
            'invalid_rows' => $analysis['invalid_rows'],
            'existing_matching_records' => count($existing),
            'new_records_to_insert' => max(0, $new),
            'duplicate_source_records' => $analysis['duplicate_source_records'],
            'class_distribution' => $analysis['class_distribution'],
        ];
    }

    private function printSummary(array $summary, array $invalidExamples, bool $dryRun): void
    {
        $this->info($dryRun ? 'Historical hotspot import dry-run (no writes)' : 'Historical hotspot import preflight');
        $this->line('Source rows: '.$summary['source_rows']);
        $this->line('Valid rows: '.$summary['valid_rows']);
        $this->line('Invalid rows: '.$summary['invalid_rows']);
        $this->line('Existing matching records: '.$summary['existing_matching_records']);
        $this->line('New records to insert: '.$summary['new_records_to_insert']);
        $this->line('Duplicate source records: '.$summary['duplicate_source_records']);
        $this->line('Class distribution (valid source rows; duplicates included):');

        ksort($summary['class_distribution']);
        foreach ($summary['class_distribution'] as $classId => $count) {
            $name = FireSusceptibilityService::LAND_COVER_NAMES[$classId] ?? 'Unknown';
            $this->line("  {$classId} {$name}: {$count}");
        }

        foreach (array_slice($invalidExamples, 0, 20) as $example) {
            $this->warn($example);
        }
    }

    private function number(?string $value): ?float
    {
        if ($value === null || trim($value) === '' || ! is_numeric(trim($value))) {
            return null;
        }

        $number = (float) trim($value);
        return is_finite($number) ? $number : null;
    }
}
