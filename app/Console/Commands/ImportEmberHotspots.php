<?php

namespace App\Console\Commands;

use App\Services\FireSusceptibilityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportEmberHotspots extends Command
{
    protected $signature = 'ember:import-hotspots
        {path : Path to the enriched EMBER hotspot CSV}
        {--truncate : Empty titik_lokasi before importing}
        {--chunk=1000 : Insert batch size}';

    protected $description =
        'Import NASA/MapBiomas-enriched EMBER hotspots and calculate experimental FSI from class-level HSE and HBE.';

    public function handle(
        FireSusceptibilityService $susceptibility
    ): int {
        $path = $this->argument('path');

        if (!is_file($path) || !is_readable($path)) {
            $this->error("CSV tidak dapat dibaca: {$path}");
            return self::FAILURE;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException(
                'Gagal membuka CSV.'
            );
        }

        try {
            /*
             * ---------------------------------------------------------
             * READ HEADER
             * ---------------------------------------------------------
             */

            $header = fgetcsv(
                $handle,
                escape: ''
            );

            if (!is_array($header)) {
                throw new RuntimeException(
                    'CSV kosong.'
                );
            }

            $header[0] = preg_replace(
                '/^\xEF\xBB\xBF/',
                '',
                trim((string) $header[0])
            ) ?? trim((string) $header[0]);

            $header = array_map(
                static fn ($value) =>
                    trim((string) $value),
                $header
            );

            /*
            * ---------------------------------------------------------
            * REQUIRED SOURCE COLUMNS
            * ---------------------------------------------------------
            *
            * FSI values are recalculated by FireSusceptibilityService
            * during import. The source CSV therefore only needs the
            * NASA/MapBiomas-enriched fields and empirical evidence.
            */

            $required = [
                'LATITUDE',
                'LONGITUDE',
                'ACQ_DATE',
                'ACQ_TIME',
                'SATELLITE',
                'INSTRUMENT',
                'CONFIDENCE',
                'DAYNIGHT',
                'LEVEL_3',
                'LEVEL_4',
                'LEVEL_5',
                'LEVEL_6',
            ];

            foreach ($required as $column) {
                if (!in_array($column, $header, true)) {
                    throw new RuntimeException(
                        "Kolom wajib tidak ditemukan: {$column}"
                    );
                }
            }

            $index = array_flip($header);

            // Accept either the normalized EMBER class field or the source MapBiomas ID.
            $landCoverIdColumn = isset($index['LAND_COVER_ID'])
                ? 'LAND_COVER_ID'
                : (isset($index['MAPBIOMAS_ID']) ? 'MAPBIOMAS_ID' : null);

            if ($landCoverIdColumn === null) {
                throw new RuntimeException(
                    'Kolom LAND_COVER_ID atau MAPBIOMAS_ID tidak ditemukan.'
                );
            }

            // Destructive truncate happens only after file and header checks pass.
            if ($this->option('truncate')) {
                DB::table('titik_lokasi')->truncate();
                $this->warn('titik_lokasi dikosongkan sebelum impor.');
            }

            /*
             * ---------------------------------------------------------
             * IMPORT SETTINGS
             * ---------------------------------------------------------
             */

            $batch = [];
            $processed = 0;
            $inserted = 0;

            $chunkSize = max(
                100,
                (int) $this->option('chunk')
            );

            /*
             * ---------------------------------------------------------
             * PROCESS CSV
             * ---------------------------------------------------------
             */

            while (
                ($row = fgetcsv(
                    $handle,
                    separator: ',',
                    escape: ''
                )) !== false
            ) {
                if (
                    count(
                        array_filter(
                            $row,
                            static fn ($value) =>
                                trim((string) $value) !== ''
                        )
                    ) === 0
                ) {
                    continue;
                }

                $processed++;

                /*
                 * -----------------------------------------------------
                 * SOURCE VALUES
                 * -----------------------------------------------------
                 */

                $confidence = $this->number(
                    $row[$index['CONFIDENCE']] ?? null
                );

                $landCoverId = is_numeric(
                    $row[$index[$landCoverIdColumn]] ?? null
                )
                    ? (int) $row[$index[$landCoverIdColumn]]
                    : null;


                /*
                 * -----------------------------------------------------
                 * VALIDATION
                 * -----------------------------------------------------
                 */

                if ($confidence === null) {
                    throw new RuntimeException(
                        "CONFIDENCE tidak valid pada baris CSV {$processed}."
                    );
                }

                if ($landCoverId === null) {
                    throw new RuntimeException(
                        "LAND_COVER_ID tidak valid pada baris CSV {$processed}."
                    );
                }

                $classEvidence = $susceptibility->classEvidence($landCoverId);

                if ($classEvidence === null) {
                    throw new RuntimeException(
                        "LAND_COVER_ID {$landCoverId} tidak tersedia dalam tabel evidence 13 kelas. "
                        . "Baris CSV: {$processed}."
                    );
                }

                $hotspotEvidence = (float) $classEvidence['hotspot_evidence'];
                $historicalBurnEvidence = (float) $classEvidence['historical_burn_evidence'];

                /*
                 * -----------------------------------------------------
                 * LAND COVER
                 * -----------------------------------------------------
                 */

                $landCover =
                    FireSusceptibilityService::LAND_COVER_NAMES[
                        $landCoverId
                    ] ?? null;

                // HSE and HBE are class-level evidence values; confidence is not a direct FIS input.
                $fsi = $susceptibility->fsi(
                    $hotspotEvidence,
                    $historicalBurnEvidence
                );

                $fsiClass = $susceptibility->fsiClass($fsi);

                $contextFlag = $susceptibility->contextFlag(
                    $hotspotEvidence,
                    $historicalBurnEvidence
                );

                /*
                 * -----------------------------------------------------
                 * DATE / DAY-NIGHT
                 * -----------------------------------------------------
                 */

                $date = $this->parseDate(
                    $row[$index['ACQ_DATE']] ?? null
                );

                $daynight = strtoupper(
                    trim(
                        (string) (
                            $row[$index['DAYNIGHT']] ?? ''
                        )
                    )
                );

                $timestamp = now();

                /*
                 * -----------------------------------------------------
                 * DATABASE ROW
                 * -----------------------------------------------------
                 * Legacy prior/hybrid fields are retained as NULL for
                 * compatibility. HSE/HBE are stored in explicit columns.
                 */

                $batch[] = [
                    'provinsi' =>
                        $row[$index['LEVEL_3']] ?: null,

                    'kabupaten_kota' =>
                        $row[$index['LEVEL_4']] ?: null,

                    'kecamatan' =>
                        $row[$index['LEVEL_5']] ?: null,

                    'desa' =>
                        $row[$index['LEVEL_6']] ?: null,

                    'latitude' =>
                        $this->number(
                            $row[$index['LATITUDE']] ?? null
                        ),

                    'longitude' =>
                        $this->number(
                            $row[$index['LONGITUDE']] ?? null
                        ),

                    'date' => $date,

                    'confidence' => $confidence,

                    'satellite' =>
                        $row[$index['SATELLITE']] ?: null,

                    'instrument' =>
                        $row[$index['INSTRUMENT']] ?: null,

                    'daynight' =>
                        $daynight !== ''
                            ? substr($daynight, 0, 1)
                            : null,

                    'acq_time' =>
                        is_numeric(
                            $row[$index['ACQ_TIME']] ?? null
                        )
                            ? (int) $row[$index['ACQ_TIME']]
                            : null,

                    'land_cover_id' =>
                        $landCoverId,

                    'land_cover' =>
                        $landCover,

                    // Legacy columns are intentionally null; HSE/HBE are stored in explicit fields.
                    'prior_lcs' => null,
                    'empirical_evidence' => null,
                    'hotspot_evidence' => $hotspotEvidence,
                    'historical_burn_evidence' => $historicalBurnEvidence,
                    'model_version' => FireSusceptibilityService::MODEL_VERSION,

                    'fsi_score' =>
                        $fsi,

                    'fsi_class' =>
                        $fsiClass,

                    'context_flag' =>
                        $contextFlag,

                    'created_at' =>
                        $timestamp,

                    'updated_at' =>
                        $timestamp,
                ];

                /*
                 * -----------------------------------------------------
                 * INSERT CHUNK
                 * -----------------------------------------------------
                 */

                if (count($batch) >= $chunkSize) {
                    DB::table('titik_lokasi')
                        ->insert($batch);

                    $inserted += count($batch);

                    $batch = [];

                    $this->line(
                        "Imported {$inserted} rows..."
                    );
                }
            }

            /*
             * ---------------------------------------------------------
             * INSERT REMAINING
             * ---------------------------------------------------------
             */

            if ($batch !== []) {
                DB::table('titik_lokasi')
                    ->insert($batch);

                $inserted += count($batch);
            }
        } finally {
            fclose($handle);
        }

        $this->info(
            "Selesai: {$inserted} hotspot berhasil diimpor "
            . "dari {$processed} baris."
        );

        return self::SUCCESS;
    }

    private function number(
        ?string $value
    ): ?float {
        if (
            $value === null
            || trim($value) === ''
            || !is_numeric(trim($value))
        ) {
            return null;
        }

        return (float) trim($value);
    }

    private function parseDate(
        ?string $value
    ): ?string {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (
            preg_match(
                '/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/',
                $value,
                $matches
            ) === 1
        ) {
            return sprintf(
                '%04d-%02d-%02d',
                $matches[1],
                $matches[2],
                $matches[3]
            );
        }

        throw new RuntimeException(
            "Format tanggal tidak dikenali: {$value}"
        );
    }
}