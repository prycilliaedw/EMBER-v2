@extends('layouts.user')

@section('title', ($language === 'en' ? 'Data Download' : 'Unduh Data').' - EMBER')

@section('content')
<section class="bg-slate-950 px-4 py-14 text-white sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl">
        <p class="text-xs font-black uppercase tracking-[0.24em] text-red-400">EMBER DATA</p>
        <h1 class="mt-4 text-4xl font-black tracking-tight sm:text-5xl">{{ $language === 'en' ? 'Download Location Data' : 'Unduh Data Titik Lokasi' }}</h1>
        <p class="mt-4 max-w-2xl leading-7 text-slate-300">{{ $language === 'en' ? 'Choose all data, a specific year, or a province. Files are provided in CSV format.' : 'Pilih seluruh data, tahun tertentu, atau provinsi. File tersedia dalam format CSV.' }}</p>
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="grid gap-5 lg:grid-cols-3">
        <article class="flex flex-col border border-slate-200 bg-white p-6 shadow-sm">
            <span class="flex size-11 items-center justify-center bg-slate-950 text-sm font-black text-white">01</span>
            <h2 class="mt-5 text-xl font-black text-slate-950">{{ $language === 'en' ? 'All data' : 'Semua data' }}</h2>
            <p class="mt-2 flex-1 text-sm leading-6 text-slate-500">{{ $language === 'en' ? 'Download every recorded EMBER location.' : 'Unduh seluruh titik lokasi EMBER yang tersimpan.' }}</p>
            <a href="{{ route('user.data.download', ['scope' => 'all']) }}" class="mt-6 flex items-center justify-center bg-red-700 px-4 py-3 text-sm font-black text-white hover:bg-red-800">{{ $language === 'en' ? 'Download all CSV' : 'Unduh semua CSV' }}</a>
        </article>

        <article class="border border-slate-200 bg-white p-6 shadow-sm">
            <span class="flex size-11 items-center justify-center bg-amber-700 text-sm font-black text-white">02</span>
            <h2 class="mt-5 text-xl font-black text-slate-950">{{ $language === 'en' ? 'By year' : 'Per tahun' }}</h2>
            <p class="mt-2 text-sm leading-6 text-slate-500">{{ $language === 'en' ? 'Select one recorded year.' : 'Pilih satu tahun yang tersedia.' }}</p>
            <form method="GET" action="{{ route('user.data.download') }}" class="mt-6">
                <input type="hidden" name="scope" value="year">
                <label for="data-year" class="text-xs font-black uppercase tracking-wider text-slate-500">{{ $language === 'en' ? 'Year' : 'Tahun' }}</label>
                <select id="data-year" name="year" required class="mt-2 w-full border border-slate-300 bg-white px-3 py-3 text-sm font-bold text-slate-800 outline-none focus:border-red-600">
                    @foreach ($years as $year)<option value="{{ $year }}">{{ $year }}</option>@endforeach
                </select>
                <button class="mt-4 w-full bg-slate-950 px-4 py-3 text-sm font-black text-white hover:bg-red-700">{{ $language === 'en' ? 'Download yearly CSV' : 'Unduh CSV tahunan' }}</button>
            </form>
        </article>

        <article class="border border-slate-200 bg-white p-6 shadow-sm">
            <span class="flex size-11 items-center justify-center bg-emerald-800 text-sm font-black text-white">03</span>
            <h2 class="mt-5 text-xl font-black text-slate-950">{{ $language === 'en' ? 'By province' : 'Per provinsi' }}</h2>
            <p class="mt-2 text-sm leading-6 text-slate-500">{{ $language === 'en' ? 'Select one province from the available data.' : 'Pilih satu provinsi dari data yang tersedia.' }}</p>
            <form method="GET" action="{{ route('user.data.download') }}" class="mt-6">
                <input type="hidden" name="scope" value="province">
                <label for="data-province" class="text-xs font-black uppercase tracking-wider text-slate-500">{{ $language === 'en' ? 'Province' : 'Provinsi' }}</label>
                <select id="data-province" name="province" required class="mt-2 w-full border border-slate-300 bg-white px-3 py-3 text-sm font-bold text-slate-800 outline-none focus:border-red-600">
                    @foreach ($provinces as $province)<option value="{{ $province }}">{{ $province }}</option>@endforeach
                </select>
                <button class="mt-4 w-full bg-slate-950 px-4 py-3 text-sm font-black text-white hover:bg-red-700">{{ $language === 'en' ? 'Download province CSV' : 'Unduh CSV provinsi' }}</button>
            </form>
        </article>
    </div>

    <div class="mt-8 border border-slate-200 bg-slate-50 p-5 text-sm leading-6 text-slate-600">
        <strong class="text-slate-950">{{ $language === 'en' ? 'CSV columns:' : 'Kolom CSV:' }}</strong>
        provinsi, kabupaten_kota, kecamatan, desa, latitude, longitude, date, confidence.
    </div>
</section>
@endsection
