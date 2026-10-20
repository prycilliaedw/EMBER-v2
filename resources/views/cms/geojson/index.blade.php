@extends('layouts.admin')

@section('title', 'Layer Wilayah - EMBER CMS')

@section('content')
    <div class="mx-auto max-w-7xl">
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-wider text-red-600">Data spasial</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">Layer Wilayah</h1>
            <p class="mt-2 text-sm text-slate-600">Unggah PMTiles yang ringkas atau GeoJSON untuk menyimpan batas wilayah EMBER.</p>
        </div>

        <div class="grid gap-6 xl:grid-cols-[380px_1fr]">
            <form id="geojson-upload-form" method="POST" action="{{ route('cms.geojson.store') }}" enctype="multipart/form-data" class="h-fit bg-white p-6 shadow-sm ring-1 ring-slate-200">
                @csrf
                <input id="geojson-upload-token" type="hidden" name="upload_token" value="{{ old('upload_token') }}">
                <h2 class="text-lg font-bold text-slate-900">Upload layer wilayah</h2>

                <div class="mt-5 space-y-5">
                    <div>
                        <label for="name" class="block text-sm font-semibold text-slate-700">Nama layer</label>
                        <input id="name" name="name" value="{{ old('name') }}" placeholder="Contoh: Batas Provinsi Sumatera" required class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                        @error('name')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-semibold text-slate-700">Keterangan</label>
                        <textarea id="description" name="description" rows="3" class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">{{ old('description') }}</textarea>
                        @error('description')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="geojson" class="block text-sm font-semibold text-slate-700">File PMTiles / GeoJSON</label>
                        <input id="geojson" name="geojson" type="file" accept=".pmtiles,.geojson,.json,application/vnd.pmtiles,application/geo+json,application/json" required class="mt-2 block w-full border border-slate-300 bg-white p-3 text-sm text-slate-600 file:mr-4 file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-xs file:font-bold file:text-white">
                        <p class="mt-2 text-xs leading-5 text-slate-500">Disarankan `.pmtiles` versi 3 untuk batas kecamatan. Maksimal 700 MB; file besar dikirim bertahap agar tidak terkena batas server.</p>
                        <div id="geojson-upload-progress" class="mt-3 hidden" aria-live="polite">
                            <div class="flex items-center justify-between gap-3 text-xs font-semibold text-slate-600">
                                <span id="geojson-upload-status">Menyiapkan upload...</span>
                                <span id="geojson-upload-percent">0%</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden bg-slate-100">
                                <div id="geojson-upload-bar" class="h-full w-0 bg-red-600 transition-[width] duration-200"></div>
                            </div>
                            <p class="mt-2 text-[11px] text-slate-500">Jangan tutup halaman sampai proses selesai.</p>
                        </div>
                        <p id="geojson-upload-error" class="mt-2 hidden text-sm font-semibold text-red-600"></p>
                        @error('geojson')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label for="administrative_level" class="block text-sm font-semibold text-slate-700">Tingkat wilayah</label>
                            <select id="administrative_level" name="administrative_level" required class="mt-2 w-full border border-slate-300 bg-white px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                                <option value="province" @selected(old('administrative_level') === 'province')>Provinsi</option>
                                <option value="regency" @selected(old('administrative_level') === 'regency')>Kabupaten/Kota</option>
                                <option value="district" @selected(old('administrative_level') === 'district')>Kecamatan</option>
                                <option value="village" @selected(old('administrative_level') === 'village')>Desa</option>
                                <option value="other" @selected(old('administrative_level', 'other') === 'other')>Lainnya</option>
                            </select>
                            @error('administrative_level')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="min_zoom" class="block text-sm font-semibold text-slate-700">Zoom minimum</label>
                            <input id="min_zoom" name="min_zoom" type="number" min="0" max="22" value="{{ old('min_zoom', 6) }}" required class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                            @error('min_zoom')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="max_zoom" class="block text-sm font-semibold text-slate-700">Zoom maksimum</label>
                            <input id="max_zoom" name="max_zoom" type="number" min="0" max="22" value="{{ old('max_zoom', 13) }}" required class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                            @error('max_zoom')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <label class="flex items-center gap-3 border border-slate-200 p-3 text-sm font-semibold text-slate-700">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} class="border-slate-300 text-red-600 focus:ring-red-500">
                        Aktifkan layer PMTiles
                    </label>
                    <p class="text-xs leading-5 text-slate-500">GeoJSON tetap dapat diunggah sebagai arsip, tetapi tidak diaktifkan pada peta karena terlalu berat.</p>
                </div>

                <button id="geojson-upload-submit" type="submit" class="mt-6 w-full border border-red-600 bg-red-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-red-500 disabled:cursor-wait disabled:opacity-60">Simpan layer</button>
            </form>

            <div>
                <div class="mb-4 flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Layer tersimpan</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ $layers->count() }} layer wilayah</p>
                    </div>
                    <span class="bg-slate-900 px-3 py-1.5 text-xs font-bold text-white">{{ $layers->where('is_active', true)->count() }} aktif</span>
                </div>

                <div class="space-y-3">
                    @forelse ($layers as $layer)
                        @php($geojsonUrl = route('map-layers.show', $layer->id))
                        <article class="bg-white p-5 shadow-sm ring-1 ring-slate-200">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="font-bold text-slate-950">{{ $layer->name }}</h3>
                                        <span class="px-2 py-1 text-[10px] font-bold uppercase {{ $layer->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $layer->is_active ? 'Aktif' : ($layer->file_format === 'geojson' ? 'Arsip' : 'Nonaktif') }}</span>
                                    </div>
                                    @if ($layer->description)<p class="mt-2 text-sm leading-6 text-slate-600">{{ $layer->description }}</p>@endif
                                    <p class="mt-3 text-xs text-slate-500">{{ $layer->original_name }} · {{ number_format($layer->file_size / 1024 / 1024, 2) }} MB · {{ strtoupper($layer->file_format) }} · {{ $layer->geojson_type }}@if ($layer->feature_count !== null) · {{ $layer->feature_count }} feature @endif</p>
                                    <p class="mt-2 text-xs font-semibold text-slate-600">{{ ['province' => 'Provinsi', 'regency' => 'Kabupaten/Kota', 'district' => 'Kecamatan', 'village' => 'Desa', 'other' => 'Lainnya'][$layer->administrative_level] ?? 'Lainnya' }} · Zoom {{ $layer->min_zoom }}–{{ $layer->max_zoom }}</p>
                                </div>
                                <form method="POST" action="{{ route('cms.geojson.destroy', $layer->id) }}" onsubmit="return confirm('Hapus layer GeoJSON ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="border border-red-200 px-3 py-2 text-xs font-bold text-red-600 transition hover:bg-red-50">Hapus</button>
                                </form>
                            </div>

                            @if ($layer->file_format === 'pmtiles')
                                <form method="POST" action="{{ route('cms.geojson.update', $layer->id) }}" class="mt-4 grid gap-3 border-y border-slate-200 py-4 sm:grid-cols-[1fr_150px_100px_100px_auto] sm:items-end">
                                    @csrf
                                    @method('PATCH')
                                    <label class="flex h-10 items-center gap-3 text-sm font-semibold text-slate-700">
                                        <input type="checkbox" name="is_active" value="1" {{ $layer->is_active ? 'checked' : '' }} class="border-slate-300 text-red-600 focus:ring-red-500">
                                        Tampilkan di peta
                                    </label>
                                    <label class="text-xs font-semibold text-slate-600">
                                        Tingkat wilayah
                                        <select name="administrative_level" required class="mt-1 w-full border border-slate-300 bg-white px-3 py-2 text-sm">
                                            <option value="province" @selected($layer->administrative_level === 'province')>Provinsi</option>
                                            <option value="regency" @selected($layer->administrative_level === 'regency')>Kab/Kota</option>
                                            <option value="district" @selected($layer->administrative_level === 'district')>Kecamatan</option>
                                            <option value="village" @selected($layer->administrative_level === 'village')>Desa</option>
                                            <option value="other" @selected($layer->administrative_level === 'other')>Lainnya</option>
                                        </select>
                                    </label>
                                    <label class="text-xs font-semibold text-slate-600">
                                        Zoom minimum
                                        <input name="min_zoom" type="number" min="0" max="22" value="{{ $layer->min_zoom }}" required class="mt-1 w-full border border-slate-300 px-3 py-2 text-sm">
                                    </label>
                                    <label class="text-xs font-semibold text-slate-600">
                                        Zoom maksimum
                                        <input name="max_zoom" type="number" min="0" max="22" value="{{ $layer->max_zoom }}" required class="mt-1 w-full border border-slate-300 px-3 py-2 text-sm">
                                    </label>
                                    <button type="submit" class="h-10 border border-slate-900 bg-slate-900 px-4 text-xs font-bold text-white transition hover:bg-slate-700">Simpan</button>
                                </form>
                            @else
                                <div class="mt-4 border-y border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold leading-5 text-amber-800">
                                    Arsip GeoJSON — konversikan ke PMTiles jika layer ini ingin ditampilkan di peta.
                                </div>
                            @endif

                            <label for="geojson-url-{{ $layer->id }}" class="mt-4 block text-xs font-semibold text-slate-600">URL layer</label>
                            <div class="mt-1 flex gap-2">
                                <input id="geojson-url-{{ $layer->id }}" value="{{ $geojsonUrl }}" readonly class="min-w-0 flex-1 border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                <button type="button" data-copy-url="{{ $geojsonUrl }}" class="shrink-0 border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50">Salin URL</button>
                            </div>
                        </article>
                    @empty
                        <div class="bg-white px-6 py-16 text-center text-sm text-slate-500 shadow-sm ring-1 ring-slate-200">Belum ada layer wilayah.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.getElementById('geojson-upload-form');
        const fileInput = document.getElementById('geojson');
        const tokenInput = document.getElementById('geojson-upload-token');
        const submitButton = document.getElementById('geojson-upload-submit');
        const progress = document.getElementById('geojson-upload-progress');
        const progressBar = document.getElementById('geojson-upload-bar');
        const progressStatus = document.getElementById('geojson-upload-status');
        const progressPercent = document.getElementById('geojson-upload-percent');
        const errorElement = document.getElementById('geojson-upload-error');

        if (!form || !fileInput) return;

        fileInput.addEventListener('change', () => {
            tokenInput.value = '';
            errorElement.classList.add('hidden');
        });

        form.addEventListener('submit', async (event) => {
            const file = fileInput.files?.[0];

            if (!file || tokenInput.value) return;

            event.preventDefault();

            const maxBytes = 700 * 1024 * 1024;
            if (file.size > maxBytes) {
                errorElement.textContent = 'Ukuran file melebihi batas 700 MB.';
                errorElement.classList.remove('hidden');
                return;
            }

            const allowedExtensions = ['pmtiles', 'geojson', 'json'];
            const extension = file.name.split('.').pop()?.toLowerCase();
            if (!allowedExtensions.includes(extension)) {
                errorElement.textContent = 'Gunakan file .pmtiles, .geojson, atau .json.';
                errorElement.classList.remove('hidden');
                return;
            }

            const chunkSize = 512 * 1024;
            const totalChunks = Math.ceil(file.size / chunkSize);
            const uploadId = globalThis.crypto?.randomUUID?.()
                ?? `${Date.now()}-${Math.random().toString(16).slice(2)}-ember-upload`;

            submitButton.disabled = true;
            fileInput.disabled = true;
            progress.classList.remove('hidden');
            errorElement.classList.add('hidden');

            try {
                for (let index = 0; index < totalChunks; index++) {
                    const start = index * chunkSize;
                    const chunk = file.slice(start, Math.min(start + chunkSize, file.size));
                    let uploaded = false;
                    let lastError = 'Upload bagian gagal.';

                    for (let attempt = 1; attempt <= 3 && !uploaded; attempt++) {
                        const body = new FormData();
                        body.append('upload_id', uploadId);
                        body.append('chunk_index', String(index));
                        body.append('total_chunks', String(totalChunks));
                        body.append('original_name', file.name);
                        body.append('chunk', chunk, `${file.name}.part`);

                        try {
                            const response = await fetch(@json(route('cms.geojson.upload-chunk')), {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': @json(csrf_token()),
                                },
                                credentials: 'same-origin',
                                body,
                            });

                            const payload = await response.json().catch(() => ({}));
                            uploaded = response.ok;
                            lastError = payload.message || payload.errors?.chunk?.[0] || `Upload gagal (${response.status}).`;
                        } catch (error) {
                            lastError = error.message || 'Koneksi upload terputus.';
                        }

                        if (!uploaded && attempt < 3) {
                            progressStatus.textContent = `Mengulang bagian ${index + 1} (percobaan ${attempt + 1}/3)...`;
                            await new Promise((resolve) => setTimeout(resolve, attempt * 500));
                        }
                    }

                    if (!uploaded) {
                        throw new Error(lastError);
                    }

                    const percent = Math.round(((index + 1) / totalChunks) * 100);
                    progressBar.style.width = `${percent}%`;
                    progressPercent.textContent = `${percent}%`;
                    progressStatus.textContent = `Mengunggah bagian ${index + 1} dari ${totalChunks}`;
                }

                tokenInput.value = uploadId;
                progressStatus.textContent = 'Upload selesai, menyimpan layer...';
                form.submit();
            } catch (error) {
                fileInput.disabled = false;
                submitButton.disabled = false;
                errorElement.textContent = error.message || 'Upload gagal. Periksa koneksi lalu coba kembali.';
                errorElement.classList.remove('hidden');
                progressStatus.textContent = 'Upload terhenti';
            }
        });
    })();
</script>
@endpush
