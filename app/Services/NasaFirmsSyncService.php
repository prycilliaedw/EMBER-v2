<?php

namespace App\Services;

use App\Jobs\ClassifyHotspotLandCover;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class NasaFirmsSyncService
{
    public function __construct(
        private AdministrativeProvinceMatcher $provinceMatcher,
    )
    {
    }

    public function sync(array $sources, int $days, bool $dryRun = false, ?int $limit = null): array
    {
        $apiKey = config('ember.firms.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('NASA FIRMS MAP_KEY belum dikonfigurasi pada environment variable NASA_FIRMS_MAP_KEY.');
        }

        $summary = [
            'received' => 0,
            'inside_aoi' => 0,
            'processed' => 0,
            'inserted' => 0,
            'updated' => 0,
            'failed' => 0,
            'first_record' => null,
        ];
        $periodEnd = now()->utc();
        $periodStart = $periodEnd->copy()->subDays(max(1, min($days, 10)));
        $bbox = $this->aoiBoundingBox();

        foreach ($sources as $source) {
            if ($limit !== null && $summary['processed'] >= $limit) {
                break;
            }

            $runId = null;
            if (! $dryRun) {
                $runId = DB::table('firms_sync_runs')->insertGetId([
                    'source' => $source,
                    'status' => 'running',
                    'requested_days' => $days,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            try {
                $url = $this->areaUrl($apiKey, $source, $bbox, $days);
                $response = Http::retry(3, 1000, throw: false)
                    ->timeout((int) config('ember.firms.timeout_seconds', 30))
                    ->accept('text/csv')
                    ->get($url);

                if (! $response->successful()) {
                    throw new RuntimeException('NASA FIRMS mengembalikan HTTP '.$response->status().'.');
                }

                $rows = $this->parseCsv($response->body());
                $summary['received'] += count($rows);
                $inserted = 0;
                $updated = 0;
                $insideAoi = 0;

                foreach ($rows as $row) {
                    $latitude = $this->number($row, ['latitude', 'lat']);
                    $longitude = $this->number($row, ['longitude', 'lon', 'long']);
                    if ($latitude === null || $longitude === null || ! $this->pointInAoi($latitude, $longitude)) {
                        continue;
                    }
                    $summary['inside_aoi']++;
                    $insideAoi++;

                    $date = $this->value($row, ['acq_date']);
                    $time = $this->value($row, ['acq_time']);
                    if (! is_string($date) || $date === '') {
                        continue;
                    }

                    if ($limit !== null && $summary['processed'] >= $limit) {
                        break;
                    }

                    $summary['processed']++;
                    if ($summary['first_record'] === null) {
                        $summary['first_record'] = [
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'acq_date' => $date,
                            'acq_time' => $time,
                            'confidence' => $this->value($row, ['confidence']),
                            'source' => $source,
                        ];
                    }

                    if ($dryRun) {
                        continue;
                    }

                    $acqTime = $this->acqTime($time);
                    $product = $source;
                    $externalId = hash('sha256', implode('|', [$source, $date, (string) $acqTime, number_format($latitude, 6, '.', ''), number_format($longitude, 6, '.', '')]));
                    $province = $this->provinceMatcher->match($latitude, $longitude);
                    $timestamp = now();
                    $payload = $row;

                    $data = [
                        'external_id' => $externalId,
                        'data_source' => 'NASA FIRMS',
                        'source_product' => $product,
                        'is_nrt' => true,
                        'provinsi' => $province,
                        'kabupaten_kota' => null,
                        'kecamatan' => null,
                        'desa' => null,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'date' => substr($date, 0, 10),
                        'confidence' => $this->value($row, ['confidence']),
                        'satellite' => $this->value($row, ['satellite']),
                        'instrument' => $this->value($row, ['instrument']),
                        'daynight' => $this->value($row, ['daynight']),
                        'acq_time' => $acqTime,
                        'frp' => $this->number($row, ['frp']),
                        'brightness' => $this->number($row, ['bright_ti4', 'bright_ti5', 'brightness']),
                        'scan' => $this->number($row, ['scan']),
                        'track' => $this->number($row, ['track']),
                        'fetched_at' => $timestamp,
                        'synced_at' => $timestamp,
                        'source_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'updated_at' => $timestamp,
                    ];

                    $existing = DB::table('titik_lokasi')->where('external_id', $externalId)->first();
                    $payloadForInsert = [...$data, 'created_at' => $timestamp];
                    if ($existing) {
                        DB::table('titik_lokasi')->where('external_id', $externalId)->update($data);
                        $updated++;
                        $hotspotId = (int) $existing->id;
                    } else {
                        $hotspotId = DB::table('titik_lokasi')->insertGetId($payloadForInsert);
                        $inserted++;
                    }

                    $terminalStatuses = [
                        'classified',
                        'zero_pixel',
                        'no_data',
                        'outside_raster',
                        'unmapped_class',
                    ];
                    $currentStatus = $existing && property_exists($existing, 'classification_status')
                        ? $existing->classification_status
                        : null;
                    if (! $existing || ! in_array($currentStatus, $terminalStatuses, true)) {
                        ClassifyHotspotLandCover::dispatch((int) $hotspotId);
                    }
                }

                $summary['inserted'] += $inserted;
                $summary['updated'] += $updated;
                if (! $dryRun && $runId !== null) {
                    $status = $inserted === 0 && $updated === 0 ? 'no_new_data' : 'success';
                    $message = $inserted === 0 && $updated === 0 ? 'Tidak ada data baru yang disimpan.' : null;
                    DB::table('firms_sync_runs')->where('id', $runId)->update([
                        'status' => $status,
                        'received' => count($rows),
                        'inside_aoi' => $insideAoi,
                        'inserted' => $inserted,
                        'updated' => $updated,
                        'message' => $message,
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Throwable $exception) {
                $summary['failed']++;
                $safeMessage = $this->redactSensitiveMessage($exception->getMessage(), $apiKey);
                Log::error('NASA FIRMS synchronization failed', [
                    'source' => $source,
                    'error' => $safeMessage,
                ]);
                if (! $dryRun && $runId !== null) {
                    DB::table('firms_sync_runs')->where('id', $runId)->update([
                        'status' => 'failed',
                        'failed' => 1,
                        'message' => $safeMessage,
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        return $summary;
    }

    private function areaUrl(string $key, string $source, array $bbox, int $days): string
    {
        $area = implode(',', [$bbox['west'], $bbox['south'], $bbox['east'], $bbox['north']]);
        return config('ember.firms.base_url').'/area/csv/'.$key.'/'.$source.'/'.$area.'/'.max(1, min($days, 10));
    }

    private function redactSensitiveMessage(string $message, string $apiKey): string
    {
        $message = str_replace($apiKey, '<redacted>', $message);
        return preg_replace(
            '~(\\/area\\/csv\\/)[^\\/]+~i',
            '$1<redacted>',
            $message
        ) ?? $message;
    }

    private function parseCsv(string $body): array
    {
        if (trim($body) === '' || str_starts_with(strtolower(trim($body)), 'invalid') || str_contains(strtolower($body), 'invalid key')) {
            throw new RuntimeException('Respons NASA FIRMS bukan CSV yang valid atau kunci tidak diterima.');
        }
        $lines = preg_split('/\R/', trim($body));
        if (! is_array($lines) || count($lines) < 2) {
            return [];
        }
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $body);
        rewind($handle);
        $header = fgetcsv($handle, escape: '');
        if (! is_array($header)) {
            throw new RuntimeException('Header respons NASA FIRMS tidak dapat dibaca.');
        }
        $header = array_map(fn ($value) => strtolower(trim((string) $value)), $header);
        $rows = [];
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if (count($values) !== count($header)) {
                continue;
            }
            $rows[] = array_combine($header, $values) ?: [];
        }
        fclose($handle);
        return $rows;
    }

    private function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }
        return null;
    }

    private function number(array $row, array $keys): ?float
    {
        $value = $this->value($row, $keys);
        return $value !== null && is_numeric($value) ? (float) $value : null;
    }

    private function acqTime(?string $value): ?int
    {
        if ($value === null || ! preg_match('/^\d{1,4}$/', $value)) {
            return null;
        }
        $numeric = (int) $value;
        return $numeric >= 0 && $numeric <= 2359 ? $numeric : null;
    }

    public function aoiBoundingBox(): array
    {
        $path = storage_path('app/ember/source/EMBER_AOI_10Provinsi_Sumatra_GAUL2025_v2.geojson');
        if (! is_file($path)) {
            throw new RuntimeException('File AOI EMBER tidak ditemukan.');
        }
        $geojson = json_decode((string) file_get_contents($path), true);
        if (! is_array($geojson)) {
            throw new RuntimeException('File AOI EMBER tidak valid.');
        }
        $west = INF; $south = INF; $east = -INF; $north = -INF;
        $this->collectCoordinates($geojson, $west, $south, $east, $north);
        if (! is_finite($west) || ! is_finite($south) || ! is_finite($east) || ! is_finite($north)) {
            throw new RuntimeException('Bounding box AOI tidak dapat dihitung.');
        }
        return compact('west', 'south', 'east', 'north');
    }

    private function collectCoordinates(mixed $value, float &$west, float &$south, float &$east, float &$north): void
    {
        if (! is_array($value)) return;
        if (array_key_exists('coordinates', $value) && is_array($value['coordinates'])) {
            $this->walkCoordinates($value['coordinates'], $west, $south, $east, $north);
        }
        foreach ($value as $item) {
            if (is_array($item)) $this->collectCoordinates($item, $west, $south, $east, $north);
        }
    }

    private function walkCoordinates(array $coordinates, float &$west, float &$south, float &$east, float &$north): void
    {
        if (isset($coordinates[0]) && is_numeric($coordinates[0]) && isset($coordinates[1]) && is_numeric($coordinates[1])) {
            $west = min($west, (float) $coordinates[0]); $east = max($east, (float) $coordinates[0]);
            $south = min($south, (float) $coordinates[1]); $north = max($north, (float) $coordinates[1]);
            return;
        }
        foreach ($coordinates as $coordinate) {
            if (is_array($coordinate)) $this->walkCoordinates($coordinate, $west, $south, $east, $north);
        }
    }

    public function pointInAoi(float $latitude, float $longitude): bool
    {
        static $polygons;
        if (! isset($polygons)) {
            $path = storage_path('app/ember/source/EMBER_AOI_10Provinsi_Sumatra_GAUL2025_v2.geojson');
            if (! is_file($path)) return false;
            $geojson = json_decode((string) file_get_contents($path), true);
            $polygons = $this->extractPolygons($geojson);
        }
        foreach ($polygons as $polygon) {
            if ($this->pointInPolygon($longitude, $latitude, $polygon)) return true;
        }
        return false;
    }

    private function extractPolygons(mixed $value): array
    {
        if (! is_array($value)) return [];
        $polygons = [];
        if (($value['type'] ?? null) === 'Polygon' && isset($value['coordinates'])) $polygons[] = $value['coordinates'];
        if (($value['type'] ?? null) === 'MultiPolygon' && isset($value['coordinates'])) {
            foreach ($value['coordinates'] as $polygon) $polygons[] = $polygon;
        }
        if (($value['type'] ?? null) === 'GeometryCollection' && isset($value['geometries'])) {
            foreach ($value['geometries'] as $geometry) $polygons = [...$polygons, ...$this->extractPolygons($geometry)];
        }
        foreach ($value as $item) {
            if (is_array($item) && ! isset($item['type'])) $polygons = [...$polygons, ...$this->extractPolygons($item)];
        }
        if (($value['type'] ?? null) === 'Feature') $polygons = [...$polygons, ...$this->extractPolygons($value['geometry'] ?? null)];
        if (($value['type'] ?? null) === 'FeatureCollection') foreach (($value['features'] ?? []) as $feature) $polygons = [...$polygons, ...$this->extractPolygons($feature)];
        return $polygons;
    }

    private function pointInPolygon(float $x, float $y, array $polygon): bool
    {
        if (! isset($polygon[0]) || ! is_array($polygon[0])) return false;
        if (! $this->pointInRing($x, $y, $polygon[0])) return false;
        for ($i = 1, $count = count($polygon); $i < $count; $i++) {
            if ($this->pointInRing($x, $y, $polygon[$i])) return false;
        }
        return true;
    }

    private function pointInRing(float $x, float $y, array $ring): bool
    {
        $inside = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            $xi = (float) ($ring[$i][0] ?? 0); $yi = (float) ($ring[$i][1] ?? 0);
            $xj = (float) ($ring[$j][0] ?? 0); $yj = (float) ($ring[$j][1] ?? 0);
            $intersect = (($yi > $y) !== ($yj > $y)) && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-12) + $xi);
            if ($intersect) $inside = ! $inside;
        }
        return $inside;
    }
}
