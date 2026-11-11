<?php

namespace App\Services;

use RuntimeException;

final class AdministrativeLocationMatcher
{
    private static $process = null;
    private static array $pipes = [];

    public function match(float $latitude, float $longitude): ?array
    {
        if (! $this->startServer()) {
            return null;
        }

        $request = json_encode(['latitude' => $latitude, 'longitude' => $longitude], JSON_UNESCAPED_SLASHES);
        if ($request === false || fwrite(self::$pipes[0], $request.PHP_EOL) === false) {
            $this->stopServer();
            throw new RuntimeException('Unable to send coordinate to administrative boundary matcher.');
        }
        fflush(self::$pipes[0]);
        $line = fgets(self::$pipes[1]);
        if ($line === false) {
            $error = stream_get_contents(self::$pipes[2]);
            $this->stopServer();
            throw new RuntimeException(trim($error) !== '' ? trim($error) : 'Administrative boundary matcher stopped without a response.');
        }

        $result = json_decode(trim($line), true);
        if (isset($result['error'])) {
            throw new RuntimeException((string) $result['error']);
        }

        return is_array($result) ? $result : null;
    }

    private function startServer(): bool
    {
        if (is_resource(self::$process)) {
            return true;
        }

        $boundaries = storage_path('app/ember/source/sumatra_admin/village.ndjson');
        $script = base_path('storage/app/ember/scripts/administrative_match_server.py');
        if (! is_file($boundaries) || ! is_file($script)) {
            return false;
        }

        $python = config('ember.mapbiomas.python_executable', 'python');
        if (! is_string($python) || trim($python) === '') {
            throw new RuntimeException('Python executable is not configured for administrative matching.');
        }
        $python = trim($python);
        if (! str_starts_with($python, DIRECTORY_SEPARATOR) && ! preg_match('/^[A-Za-z]:[\\\\\/]/', $python)) {
            $python = base_path(str_replace('/', DIRECTORY_SEPARATOR, $python));
        }
        if (str_contains($python, DIRECTORY_SEPARATOR) && ! is_file($python)) {
            throw new RuntimeException('Configured Python executable does not exist: '.$python);
        }

        $descriptorSpec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        self::$process = proc_open([$python, $script, $boundaries], $descriptorSpec, self::$pipes);
        return is_resource(self::$process);
    }

    private function stopServer(): void
    {
        foreach (self::$pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        self::$pipes = [];
        if (is_resource(self::$process)) {
            proc_close(self::$process);
        }
        self::$process = null;
    }

    public function __destruct()
    {
        // Leave the worker alive for the next queued hotspot in this PHP process.
    }
}
