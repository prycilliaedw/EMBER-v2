@extends('layouts.admin')

@section('title', 'Tambah Titik Lokasi - EMBER CMS')

@section('content')
    <div class="mx-auto max-w-5xl">
        <a href="{{ route('cms.locations.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-red-600">&larr; Kembali ke daftar</a>

        <div class="mt-5">
            <p class="text-sm font-semibold uppercase tracking-wider text-red-600">Data pemantauan</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">Tambah Titik Lokasi</h1>
            <p class="mt-2 text-sm text-slate-600">Masukkan informasi wilayah, koordinat, tanggal, dan confidence lokasi baru.</p>
        </div>

        <form method="POST" action="{{ route('cms.locations.store') }}" class="mt-6 bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
            @csrf

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="provinsi" class="block text-sm font-semibold text-slate-700">Provinsi</label>
                    <input id="provinsi" name="provinsi" value="{{ old('provinsi') }}" class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                    @error('provinsi')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="kabupaten_kota" class="block text-sm font-semibold text-slate-700">Kabupaten/Kota</label>
                    <input id="kabupaten_kota" name="kabupaten_kota" value="{{ old('kabupaten_kota') }}" class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                    @error('kabupaten_kota')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="kecamatan" class="block text-sm font-semibold text-slate-700">Kecamatan</label>
                    <input id="kecamatan" name="kecamatan" value="{{ old('kecamatan') }}" class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                    @error('kecamatan')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="desa" class="block text-sm font-semibold text-slate-700">Desa</label>
                    <input id="desa" name="desa" value="{{ old('desa') }}" class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                    @error('desa')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="latitude" class="block text-sm font-semibold text-slate-700">Latitude <span class="text-red-600">*</span></label>
                    <input id="latitude" name="latitude" type="number" step="0.00000001" min="-90" max="90" value="{{ old('latitude') }}" placeholder="Contoh: -2.92494000" required class="mt-2 w-full border border-slate-300 px-4 py-3 font-mono text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                    @error('latitude')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="longitude" class="block text-sm font-semibold text-slate-700">Longitude <span class="text-red-600">*</span></label>
                    <input id="longitude" name="longitude" type="number" step="0.00000001" min="-180" max="180" value="{{ old('longitude') }}" placeholder="Contoh: 104.68752000" required class="mt-2 w-full border border-slate-300 px-4 py-3 font-mono text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                    @error('longitude')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="date" class="block text-sm font-semibold text-slate-700">Tanggal</label>
                    <input id="date" name="date" type="date" value="{{ old('date') }}" class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                    @error('date')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="confidence" class="block text-sm font-semibold text-slate-700">Confidence</label>
                    <input id="confidence" name="confidence" value="{{ old('confidence') }}" placeholder="Contoh: high, nominal, low, atau 85" class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                    <p class="mt-2 text-xs leading-5 text-slate-500">Kosongkan jika belum dinilai. Status akan ditentukan otomatis dari nilai confidence.</p>
                    @error('confidence')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-7 flex flex-wrap justify-end gap-3 border-t border-slate-200 pt-6">
                <a href="{{ route('cms.locations.index') }}" class="border border-slate-300 px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50">Batal</a>
                <button type="submit" class="border border-red-600 bg-red-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-500">Simpan Titik Lokasi</button>
            </div>
        </form>
    </div>
@endsection
