<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class MapBiomasSpatialMatcher
{
    private ?array $rasters = null;
    private $process = null;
    private $pipes = null;

    public const RAW_TO_EMBER = [
        3 => 3, 5 => 5, 9 => 9, 13 => 13, 21 => 21, 24 => 24,
        25 => 25, 30 => 30, 31 => 31, 33 => 33, 35 => 35, 40 => 40, 76 => 76,
    ];
    private static ?array $crosswalk = null;

    public function match(float $latitude, float $longitude): ?array
    {
        try {
            $sample = $this->sampleRaster($latitude, $longitude);
        } catch (Throwable $exception) {
            return [
                'status' => 'raster_unavailable',
                'raw_class_id' => null,
                'error' => $exception->getMessage(),
            ];
        }
        if (! is_array($sample)) {
            return ['status' => 'raster_unavailable', 'raw_class_id' => null, 'error' => 'Raster sampler returned no response'];
        }
        if (($sample['status'] ?? '') !== 'sampled') {
            return [
                'status' => $sample['status'] ?? 'raster_unavailable',
                'raw_class_id' => isset($sample['raw_class_id']) ? (int) $sample['raw_class_id'] : null,
                'error' => $sample['error'] ?? null,
            ];
        }

        $classId = (int) $sample['raw_class_id'];
        $emberClassId = $this->mapRawClassId($classId);
        if ($emberClassId === null) {
            return [
                'status' => 'unmapped_class',
                'raw_class_id' => $classId,
                'error' => 'Raw Collection 4 class has no verified EMBER mapping',
            ];
        }

        $class = config("ember.class_evidence.{$emberClassId}");
        if (! is_array($class)) {
            return [
                'status' => 'unmapped_class',
                'raw_class_id' => $classId,
                'error' => "No EMBER evidence configuration for class {$emberClassId}",
            ];
        }

        return [
            'status' => 'classified',
            'raw_class_id' => $classId,
            'id' => $emberClassId,
            'name' => $class['name'] ?? null,
        ];
    }

    public function mapRawClassId(int $rawClassId): ?int
    {
        if (self::$crosswalk === null) {
            self::$crosswalk = $this->loadAuthoritativeCrosswalk();
        }
        return self::$crosswalk[$rawClassId] ?? null;
    }

    private function loadAuthoritativeCrosswalk(?string $path = null): array
    {
        $path ??= storage_path('app/ember/output/ember_collection4_authoritative_crosswalk.csv');
        if (! is_file($path)) {
            return self::RAW_TO_EMBER;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return self::RAW_TO_EMBER;
        }

        $header = fgetcsv($handle, escape: '');
        if (! is_array($header)) {
            fclose($handle);
            return self::RAW_TO_EMBER;
        }
        $header = array_map(fn ($value) => strtolower(trim((string) $value, "\" \t\n\r\0\x0B")), $header);
        $mapping = [];
        $ambiguousRawIds = [];

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }
            $record = array_combine($header, $row);
            if (! is_array($record)) {
                continue;
            }
            $verification = strtolower((string) ($record['verification'] ?? ''));
            if (str_contains($verification, 'do not map') || str_contains($verification, 'do not update') || str_contains($verification, 'unverified')) {
                continue;
            }
            $emberId = filter_var($record['ember_class_id'] ?? null, FILTER_VALIDATE_INT);
            $rawCell = trim((string) ($record['raw_collection4_id'] ?? ''));
            if ($emberId === false
                || ! array_key_exists((int) $emberId, FireSusceptibilityService::LAND_COVER_NAMES)
                || $rawCell === '') {
                continue;
            }

            $rawIds = preg_split('/\s*,\s*/', $rawCell, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($rawIds as $rawValue) {
                $rawId = filter_var(trim($rawValue), FILTER_VALIDATE_INT);
                if ($rawId === false || $rawId <= 0) {
                    continue;
                }

                if (isset($mapping[$rawId]) && $mapping[$rawId] !== (int) $emberId) {
                    unset($mapping[$rawId]);
                    $ambiguousRawIds[$rawId] = true;
                    continue;
                }

                if (! isset($ambiguousRawIds[$rawId])) {
                    $mapping[$rawId] = (int) $emberId;
                }
            }
        }
        fclose($handle);
        return $mapping !== [] ? $mapping : self::RAW_TO_EMBER;
    }

    private function sampleRaster(float $latitude, float $longitude): array
    {
        $directory = config('ember.mapbiomas.raster_directory');
        if (! is_string($directory) || trim($directory) === '') {
            $configuredRaster = config('ember.mapbiomas.raster_path');
            if (is_string($configuredRaster) && trim($configuredRaster) !== '') {
                $directory = dirname($configuredRaster);
            }
        }
        if (is_string($directory) && ! str_starts_with($directory, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $directory)) {
            $directory = base_path(str_replace('/', DIRECTORY_SEPARATOR, $directory));
        }
        if (! is_string($directory) || trim($directory) === '') {
            throw new RuntimeException('MAPBIOMAS_LAND_COVER_RASTER_DIRECTORY is not configured');
        }
        if (! is_dir($directory)) {
            throw new RuntimeException('MapBiomas raster directory does not exist: '.$directory);
        }

        $python = $this->findPythonExecutable();
        if ($this->process === null || ! is_resource($this->process)) {
            $script = base_path('storage/app/ember/scripts/raster_sample_server.py');
            if (! is_file($script)) {
                throw new RuntimeException('Raster sampler script is missing: '.$script);
            }
            $descriptorSpec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $this->process = proc_open([$python, $script, $directory], $descriptorSpec, $this->pipes);
            if (! is_resource($this->process)) {
                throw new RuntimeException('Unable to start the MapBiomas raster sampler');
            }
        }

        $province = (new AdministrativeProvinceMatcher())->match($latitude, $longitude);
        $provinceRaster = $province === null ? null : match ($province) {
            'Bengkulu' => 'ember_mapbiomas_collection4_2024_aoi_01.tif',
            'Lampung' => 'ember_mapbiomas_collection4_2024_aoi_02.tif',
            'Kepulauan Bangka Belitung' => 'ember_mapbiomas_collection4_2024_aoi_03.tif',
            'Sumatera Barat' => 'ember_mapbiomas_collection4_2024_aoi_04.tif',
            'Jambi' => 'ember_mapbiomas_collection4_2024_aoi_05.tif',
            'Aceh' => 'ember_mapbiomas_collection4_2024_aoi_06.tif',
            'Kepulauan Riau' => 'ember_mapbiomas_collection4_2024_kepulauan_riau.tif',
            'Sumatera Selatan' => 'ember_mapbiomas_collection4_2024_aoi_08.tif',
            'Riau' => 'ember_mapbiomas_collection4_2024_aoi_09.tif',
            'Sumatera Utara' => 'ember_mapbiomas_collection4_2024_aoi_10.tif',
            default => null,
        };

        $request = json_encode([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'raster_file' => $provinceRaster,
        ], JSON_UNESCAPED_SLASHES);
        if ($request === false || fwrite($this->pipes[0], $request.PHP_EOL) === false) {
            throw new RuntimeException('Unable to send coordinate to raster sampler');
        }
        fflush($this->pipes[0]);
        $line = fgets($this->pipes[1]);
        if ($line === false) {
            $stderr = stream_get_contents($this->pipes[2]);
            throw new RuntimeException(trim($stderr) !== '' ? trim($stderr) : 'Raster sampler stopped without a response');
        }

        $response = json_decode(trim($line), true);
        if (! is_array($response)) {
            throw new RuntimeException('Raster sampler returned invalid JSON');
        }
        return $response;
    }

    public function findPythonExecutable(): string
    {
        $candidate = config('ember.mapbiomas.python_executable');
        if (! is_string($candidate) || trim($candidate) === '') {
            throw new RuntimeException('MAPBIOMAS_PYTHON_EXECUTABLE is not configured');
        }

        $candidate = trim($candidate);
        if (! str_starts_with($candidate, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $candidate)) {
            $candidate = base_path(str_replace('/', DIRECTORY_SEPARATOR, $candidate));
        }
        if (! is_file($candidate)) {
            throw new RuntimeException('Configured MapBiomas Python executable does not exist: '.$candidate);
        }

        $command = escapeshellarg($candidate).' -c '.escapeshellarg('import rasterio');
        exec($command.' 2>NUL', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Configured MapBiomas Python executable cannot import rasterio: '.$candidate);
        }
        return $candidate;
    }

    public function __destruct()
    {
        if ($this->process !== null && is_resource($this->process)) {
            if (is_array($this->pipes)) {
                foreach ($this->pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
            }
            proc_close($this->process);
        }
    }
}
