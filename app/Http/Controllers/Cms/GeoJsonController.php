<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeoJsonController extends Controller
{
    private const MAX_UPLOAD_KILOBYTES = 716800;

    public function index(): View
    {
        return view('cms.geojson.index', [
            'layers' => DB::table('geojson_layers')->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'geojson' => ['nullable', 'required_without:upload_token', 'file', 'max:'.self::MAX_UPLOAD_KILOBYTES],
            'upload_token' => ['nullable', 'required_without:geojson', 'string', 'regex:/^[A-Za-z0-9-]{20,64}$/'],
            'administrative_level' => ['required', 'in:province,regency,district,village,other'],
            'min_zoom' => ['nullable', 'integer', 'min:0', 'max:22'],
            'max_zoom' => ['nullable', 'integer', 'min:0', 'max:22', 'gte:min_zoom'],
        ], [
            'geojson.uploaded' => 'File gagal diterima oleh PHP. Muat ulang halaman lalu unggah kembali agar file dikirim bertahap.',
            'geojson.required_without' => 'Pilih file PMTiles, GeoJSON, atau JSON.',
            'geojson.file' => 'Berkas layer yang dipilih tidak dapat dibaca sebagai file.',
            'geojson.max' => 'Ukuran file layer melebihi batas 700 MB.',
            'upload_token.required_without' => 'Pilih file layer yang akan diunggah.',
        ]);
        [$file, $chunkPaths] = $request->filled('upload_token')
            ? $this->resolveChunkedUpload($request->string('upload_token')->toString())
            : [$request->file('geojson'), []];

        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw ValidationException::withMessages([
                'geojson' => 'File gagal diterima server. Coba unggah ulang melalui upload bertahap.',
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['geojson', 'json', 'pmtiles'], true)) {
            throw ValidationException::withMessages([
                'geojson' => 'File harus menggunakan ekstensi .pmtiles, .geojson, atau .json.',
            ]);
        }

        $fileFormat = $extension === 'pmtiles' ? 'pmtiles' : 'geojson';
        [$geojsonType, $featureCount] = $fileFormat === 'pmtiles'
            ? $this->inspectPmTiles($file->getRealPath())
            : $this->inspectGeoJson($file->getRealPath(), $file->getSize());

        $filePath = $file->storeAs($fileFormat, Str::uuid().'.'.$extension, 'public');

        if (! is_string($filePath) || $filePath === '') {
            throw ValidationException::withMessages([
                'geojson' => 'File tidak dapat disimpan. Periksa kapasitas dan izin folder storage di server.',
            ]);
        }

        DB::table('geojson_layers')->insert([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'file_path' => $filePath,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'file_format' => $fileFormat,
            'administrative_level' => $validated['administrative_level'],
            'geojson_type' => $geojsonType,
            'feature_count' => $featureCount,
            'min_zoom' => $validated['min_zoom'] ?? 6,
            'max_zoom' => $validated['max_zoom'] ?? 13,
            'is_active' => $fileFormat === 'pmtiles' && $request->boolean('is_active'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($chunkPaths as $chunkPath) {
            if (is_dir($chunkPath)) {
                File::deleteDirectory($chunkPath);
            } else {
                File::delete($chunkPath);
            }
        }

        return back()->with('success', 'Layer '.strtoupper($fileFormat).' berhasil diunggah.');
    }

    public function uploadChunk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'upload_id' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{20,64}$/'],
            'chunk_index' => ['required', 'integer', 'min:0', 'max:2500'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:2500'],
            'original_name' => ['required', 'string', 'max:255'],
            'chunk' => ['required', 'file', 'max:1024'],
        ], [
            'chunk.uploaded' => 'Satu bagian file gagal diterima server. Coba unggah ulang.',
            'chunk.file' => 'Bagian file tidak dapat dibaca server.',
            'chunk.max' => 'Ukuran satu bagian file melebihi 1 MB.',
        ]);

        $chunk = $request->file('chunk');

        if (! $chunk instanceof UploadedFile || ! $chunk->isValid()) {
            return response()->json([
                'message' => 'Potongan file gagal diterima server.',
            ], 422);
        }

        $directory = storage_path('app/private/geojson-chunks/'.auth()->id());
        File::ensureDirectoryExists($directory);

        $uploadId = $validated['upload_id'];
        $partDirectory = $directory.'/'.$uploadId;
        File::ensureDirectoryExists($partDirectory);

        $assembledPath = $directory.'/'.$uploadId.'.part';
        $metaPath = $directory.'/'.$uploadId.'.json';
        $chunkIndex = (int) $validated['chunk_index'];
        $totalChunks = (int) $validated['total_chunks'];
        $originalName = basename($validated['original_name']);
        $meta = is_file($metaPath) ? json_decode((string) file_get_contents($metaPath), true) : null;

        if (! is_array($meta)) {
            $meta = [
                'original_name' => $originalName,
                'total_chunks' => $totalChunks,
            ];
        } elseif ((int) ($meta['total_chunks'] ?? -1) !== $totalChunks
            || (string) ($meta['original_name'] ?? '') !== $originalName) {
            return response()->json([
                'message' => 'Metadata upload berbeda. Pilih ulang file untuk memulai sesi baru.',
            ], 409);
        }

        $chunkPath = $partDirectory.'/'.$chunkIndex.'.part';
        if (! File::copy($chunk->getRealPath(), $chunkPath)) {
            return response()->json(['message' => 'Server tidak dapat menulis file sementara.'], 500);
        }

        $partPaths = [];
        $totalSize = 0;

        for ($index = 0; $index < $totalChunks; $index++) {
            $path = $partDirectory.'/'.$index.'.part';

            if (! is_file($path)) {
                continue;
            }

            $partPaths[$index] = $path;
            $totalSize += (int) filesize($path);
        }

        if ($totalSize > self::MAX_UPLOAD_KILOBYTES * 1024) {
            File::delete([$assembledPath, $metaPath]);
            File::deleteDirectory($partDirectory);

            return response()->json(['message' => 'Ukuran file melebihi batas 700 MB.'], 422);
        }

        $meta['received_chunks'] = count($partPaths);
        file_put_contents($metaPath, json_encode($meta, JSON_THROW_ON_ERROR), LOCK_EX);

        if ($meta['received_chunks'] === $totalChunks) {
            $output = fopen($assembledPath, 'wb');

            if ($output === false) {
                return response()->json(['message' => 'Server gagal menyiapkan file hasil upload.'], 500);
            }

            try {
                for ($index = 0; $index < $totalChunks; $index++) {
                    $input = fopen($partPaths[$index], 'rb');

                    if ($input === false) {
                        return response()->json(['message' => "Bagian file ke-{$index} tidak dapat dibaca."], 500);
                    }

                    stream_copy_to_stream($input, $output);
                    fclose($input);
                }
            } finally {
                fclose($output);
            }
        }

        return response()->json([
            'completed' => $meta['received_chunks'] === $totalChunks,
            'received_chunks' => $meta['received_chunks'],
            'total_chunks' => $totalChunks,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $layer = DB::table('geojson_layers')->where('id', $id)->firstOrFail();
        $validated = $request->validate([
            'administrative_level' => ['required', 'in:province,regency,district,village,other'],
            'min_zoom' => ['required', 'integer', 'min:0', 'max:22'],
            'max_zoom' => ['required', 'integer', 'min:0', 'max:22', 'gte:min_zoom'],
        ]);

        DB::table('geojson_layers')->where('id', $id)->update([
            'administrative_level' => $validated['administrative_level'],
            'min_zoom' => $validated['min_zoom'],
            'max_zoom' => $validated['max_zoom'],
            'is_active' => $layer->file_format === 'pmtiles' && $request->boolean('is_active'),
            'updated_at' => now(),
        ]);

        $message = $layer->file_format === 'pmtiles'
            ? 'Pengaturan layer berhasil diperbarui.'
            : 'GeoJSON tetap disimpan sebagai arsip. Gunakan PMTiles untuk ditampilkan di peta.';

        return back()->with('success', $message);
    }

    public function destroy(int $id): RedirectResponse
    {
        $layer = DB::table('geojson_layers')->where('id', $id)->firstOrFail();

        Storage::disk('public')->delete($layer->file_path);
        DB::table('geojson_layers')->where('id', $id)->delete();

        return back()->with('success', 'Layer wilayah berhasil dihapus.');
    }

    public function show(Request $request, int $id): StreamedResponse
    {
        $layer = DB::table('geojson_layers')->where('id', $id)->firstOrFail();
        $path = Storage::disk('public')->path($layer->file_path);
        abort_unless(is_file($path), 404);

        $size = filesize($path);
        $start = 0;
        $end = $size - 1;
        $status = 200;
        $range = $request->header('Range');

        if ($range && preg_match('/bytes=(\d*)-(\d*)/', $range, $matches)) {
            $start = $matches[1] !== '' ? (int) $matches[1] : 0;
            $end = $matches[2] !== '' ? min((int) $matches[2], $end) : $end;

            if ($start > $end || $start >= $size) {
                abort(416, 'Requested range is not satisfiable.');
            }

            $status = 206;
        }

        $length = $end - $start + 1;
        $headers = [
            'Accept-Ranges' => 'bytes',
            'Content-Length' => (string) $length,
            'Content-Type' => $layer->file_format === 'pmtiles'
                ? 'application/vnd.pmtiles'
                : 'application/geo+json',
            'Cache-Control' => 'public, max-age=86400',
        ];

        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return response()->stream(function () use ($path, $start, $length) {
            $handle = fopen($path, 'rb');

            if ($handle === false) {
                return;
            }

            fseek($handle, $start);
            $remaining = $length;

            while ($remaining > 0 && ! feof($handle)) {
                $chunk = fread($handle, min(1024 * 1024, $remaining));

                if ($chunk === false || $chunk === '') {
                    break;
                }

                echo $chunk;
                $remaining -= strlen($chunk);
            }

            fclose($handle);
        }, $status, $headers);
    }

    private function inspectPmTiles(string $path): array
    {
        $handle = fopen($path, 'rb');
        $header = $handle === false ? false : fread($handle, 8);

        if ($handle !== false) {
            fclose($handle);
        }

        if ($header === false || strlen($header) < 8 || substr($header, 0, 7) !== 'PMTiles') {
            throw ValidationException::withMessages([
                'geojson' => 'Isi file bukan arsip PMTiles yang valid.',
            ]);
        }

        $version = ord($header[7]);

        if ($version !== 3) {
            throw ValidationException::withMessages([
                'geojson' => "Versi PMTiles {$version} belum didukung. Gunakan PMTiles versi 3.",
            ]);
        }

        return ['PMTiles v3', null];
    }

    private function resolveChunkedUpload(string $uploadId): array
    {
        $directory = storage_path('app/private/geojson-chunks/'.auth()->id());
        $partPath = $directory.'/'.$uploadId.'.part';
        $metaPath = $directory.'/'.$uploadId.'.json';
        $meta = is_file($metaPath) ? json_decode((string) file_get_contents($metaPath), true) : null;

        if (! is_file($partPath)
            || ! is_array($meta)
            || (int) ($meta['received_chunks'] ?? 0) !== (int) ($meta['total_chunks'] ?? -1)) {
            throw ValidationException::withMessages([
                'geojson' => 'Upload bertahap belum lengkap. Pilih file dan unggah kembali.',
            ]);
        }

        $fileSize = filesize($partPath);

        if ($fileSize === false || $fileSize <= 0 || $fileSize > self::MAX_UPLOAD_KILOBYTES * 1024) {
            throw ValidationException::withMessages([
                'geojson' => 'Ukuran file hasil upload tidak valid atau melebihi 700 MB.',
            ]);
        }

        return [
            new UploadedFile(
                $partPath,
                basename((string) $meta['original_name']),
                null,
                UPLOAD_ERR_OK,
                true,
            ),
            [$partPath, $metaPath, $directory.'/'.$uploadId],
        ];
    }

    private function inspectGeoJson(string $path, int $fileSize): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'geojson' => 'File GeoJSON tidak dapat dibaca.',
            ]);
        }

        $header = fread($handle, min($fileSize, 2 * 1024 * 1024)) ?: '';
        fseek($handle, max(0, $fileSize - 8192));
        $footer = stream_get_contents($handle) ?: '';
        fclose($handle);

        $startsAsObject = str_starts_with(ltrim($header, "\xEF\xBB\xBF \t\r\n"), '{');
        $endsAsObject = str_ends_with(rtrim($footer), '}');
        preg_match('/"type"\s*:\s*"([A-Za-z]+)"/', $header, $typeMatch);
        $geojsonType = $typeMatch[1] ?? null;
        $allowedTypes = [
            'FeatureCollection', 'Feature', 'GeometryCollection',
            'Point', 'MultiPoint', 'LineString', 'MultiLineString',
            'Polygon', 'MultiPolygon',
        ];

        if (! $startsAsObject || ! $endsAsObject || ! in_array($geojsonType, $allowedTypes, true)) {
            throw ValidationException::withMessages([
                'geojson' => 'Isi file bukan struktur GeoJSON yang valid.',
            ]);
        }

        if ($geojsonType === 'FeatureCollection' && ! preg_match('/"features"\s*:\s*\[/', $header)) {
            throw ValidationException::withMessages([
                'geojson' => 'FeatureCollection harus memiliki daftar features.',
            ]);
        }

        $featureCount = null;

        if ($geojsonType === 'FeatureCollection' && $fileSize <= 10 * 1024 * 1024) {
            $decoded = json_decode(file_get_contents($path), true);

            if (! is_array($decoded) || ! is_array($decoded['features'] ?? null)) {
                throw ValidationException::withMessages([
                    'geojson' => 'Isi file bukan struktur GeoJSON yang valid.',
                ]);
            }

            $featureCount = count($decoded['features']);
        }

        return [$geojsonType, $featureCount];
    }
}
