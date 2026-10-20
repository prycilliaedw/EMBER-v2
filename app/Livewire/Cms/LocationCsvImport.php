<?php

namespace App\Livewire\Cms;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use RuntimeException;

class LocationCsvImport extends Component
{
    use WithFileUploads;

    public $csvFile;

    public array $importErrors = [];

    public function importCsv()
    {
        $this->resetErrorBag();
        $this->importErrors = [];

        $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [
            'csvFile.required' => 'Pilih file CSV yang akan diimpor.',
            'csvFile.mimes' => 'File harus berformat CSV.',
            'csvFile.max' => 'Ukuran file CSV maksimal 5 MB.',
        ]);

        try {
            $rows = $this->readCsv($this->csvFile->getRealPath());
        } catch (RuntimeException $exception) {
            $this->addError('csvFile', $exception->getMessage());

            return null;
        }

        $validRows = [];

        foreach ($rows as $row) {
            $line = $row['_line'];
            unset($row['_line']);

            $validator = Validator::make($row, $this->locationRules());

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $this->importErrors[] = "Baris {$line}: {$message}";
                }

                continue;
            }

            $validRows[] = [
                ...$validator->validated(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($this->importErrors !== []) {
            $this->importErrors = array_slice($this->importErrors, 0, 20);
            $this->addError('csvFile', 'Impor dibatalkan karena terdapat data yang tidak valid.');

            return null;
        }

        if ($validRows === []) {
            $this->addError('csvFile', 'CSV tidak memiliki baris data untuk diimpor.');

            return null;
        }

        DB::transaction(fn () => DB::table('titik_lokasi')->insert($validRows));

        session()->flash('success', count($validRows).' titik lokasi berhasil diimpor dari CSV.');

        return $this->redirectRoute('cms.locations.index');
    }

    public function render()
    {
        return view('livewire.cms.location-csv-import');
    }

    private function locationRules(): array
    {
        return [
            'provinsi' => ['nullable', 'string', 'max:255'],
            'kabupaten_kota' => ['nullable', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'desa' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'confidence' => ['nullable', 'string', 'max:50'],
        ];
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('File CSV tidak dapat dibaca.');
        }

        try {
            $headerLine = fgets($handle);

            if ($headerLine === false) {
                throw new RuntimeException('File CSV kosong.');
            }

            [$header, $delimiter] = $this->parseHeader($headerLine);

            $header = array_map(
                fn ($column) => strtolower(trim((string) $column)),
                $header
            );
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];

            $expectedHeader = [
                'provinsi',
                'kabupaten_kota',
                'kecamatan',
                'desa',
                'latitude',
                'longitude',
                'date',
                'confidence',
            ];

            if ($header !== $expectedHeader) {
                throw new RuntimeException(
                    'Header CSV tidak sesuai. Gunakan template CSV yang tersedia tanpa mengubah nama atau urutan kolom.'
                );
            }

            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle, separator: $delimiter, escape: '')) !== false) {
                $line++;

                if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                if (count($values) !== count($expectedHeader)) {
                    throw new RuntimeException("Baris {$line} memiliki jumlah kolom yang tidak sesuai.");
                }

                $row = array_combine($expectedHeader, $values);

                foreach ($row as $column => $value) {
                    $value = trim((string) $value);
                    $row[$column] = $value === '' ? null : $value;
                }

                $row['latitude'] = $this->normalizeCoordinate($row['latitude'], false);
                $row['longitude'] = $this->normalizeCoordinate($row['longitude'], true);
                $row['date'] = $this->normalizeDate($row['date']);

                if ($row['latitude'] === null && $row['longitude'] === null) {
                    continue;
                }

                $row['_line'] = $line;
                $rows[] = $row;

                if (count($rows) > 10000) {
                    throw new RuntimeException('Satu file CSV maksimal berisi 10.000 baris data.');
                }
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function parseHeader(string $headerLine): array
    {
        $expectedHeader = [
            'provinsi',
            'kabupaten_kota',
            'kecamatan',
            'desa',
            'latitude',
            'longitude',
            'date',
            'confidence',
        ];

        foreach ([',', ';', "\t"] as $delimiter) {
            $header = str_getcsv($headerLine, $delimiter, escape: '');
            $header = array_map(
                fn ($column) => strtolower(trim((string) $column)),
                $header
            );
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];

            if ($header === $expectedHeader) {
                return [$header, $delimiter];
            }
        }

        throw new RuntimeException(
            'Header CSV tidak sesuai. Gunakan template CSV yang tersedia tanpa mengubah nama atau urutan kolom.'
        );
    }

    private function normalizeCoordinate(?string $value, bool $isLongitude): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(str_replace(["\u{2212}", "\u{2013}", "\u{2014}", "\u{00A0}"], ['-', '-', '-', ''], $value));

        if (preg_match('/^=["\'](.+)["\']$/', $value, $matches) === 1) {
            $value = trim($matches[1]);
        } elseif (preg_match('/^["\'](.+)["\']$/', $value, $matches) === 1) {
            $value = trim($matches[1]);
        }

        if (preg_match('/^[+-]?\d+,\d+$/', $value) === 1) {
            $value = str_replace(',', '.', $value);
        }

        $hasRepeatedThousandsSeparators = substr_count($value, '.') > 1
            && preg_match('/^[+-]?[\d.]+$/', $value) === 1;

        if ($hasRepeatedThousandsSeparators) {
            $value = str_replace('.', '', $value);
        }

        if (is_numeric($value)) {
            $coordinate = (float) $value;

            if ($isLongitude && ($hasRepeatedThousandsSeparators || abs($coordinate) >= 900)) {
                while (abs($coordinate) > 142) {
                    $coordinate /= 10;
                }
            } elseif (! $isLongitude && ($hasRepeatedThousandsSeparators || (abs($coordinate) <= 90 && ($coordinate > 6.5 || $coordinate < -11)) || ($coordinate > 90 && $coordinate < 650))) {
                while ($coordinate > 6.5 || $coordinate < -11) {
                    $coordinate /= 10;
                }
            }

            $value = rtrim(rtrim(number_format($coordinate, 8, '.', ''), '0'), '.');
        }

        return $value;
    }

    private function normalizeDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $format = '!d/m/y';

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $parts) === 1) {
            $firstPart = (int) $parts[1];
            $secondPart = (int) $parts[2];
            $hasPaddedDayAndMonth = strlen($parts[1]) === 2 && strlen($parts[2]) === 2;

            $format = $firstPart > 12 || $hasPaddedDayAndMonth ? '!d/m/Y' : '!m/d/Y';

            if ($secondPart > 12) {
                $format = '!m/d/Y';
            }
        }

        foreach ([$format] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }
}
