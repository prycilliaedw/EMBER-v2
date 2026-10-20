@extends('layouts.admin')

@section('title', 'Edit Titik Lokasi - EMBER CMS')

@section('content')
    <div class="mx-auto max-w-5xl">
        <a href="{{ route('cms.locations.show', $location->id) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-red-600">&larr; Kembali ke detail</a>

        <div class="mt-5">
            <p class="text-sm font-semibold uppercase tracking-wider text-red-600">Titik lokasi #{{ $location->id }}</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">Edit Detail Lokasi</h1>
            <p class="mt-2 text-sm text-slate-600">Lengkapi informasi wilayah, koordinat, tanggal, dan confidence.</p>
        </div>

        <form method="POST" action="{{ route('cms.locations.update', $location->id) }}" class="mt-6 bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
            @csrf
            @method('PUT')

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="provinsi" class="block text-sm font-semibold text-slate-700">Provinsi</label>
                    <input id="provinsi" name="provinsi" value="{{ old('provinsi', $location->provinsi) }}" class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                    @error('provinsi')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="kabupaten_kota" class="block text-sm font-semibold text-slate-700">Kabupaten/Kota</label>
                    <input id="kabupaten_kota" name="kabupaten_kota" value="{{ old('kabupaten_kota', $location->kabupaten_kota) }}" class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                    @error('kabupaten_kota')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="kecamatan" class="block text-sm font-semibold text-slate-700">Kecamatan</label>
                    <input id="kecamatan" name="kecamatan" value="{{ old('kecamatan', $location->kecamatan) }}" class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                    @error('kecamatan')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="desa" class="block text-sm font-semibold text-slate-700">Desa</label>
                    <input id="desa" name="desa" value="{{ old('desa', $location->desa) }}" class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                    @error('desa')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="latitude" class="block text-sm font-semibold text-slate-700">Latitude</label>
                    <input id="latitude" name="latitude" type="number" step="0.00000001" min="-90" max="90" value="{{ old('latitude', $location->latitude) }}" required class="mt-2 w-full border border-slate-300 px-4 py-3 font-mono text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                    @error('latitude')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="longitude" class="block text-sm font-semibold text-slate-700">Longitude</label>
                    <input id="longitude" name="longitude" type="number" step="0.00000001" min="-180" max="180" value="{{ old('longitude', $location->longitude) }}" required class="mt-2 w-full border border-slate-300 px-4 py-3 font-mono text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                    @error('longitude')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="date" class="block text-sm font-semibold text-slate-700">Tanggal</label>
                    <input id="date" name="date" type="date" value="{{ old('date', $location->date) }}" class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                    @error('date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="confidence" class="block text-sm font-semibold text-slate-700">Confidence</label>
                    <input id="confidence" name="confidence" value="{{ old('confidence', $location->confidence) }}" placeholder="Contoh: high, nominal, low, atau 85" class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                    <p class="mt-2 text-xs leading-5 text-slate-500">Status dibuat otomatis: high/tinggi atau ≥80 = Tinggi; nominal/sedang atau ≥50 = Sedang; low/rendah atau &lt;50 = Rendah.</p>
                    @error('confidence')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-7 flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-6">
                <a href="{{ route('cms.locations.show', $location->id) }}" class="border border-slate-300 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Batal</a>
                <button type="submit" class="border border-red-600 bg-red-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-500">Simpan Perubahan</button>
            </div>
        </form>
    </div>
@endsection
