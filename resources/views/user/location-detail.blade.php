@extends('layouts.user')

@section('title', ($language === 'en' ? 'Location Details' : 'Detail Lokasi') . ' - EMBER')

@section('content')
    @php
        $locationName = $location->desa ?: ($language === 'en' ? 'Village not available' : 'Desa belum tersedia');
        $region = collect([
            $location->kecamatan,
            $location->kabupaten_kota,
            $location->provinsi
        ])->filter()->join(', ');

        /*
         * Status tampilan mengikuti bahasa aktif.
         * Nilai database tetap tidak diubah.
         */
        $statusLabel = match ($status) {
            'Tinggi', 'High' => $language === 'en' ? 'High' : 'Tinggi',
            'Sedang', 'Medium', 'Moderate' => $language === 'en' ? 'Moderate' : 'Sedang',
            'Rendah', 'Low' => $language === 'en' ? 'Low' : 'Rendah',
            default => $language === 'en' ? 'Unrated' : 'Belum dinilai',
        };

        $statusClass = match ($status) {
            'Tinggi', 'High' => 'bg-red-500/15 text-red-300 ring-red-400/30',
            'Sedang', 'Medium', 'Moderate' => 'bg-amber-500/15 text-amber-300 ring-amber-400/30',
            'Rendah', 'Low' => 'bg-emerald-500/15 text-emerald-300 ring-emerald-400/30',
            default => 'bg-slate-500/20 text-slate-300 ring-slate-400/30',
        };

        /*
         * Terjemahan nama tutupan lahan.
         * value database tetap tidak diubah.
         */
        $landCoverLabel = match ($location->land_cover) {
            'Formasi Hutan' => $language === 'en' ? 'Forest Formation' : 'Formasi Hutan',
            'Hutan Rawa Gambut' => $language === 'en' ? 'Peat Swamp Forest' : 'Hutan Rawa Gambut',
            'Kebun Kayu' => $language === 'en' ? 'Wood Plantation' : 'Kebun Kayu',
            'Lubang Tambang' => $language === 'en' ? 'Mining Pits' : 'Lubang Tambang',
            'Mangrove' => 'Mangrove',
            'Non-Vegetasi Lainnya' => $language === 'en' ? 'Other Non-Vegetated Areas' : 'Non-Vegetasi Lainnya',
            'Pemukiman' => $language === 'en' ? 'Settlement' : 'Pemukiman',
            'Pertanian Lainnya' => $language === 'en' ? 'Other Agriculture' : 'Pertanian Lainnya',
            'Sawah' => $language === 'en' ? 'Rice Field' : 'Sawah',
            'Sawit' => $language === 'en' ? 'Oil Palm' : 'Sawit',
            'Sungai, Danau, Laut' => $language === 'en' ? 'River, Lake, Sea' : 'Sungai, Danau, Laut',
            'Tambak' => $language === 'en' ? 'Pond' : 'Tambak',
            'Tumbuhan Non-Hutan Lainnya' => $language === 'en' ? 'Other Non-Forest Vegetation' : 'Tumbuhan Non-Hutan Lainnya',
            default => $location->land_cover ?: '-',
        };

        /*
         * Terjemahan kelas FSI.
         */
        $fsiClassLabel = match ($location->fsi_class) {
            'Very Low' => $language === 'en' ? 'Very Low' : 'Sangat Rendah',
            'Low' => $language === 'en' ? 'Low' : 'Rendah',
            'Moderate' => $language === 'en' ? 'Moderate' : 'Sedang',
            'High' => $language === 'en' ? 'High' : 'Tinggi',
            'Very High' => $language === 'en' ? 'Very High' : 'Sangat Tinggi',
            'Sangat Rendah' => $language === 'en' ? 'Very Low' : 'Sangat Rendah',
            'Rendah' => $language === 'en' ? 'Low' : 'Rendah',
            'Sedang' => $language === 'en' ? 'Moderate' : 'Sedang',
            'Tinggi' => $language === 'en' ? 'High' : 'Tinggi',
            'Sangat Tinggi' => $language === 'en' ? 'Very High' : 'Sangat Tinggi',
            default => $language === 'en' ? 'Not assessed' : 'Belum dinilai',
        };
    @endphp

    <section class="relative overflow-hidden bg-slate-950 px-4 py-12 text-white sm:px-6 lg:px-8 lg:py-16">
        <div
            class="absolute inset-0 opacity-20"
            aria-hidden="true"
            style="background-image: linear-gradient(rgba(255,255,255,.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.1) 1px, transparent 1px); background-size: 48px 48px;"
        ></div>

        <div class="relative mx-auto max-w-7xl">
            <a
                href="{{ route('user.map', ['lang' => $language]) }}"
                class="inline-flex items-center gap-2 text-sm font-semibold text-slate-300 transition hover:text-white"
            >
                <span aria-hidden="true">&larr;</span>
                {{ $language === 'en' ? 'Back to map' : 'Kembali ke peta' }}
            </a>

            <div class="mt-10 flex flex-col gap-7 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-red-400">
                            {{ $language === 'en' ? 'Monitoring location' : 'Lokasi pemantauan' }} #{{ $location->id }}
                        </p>

                        <span class="inline-flex px-3 py-1 text-xs font-bold ring-1 {{ $statusClass }}">
                            {{ $statusLabel }}
                        </span>
                    </div>

                    <h1 class="mt-4 max-w-4xl text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                        {{ $locationName }}
                    </h1>

                    <p class="mt-4 max-w-3xl text-base text-slate-300">
                        {{ $region ?: ($language === 'en'
                            ? 'Administrative area information has not been completed.'
                            : 'Informasi wilayah administratif belum dilengkapi.') }}
                    </p>
                </div>

                <div class="grid w-full grid-cols-2 gap-px bg-white/15 sm:w-auto sm:min-w-80">
                    <div class="bg-slate-900/90 p-5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            {{ $language === 'en' ? 'NASA Confidence' : 'Confidence NASA' }}
                        </p>
                        <p class="mt-2 text-xl font-bold">
                            {{ $location->confidence ?? '-' }}
                        </p>
                    </div>

                    <div class="bg-slate-900/90 p-5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            {{ $language === 'en' ? 'Observation date' : 'Tanggal observasi' }}
                        </p>
                        <p class="mt-2 text-base font-bold">
                            {{ $location->date ? \Illuminate\Support\Carbon::parse($location->date)->format('d M Y') : '-' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
        <div class="grid gap-6 lg:grid-cols-[1.35fr_.65fr]">
            <div class="space-y-6">

                <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-red-600">
                            {{ $language === 'en' ? 'Point position' : 'Posisi titik' }}
                        </p>

                        <h2 class="mt-1 text-xl font-bold text-slate-950">
                            {{ $language === 'en' ? 'Location map' : 'Peta lokasi' }}
                        </h2>
                    </div>

                    <div
                        id="location-detail-map"
                        class="h-[360px] w-full bg-slate-100"
                        aria-label="{{ $language === 'en' ? 'Location point map' : 'Peta titik lokasi' }}"
                    ></div>

                    <script id="location-detail-map-data" type="application/json">
                        {!! json_encode([
                            'latitude' => $location->latitude,
                            'longitude' => $location->longitude,
                            'confidence' => $location->confidence,
                            'fsi_score' => $location->fsi_score ?? null,
                            'hotspot_evidence' => $location->hotspot_evidence ?? null,
                            'historical_burn_evidence' => $location->historical_burn_evidence ?? null,
                            'model_version' => $location->model_version ?? null,
                            'language' => $language
                        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
                    </script>
                </div>

                @if(isset($location->fsi_score))
                    <div class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-sm">
                        <div class="p-6">
                            <p class="text-xs font-bold uppercase tracking-wider text-red-400">
                                {{ $language === 'en' ? 'Fire Susceptibility Index' : 'Indeks Kerawanan Hotspot' }}
                            </p>

                            <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <p class="text-4xl font-black">
                                        {{ number_format((float) $location->fsi_score, 1) }}
                                        <span class="text-lg font-bold text-slate-400">/ 100</span>
                                    </p>

                                    <p class="mt-1 text-sm font-bold text-slate-300">
                                        {{ $fsiClassLabel }}
                                    </p>
                                </div>

                                <div class="max-w-md text-sm leading-6 text-slate-300">
                                    {{ $language === 'en'
                                        ? 'Experimental class-level relative susceptibility index combining hotspot evidence and historical burned-area evidence. It is not a fire probability or a claim that every hotspot becomes a fire.'
                                        : 'Indeks kerawanan relatif tingkat kelas yang masih eksperimental, menggabungkan evidence hotspot dan evidence historis area terbakar. Nilai ini bukan probabilitas kebakaran dan tidak berarti setiap hotspot berkembang menjadi kebakaran.' }}
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-px border-t border-white/10 bg-white/10 sm:grid-cols-3">
                            <div class="bg-slate-900/80 p-4">
                                <p class="text-[10px] uppercase tracking-wider text-slate-500">
                                    {{ $language === 'en' ? 'Land cover' : 'Tutupan lahan' }}
                                </p>

                                <p class="mt-2 text-sm font-bold">
                                    {{ $landCoverLabel }}
                                </p>
                            </div>

                            <div class="bg-slate-900/80 p-4">
                                <p class="text-[10px] uppercase tracking-wider text-slate-500">
                                    {{ $language === 'en' ? 'Hotspot Evidence (HSE)' : 'Evidence Hotspot (HSE)' }}
                                </p>

                                <p class="mt-2 text-sm font-bold">
                                    {{ isset($location->hotspot_evidence) ? number_format((float) $location->hotspot_evidence, 1).' / 100' : '-' }}
                                </p>
                            </div>

                            <div class="bg-slate-900/80 p-4">
                                <p class="text-[10px] uppercase tracking-wider text-slate-500">
                                    {{ $language === 'en' ? 'Historical Burn Evidence (HBE)' : 'Evidence Historis Kebakaran (HBE)' }}
                                </p>

                                <p class="mt-2 text-sm font-bold">
                                    {{ isset($location->historical_burn_evidence) ? number_format((float) $location->historical_burn_evidence, 1).' / 100' : '-' }}
                                </p>
                            </div>

                            <div class="bg-slate-900/80 p-4">
                                <p class="text-[10px] uppercase tracking-wider text-slate-500">
                                    {{ $language === 'en' ? 'Evidence comparison (QA)' : 'Perbandingan evidence (QA)' }}
                                </p>

                                <p class="mt-2 text-sm font-bold">
                                    {{ $location->context_flag ?: '-' }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <p class="text-xs font-bold uppercase tracking-wider text-red-600">
                            {{ $language === 'en' ? 'Administrative information' : 'Informasi administratif' }}
                        </p>

                        <h2 class="mt-1 text-xl font-bold text-slate-950">
                            {{ $language === 'en' ? 'Location hierarchy' : 'Hierarki lokasi' }}
                        </h2>
                    </div>

                    <div class="grid sm:grid-cols-2">
                        @foreach (($language === 'en' ? [
                            'Province' => $location->provinsi,
                            'Regency/City' => $location->kabupaten_kota,
                            'District' => $location->kecamatan,
                            'Village' => $location->desa,
                        ] : [
                            'Provinsi' => $location->provinsi,
                            'Kabupaten/Kota' => $location->kabupaten_kota,
                            'Kecamatan' => $location->kecamatan,
                            'Desa' => $location->desa,
                        ]) as $label => $value)

                            <div class="border-b border-slate-100 p-5 sm:border-r">
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">
                                    {{ $label }}
                                </p>

                                <p class="mt-2 text-base font-semibold text-slate-900">
                                    {{ $value ?: '-' }}
                                </p>
                            </div>

                        @endforeach
                    </div>
                </div>
            </div>

            <aside class="space-y-6">

                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <p class="text-xs font-bold uppercase tracking-wider text-red-600">
                        {{ $language === 'en' ? 'Detection details' : 'Detail deteksi' }}
                    </p>

                    <div class="mt-5 divide-y divide-slate-100">
                        <div class="flex items-center justify-between gap-4 py-4">
                            <span class="text-sm text-slate-500">
                                {{ $language === 'en' ? 'Status' : 'Status' }}
                            </span>

                            <span class="font-bold text-slate-900">
                                {{ $statusLabel }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-4 py-4">
                            <span class="text-sm text-slate-500">
                                {{ $language === 'en' ? 'NASA Confidence' : 'Confidence NASA' }}
                            </span>

                            <span class="font-bold text-slate-900">
                                {{ $location->confidence ?? '-' }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-4 py-4">
                            <span class="text-sm text-slate-500">
                                {{ $language === 'en' ? 'Date' : 'Tanggal' }}
                            </span>

                            <span class="font-bold text-slate-900">
                                {{ $location->date ? \Illuminate\Support\Carbon::parse($location->date)->format('d M Y') : '-' }}
                            </span>
                        </div>
                    </div>
                </div>

                @if(isset($location->land_cover) || isset($location->fsi_score))
                    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                        <p class="text-xs font-bold uppercase tracking-wider text-red-600">
                            {{ $language === 'en' ? 'Land-cover assessment' : 'Analisis tutupan lahan' }}
                        </p>

                        <div class="mt-4 space-y-4">
                            <div>
                                <p class="text-xs text-slate-400">
                                    {{ $language === 'en' ? 'MapBiomas class' : 'Kelas MapBiomas' }}
                                </p>

                                <p class="mt-1 font-bold text-slate-900">
                                    {{ $landCoverLabel }}
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-xl bg-slate-50 p-3">
                                    <p class="text-[10px] uppercase tracking-wider text-slate-400">
                                        {{ $language === 'en' ? 'Hotspot Evidence (HSE)' : 'Evidence Hotspot (HSE)' }}
                                    </p>

                                    <p class="mt-1 font-bold text-slate-900">
                                        {{ isset($location->hotspot_evidence) ? number_format((float) $location->hotspot_evidence, 1) : '-' }}
                                    </p>
                                </div>

                                <div class="rounded-xl bg-slate-50 p-3">
                                    <p class="text-[10px] uppercase tracking-wider text-slate-400">
                                        {{ $language === 'en' ? 'Historical Burn Evidence (HBE)' : 'Evidence Historis Kebakaran (HBE)' }}
                                    </p>

                                    <p class="mt-1 font-bold text-slate-900">
                                        {{ isset($location->historical_burn_evidence) ? number_format((float) $location->historical_burn_evidence, 1) : '-' }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-3 rounded-xl bg-slate-50 p-3">
                                <p class="text-[10px] uppercase tracking-wider text-slate-400">
                                    {{ $language === 'en' ? 'FSI' : 'FSI' }}
                                </p>

                                <p class="mt-1 font-bold text-slate-900">
                                    {{ isset($location->fsi_score) ? number_format((float) $location->fsi_score, 1) : '-' }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-sm">
                    <div class="p-6">
                        <p class="text-xs font-bold uppercase tracking-wider text-red-400">
                            {{ $language === 'en' ? 'Coordinates' : 'Koordinat' }}
                        </p>

                        <p class="mt-4 break-all font-mono text-lg">
                            {{ $location->latitude }}, {{ $location->longitude }}
                        </p>

                        <p class="mt-3 text-xs leading-5 text-slate-400">
                            {{ $language === 'en'
                                ? 'Coordinates use the WGS 84 geographic reference system.'
                                : 'Koordinat menggunakan sistem referensi geografis WGS 84.' }}
                        </p>
                    </div>

                    <a
                        href="https://www.openstreetmap.org/?mlat={{ $location->latitude }}&mlon={{ $location->longitude }}#map=12/{{ $location->latitude }}/{{ $location->longitude }}"
                        target="_blank"
                        rel="noopener"
                        class="flex items-center justify-between border-t border-white/10 px-6 py-4 text-sm font-bold transition hover:bg-white/10"
                    >
                        {{ $language === 'en' ? 'Open in OpenStreetMap' : 'Buka di OpenStreetMap' }}
                        <span aria-hidden="true">&nearr;</span>
                    </a>
                </div>

                <a
                    href="{{ route('user.map', ['lang' => $language]) }}"
                    class="flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700"
                >
                    <span aria-hidden="true">&larr;</span>
                    {{ $language === 'en' ? 'View all locations' : 'Lihat semua lokasi' }}
                </a>

            </aside>
        </div>
    </section>
@endsection