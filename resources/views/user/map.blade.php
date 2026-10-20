@extends('layouts.user')

@section('title', ($language === 'en' ? 'Interactive Map' : 'Peta Interaktif') . ' - EMBER')
@section('hideFooter', true)

@section('content')
    @php
        $availableYears = $locations
            ->pluck('date')
            ->filter()
            ->map(fn ($date) => (int) substr($date, 0, 4))
            ->unique()
            ->sort()
            ->values();

        $classEvidenceData = collect(config('ember.class_evidence', []))->map(function ($evidence, $landCoverId) use ($locations, $language) {
            $landCoverId = (int) $landCoverId;
            $targetLocations = $locations->filter(function ($location) use ($landCoverId, $evidence) {
                if (isset($location->land_cover_id) && $location->land_cover_id !== null) {
                    return (int) $location->land_cover_id === $landCoverId;
                }

                return trim((string) ($location->land_cover ?? '')) === trim((string) ($evidence['name'] ?? ''));
            });

            $confidenceCounts = ['low' => 0, 'nominal' => 0, 'high' => 0, 'unrated' => 0];
            foreach ($targetLocations as $location) {
                $rawConfidence = $location->confidence ?? null;
                if (! is_numeric($rawConfidence) && trim((string) $rawConfidence) === '') {
                    $confidenceCounts['unrated'] += 1;
                    continue;
                }

                $value = is_numeric($rawConfidence) ? (float) $rawConfidence : null;
                if ($value === null) {
                    continue;
                }

                $key = $value < 30 ? 'low' : ($value < 80 ? 'nominal' : 'high');
                $confidenceCounts[$key] += 1;
            }

            $total = $targetLocations->count();
            $hse = isset($evidence['hotspot_evidence']) ? (float) $evidence['hotspot_evidence'] : 0;
            $hbe = isset($evidence['historical_burn_evidence']) ? (float) $evidence['historical_burn_evidence'] : 0;
            $fsi = app(App\Services\FireSusceptibilityService::class)->fsi($hse, $hbe);

            return [
                'id' => $landCoverId,
                'name' => $evidence['name'] ?? 'Land cover',
                'display_name' => match ($evidence['name'] ?? '') {
                    'Formasi Hutan' => $language === 'en' ? 'Forest Formation' : 'Formasi Hutan',
                    'Hutan Rawa Gambut' => $language === 'en' ? 'Peat Swamp Forest' : 'Hutan Rawa Gambut',
                    'Kebun Kayu' => $language === 'en' ? 'Wood Plantation' : 'Kebun Kayu',
                    'Lubang Tambang' => $language === 'en' ? 'Mining Pits' : 'Lubang Tambang',
                    'Non-Vegetasi Lainnya' => $language === 'en' ? 'Other Non-Vegetated Areas' : 'Non-Vegetasi Lainnya',
                    'Pemukiman' => $language === 'en' ? 'Settlement' : 'Pemukiman',
                    'Pertanian Lainnya' => $language === 'en' ? 'Other Agriculture' : 'Pertanian Lainnya',
                    'Sawah' => $language === 'en' ? 'Rice Field' : 'Sawah',
                    'Sawit' => $language === 'en' ? 'Oil Palm' : 'Sawit',
                    'Sungai, Danau, Laut' => $language === 'en' ? 'River, Lake, Sea' : 'Sungai, Danau, Laut',
                    'Tambak' => $language === 'en' ? 'Pond' : 'Tambak',
                    'Tumbuhan Non-Hutan Lainnya' => $language === 'en' ? 'Other Non-Forest Vegetation' : 'Tumbuhan Non-Hutan Lainnya',
                    default => $evidence['name'] ?? 'Land cover',
                },
                'area_km2' => isset($evidence['area_km2']) ? (float) $evidence['area_km2'] : null,
                'count' => $total,
                'total' => $total,
                'confidence' => $confidenceCounts,
                'confidence_percentages' => [
                    'low' => $total > 0 ? round(($confidenceCounts['low'] / $total) * 100, 1) : 0,
                    'nominal' => $total > 0 ? round(($confidenceCounts['nominal'] / $total) * 100, 1) : 0,
                    'high' => $total > 0 ? round(($confidenceCounts['high'] / $total) * 100, 1) : 0,
                    'unrated' => $total > 0 ? round(($confidenceCounts['unrated'] / $total) * 100, 1) : 0,
                ],
                'hse' => $hse,
                'hbe' => $hbe,
                'fsi' => $fsi,
            ];
        })->values();

        $overallHotspotTotal = $classEvidenceData->sum('count');
        $classDistribution = $classEvidenceData->map(function ($entry) use ($overallHotspotTotal) {
            $share = $overallHotspotTotal > 0 ? round(($entry['count'] / $overallHotspotTotal) * 100, 2) : 0;

            return [
                'id' => $entry['id'],
                'name' => $entry['name'],
                'display_name' => $entry['display_name'],
                'count' => $entry['count'],
                'share' => $share,
                'color' => match ($entry['name']) {
                    'Formasi Hutan' => '#16a34a',
                    'Mangrove' => '#0f766e',
                    'Kebun Kayu' => '#84cc16',
                    'Tumbuhan Non-Hutan Lainnya' => '#a3e635',
                    'Pertanian Lainnya' => '#f59e0b',
                    'Pemukiman' => '#f97316',
                    'Non-Vegetasi Lainnya' => '#b45309',
                    'Lubang Tambang' => '#374151',
                    'Tambak' => '#38bdf8',
                    'Sungai, Danau, Laut' => '#60a5fa',
                    'Sawit' => '#22c55e',
                    'Sawah' => '#facc15',
                    'Hutan Rawa Gambut' => '#14532d',
                    default => '#e2e8f0',
                },
            ];
        })->values();

        $donutGradient = 'conic-gradient(' . collect($classDistribution)->map(function ($item, $index) use ($classDistribution) {
            $previous = collect($classDistribution)->take($index)->sum('share');

            return sprintf('%s %s%% %s%%', $item['color'], $previous, $previous + $item['share']);
        })->implode(', ') . ')';

        $confidenceSummary = $locations
            ->filter(fn ($location) => is_numeric($location->confidence ?? null) || trim((string) ($location->confidence ?? '')) === '')
            ->map(function ($location) {
                $confidence = $location->confidence ?? null;

                if (! is_numeric($confidence)) {
                    return 'unrated';
                }

                $value = (float) $confidence;

                return $value < 30 ? 'low' : ($value < 80 ? 'nominal' : 'high');
            })->countBy()->all();
        $confidenceTotal = max(1, array_sum($confidenceSummary));
        $confidenceDistribution = collect(['low', 'nominal', 'high', 'unrated'])->mapWithKeys(function ($key) use ($confidenceSummary, $confidenceTotal) {
            $count = $confidenceSummary[$key] ?? 0;

            return [$key => [
                'count' => $count,
                'share' => $confidenceTotal > 0 ? round(($count / $confidenceTotal) * 100, 1) : 0,
            ]];
        })->all();

        $classEvidenceJson = $classEvidenceData->keyBy('id')->all();
        $availableYears = $mapDashboard['years']->sort()->values();
        $classEvidenceData = $mapDashboard['classes'];
        $classDistribution = $classEvidenceData;
        $classEvidenceJson = $classEvidenceData->keyBy('id')->all();
    @endphp

    <section class="relative h-[calc(100vh-6.5rem)] min-h-150 w-full overflow-hidden bg-slate-100">
        <div class="absolute left-16 top-3 z-500 max-w-[calc(100%-5rem)] bg-white/95 px-4 py-3 shadow-xl ring-1 ring-slate-200 backdrop-blur sm:left-20 sm:top-5 sm:max-w-[calc(100%-6.25rem)] sm:px-5 sm:py-4">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-red-600">{{ $language === 'en' ? 'Interactive map' : 'Peta interaktif' }}</p>
            <h1 class="mt-1 text-lg font-bold tracking-tight text-slate-950 sm:text-xl">{{ $language === 'en' ? 'EMBER location distribution' : 'Persebaran lokasi EMBER' }}</h1>
        </div>

        <div id="map-drilldown-control" class="absolute right-3 top-24 z-500 w-[min(330px,calc(100%-1.5rem))] bg-white/95 p-4 shadow-xl ring-1 ring-slate-200 backdrop-blur sm:right-5 sm:top-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p id="map-boundary-level" class="text-[10px] font-bold uppercase tracking-[0.16em] text-red-600">{{ $language === 'en' ? 'Province' : 'Provinsi' }}</p>
                    <p id="map-boundary-breadcrumb" class="mt-1 text-sm font-bold text-slate-950">Sumatera</p>
                    <p id="map-boundary-instruction" class="mt-1 text-xs leading-5 text-slate-500">{{ $language === 'en' ? 'Click a province to view regencies/cities.' : 'Klik provinsi untuk melihat kabupaten/kota.' }}</p>
                </div>
                <button id="map-boundary-reset" type="button" class="hidden shrink-0 border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700">Reset</button>
            </div>
        </div>

        <section id="map-statistics-panel" data-hotspot-total="{{ $mapDashboard['total'] }}" data-classified-total="{{ $mapDashboard['mapped_total'] }}" class="absolute right-3 top-56 z-500 max-h-[calc(100%-15rem)] w-[min(320px,calc(100%-1.5rem))] overflow-y-auto bg-white/95 p-3 shadow-xl ring-1 ring-slate-200 backdrop-blur sm:right-5 sm:top-40 sm:max-h-[calc(100%-11rem)]" aria-label="{{ $language === 'en' ? 'Map statistics' : 'Statistik peta' }}">
            <div class="flex items-start justify-between gap-3 border-b border-slate-200 pb-3">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.18em] text-red-700">{{ $language === 'en' ? 'Statistics dashboard · Status statistics' : 'Dasbor statistik · Statistik status' }}</p>
                    <h2 id="map-statistics-region" class="mt-1 text-base font-black text-slate-950">Sumatera</h2>
                </div>
                <span id="map-statistics-period" class="bg-slate-100 px-2 py-1 text-[9px] font-black text-slate-500">{{ $language === 'en' ? 'All years' : 'Semua tahun' }}</span>
            </div>

            <section id="map-confidence-statistics" class="border-b border-slate-200 py-3">
                <h3 class="text-[9px] font-black uppercase tracking-[0.16em] text-red-700">{{ $language === 'en' ? '1. NASA confidence distribution' : '1. Distribusi confidence NASA' }}</h3>
                <p class="mt-1 text-[9px] leading-4 text-slate-500">{{ $language === 'en' ? 'Hotspot confidence categories for the currently filtered locations.' : 'Kategori confidence hotspot pada lokasi yang sesuai filter saat ini.' }}</p>
            <div class="grid grid-cols-[112px_1fr] items-center gap-4 pt-3">
                <div class="relative mx-auto size-28">
                    <div id="map-yearly-donut" class="absolute inset-0 rounded-full bg-slate-200" role="img" aria-label="{{ $language === 'en' ? 'Yearly status donut chart' : 'Grafik donat status tahunan' }}"></div>
                    <div class="absolute inset-[24%] flex flex-col items-center justify-center rounded-full bg-white shadow-inner">
                        <strong id="map-yearly-total" class="text-xl font-black text-slate-950">0</strong>
                        <span class="text-[8px] font-black uppercase tracking-wider text-slate-400">{{ $language === 'en' ? 'locations' : 'lokasi' }}</span>
                    </div>
                </div>

                <div class="space-y-2">
                    @foreach ([
                        ['high', '#b91c1c', $language === 'en' ? 'High' : 'Tinggi'],
                        ['nominal', '#d97706', 'Nominal'],
                        ['low', '#047857', $language === 'en' ? 'Low' : 'Rendah'],
                        ['unrated', '#334155', $language === 'en' ? 'Unrated' : 'Belum dinilai'],
                    ] as [$key, $color, $label])
                        <div class="flex items-center gap-2 text-[10px]">
                            <span class="size-2 shrink-0 rounded-full" style="background: {{ $color }}"></span>
                            <span class="min-w-0 flex-1 truncate font-bold text-slate-600">{{ $label }}</span>
                            <strong data-map-yearly-count="{{ $key }}" class="font-black text-slate-950">0</strong>
                        </div>
                    @endforeach
                </div>
            </div>
            </section>

            <div id="map-monthly-statistics" class="hidden border-t border-slate-200 pt-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[0.16em] text-slate-400">{{ $language === 'en' ? 'Monthly status' : 'Status per bulan' }}</p>
                        <p id="map-monthly-title" class="mt-1 text-xs font-black text-slate-800">-</p>
                    </div>
                    <span id="map-monthly-total" class="text-xs font-black text-red-700">0</span>
                </div>
                <div class="mt-4 grid h-28 grid-cols-12 items-end gap-1 border-b border-slate-200 px-1" aria-label="{{ $language === 'en' ? 'Monthly status bar chart' : 'Grafik batang status bulanan' }}">
                    @foreach (($language === 'en'
                        ? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
                        : ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']) as $index => $month)
                        <div class="flex h-full min-w-0 flex-col justify-end" title="{{ $month }}">
                            <div data-map-month-bar="{{ $index }}" class="flex min-h-0 w-full flex-col-reverse overflow-hidden bg-slate-100"></div>
                            <span class="mt-1 block truncate text-center text-[7px] font-bold text-slate-400">{{ $month }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-4 border-t border-slate-200 pt-4" id="map-class-statistics" data-class-evidence="{{ json_encode($classEvidenceJson, JSON_HEX_APOS | JSON_HEX_QUOT) }}" data-class-distribution="{{ json_encode($classDistribution, JSON_HEX_APOS | JSON_HEX_QUOT) }}">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 id="map-class-title" class="text-[9px] font-black uppercase tracking-[0.16em] text-red-700">{{ $language === 'en' ? '2. Hotspot distribution by land-cover class' : '2. Distribusi hotspot per kelas tutupan lahan' }}</h3>
                    <div class="flex items-center gap-2">
                        <a data-map-statistics-link href="{{ route('user.statistics', ['lang' => $language]) }}" class="text-[8px] font-bold text-slate-500 hover:text-red-700">{{ $language === 'en' ? 'Full statistics' : 'Statistik lengkap' }}</a>
                        <button id="map-class-clear" type="button" class="hidden text-[9px] font-bold text-red-700">{{ $language === 'en' ? 'Clear Selection' : 'Hapus Pilihan' }}</button>
                    </div>
                </div>
                <p class="mb-3 text-[9px] leading-4 text-slate-500">{{ $language === 'en' ? 'All 13 classes stay in the chart; counts follow the active map filters.' : 'Seluruh 13 kelas tetap ditampilkan; jumlah hotspot mengikuti filter peta aktif.' }}</p>
                <div id="map-selected-class-summary" class="mb-3 hidden border-l-2 border-red-600 bg-red-50 px-3 py-2">
                    <p id="map-selected-class-name" class="text-[10px] font-black text-slate-900"></p>
                    <p id="map-selected-class-area" class="mt-1 text-[9px] text-slate-600"></p>
                </div>
                <div class="grid grid-cols-[88px_1fr] items-center gap-3">
                    <div id="map-class-donut" class="h-22 w-22 rounded-full bg-slate-200" role="img" aria-label="{{ $language === 'en' ? 'Hotspot distribution by land-cover class' : 'Distribusi hotspot per kelas tutupan lahan' }}"></div>
                    <div id="map-class-legend" class="grid max-h-28 grid-cols-1 gap-1 overflow-y-auto text-[9px]"></div>
                </div>
                <div id="map-class-metrics" class="mt-3 grid-cols-3 gap-2 text-center" style="display: none;">
                    <div class="bg-slate-50 p-2"><p class="text-[8px] font-bold text-slate-500">HSE</p><strong id="map-class-hse" class="text-xs">—</strong></div>
                    <div class="bg-slate-50 p-2"><p class="text-[8px] font-bold text-slate-500">HBE</p><strong id="map-class-hbe" class="text-xs">—</strong></div>
                    <div class="bg-slate-50 p-2"><p class="text-[8px] font-bold text-slate-500">FSI</p><strong id="map-class-fsi" class="text-xs">—</strong></div>
                </div>
                <p id="map-overall-metrics-note" class="mt-3 text-[9px] leading-4 text-slate-600">{{ $language === 'en' ? 'Select a class in the chart or land-cover filter to inspect its hotspot count, mapped area, and class scores.' : 'Pilih kelas pada grafik atau filter tutupan lahan untuk melihat jumlah hotspot, luas kelas, dan skornya.' }}</p>
                <p class="mt-2 text-[9px] leading-4 text-slate-500">{{ $language === 'en' ? 'HSE and HBE are class-level evidence scores (0–100). FSI is a relative susceptibility index (0–100), not a fire probability. Scores are not aggregated across classes.' : 'HSE dan HBE adalah skor bukti tingkat kelas (0–100). FSI adalah indeks kerentanan relatif (0–100), bukan probabilitas kebakaran. Skor tidak diagregasi lintas kelas.' }}</p>
                <div id="map-selected-bars" class="mt-3 hidden border-t border-slate-200 pt-3"></div>
            </div>

            @foreach ([
                ['hse', 'HSE', $language === 'en' ? 'Hotspot evidence' : 'Bukti hotspot', '#dc2626', $language === 'en' ? 'Confidence-weighted hotspot evidence by class (0–100).' : 'Bukti hotspot berbobot confidence per kelas (0–100).'],
                ['hbe', 'HBE', $language === 'en' ? 'Historical burn evidence' : 'Bukti kebakaran historis', '#d97706', $language === 'en' ? 'Reconstructed historical burn evidence by class (0–100).' : 'Rekonstruksi bukti kebakaran historis per kelas (0–100).'],
                ['fsi', 'FSI', $language === 'en' ? 'Relative susceptibility index' : 'Indeks kerentanan relatif', '#2563eb', $language === 'en' ? 'Experimental class-context index; not a fire probability (0–100).' : 'Indeks eksperimental berdasarkan konteks kelas; bukan probabilitas kebakaran (0–100).'],
            ] as $index => [$metric, $label, $heading, $color, $description])
                <section class="border-b border-slate-200 py-3" aria-labelledby="map-score-title-{{ $metric }}">
                    <h3 id="map-score-title-{{ $metric }}" class="text-[9px] font-black uppercase tracking-[0.16em] text-red-700">{{ $index + 3 }}. {{ $label }} — {{ $heading }}</h3>
                    <p class="mt-1 text-[9px] leading-4 text-slate-500">{{ $description }}</p>
                    <div id="map-score-chart-{{ $metric }}" class="mt-3 space-y-2" role="img" aria-label="{{ $label }} {{ $language === 'en' ? 'scores by land-cover class, scale 0 to 100' : 'skor per kelas tutupan lahan, skala 0 sampai 100' }}">
                        @foreach ($classEvidenceData as $class)
                            <button type="button" data-score-class="{{ $class['id'] }}" data-score-value="{{ $class[$metric] }}" class="group grid w-full grid-cols-[minmax(0,1fr)_82px_27px] items-center gap-2 rounded-sm text-left">
                                <span class="truncate text-[8px] font-semibold text-slate-600" title="{{ $class['display_name'] }}">{{ $class['display_name'] }}</span>
                                <span class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <span class="block h-full rounded-full" style="width: {{ max(0, min(100, (float) $class[$metric])) }}%; background: {{ $color }}"></span>
                                </span>
                                <strong class="text-right text-[8px] font-black text-slate-700">{{ number_format($class[$metric], 1, $language === 'en' ? '.' : ',', $language === 'en' ? ',' : '.') }}</strong>
                            </button>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </section>

        <div id="map-status-filter" class="absolute bottom-28 left-3 z-500 w-[min(260px,calc(100%-1.5rem))] rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-xl backdrop-blur sm:bottom-32 sm:left-5">
            <div class="mb-2.5 flex items-center justify-between gap-3">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">{{ $language === 'en' ? 'Hotspot filters' : 'Filter hotspot' }}</p>
                <button type="button" data-map-filters-reset class="text-[10px] font-black text-red-600 transition hover:text-red-500">{{ $language === 'en' ? 'Reset' : 'Reset' }}</button>
            </div>

            <p class="mb-2 text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $language === 'en' ? 'NASA confidence' : 'Confidence NASA' }}</p>
            <div class="mb-2 flex items-center justify-between">
                <span class="text-[9px] font-black uppercase tracking-[0.12em] text-slate-400">{{ $language === 'en' ? 'Select all' : 'Pilih semua' }}</span>
                <button type="button" data-map-status="all" class="text-[9px] font-black text-red-600 transition hover:text-red-500">{{ $language === 'en' ? 'All' : 'Semua' }}</button>
            </div>
            <div class="grid grid-cols-2 gap-2 text-[11px] font-bold text-slate-600 sm:grid-cols-4">
                @foreach ([
                    ['low', 'bg-emerald-500', $language === 'en' ? 'Low' : 'Rendah'],
                    ['nominal', 'bg-amber-400', 'Nominal'],
                    ['high', 'bg-red-500', $language === 'en' ? 'High' : 'Tinggi'],
                    ['unrated', 'bg-slate-500', $language === 'en' ? 'Unrated' : 'Belum dinilai'],
                ] as [$key, $color, $label])
                    <button type="button" data-map-confidence="{{ $key }}" data-map-status="{{ $key }}" aria-pressed="true" class="map-status-option flex min-w-0 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2 py-2.5 text-center transition hover:border-slate-300 hover:bg-slate-50">
                        <span class="size-2 shrink-0 rounded-full {{ $color }}"></span>
                        <span class="truncate">{{ $label }}</span>
                    </button>
                @endforeach
            </div>

            <p class="mb-2 mt-3 text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $language === 'en' ? 'Relative FSI (experimental)' : 'FSI relatif (eksperimental)' }}</p>
            <div class="grid grid-cols-2 gap-2 text-[11px] font-bold text-slate-600">
                @foreach ([
                    ['very_low', $language === 'en' ? 'Very Low' : 'Sangat Rendah'],
                    ['low', $language === 'en' ? 'Low' : 'Rendah'],
                    ['moderate', $language === 'en' ? 'Moderate' : 'Sedang'],
                    ['high', $language === 'en' ? 'High' : 'Tinggi'],
                    ['very_high', $language === 'en' ? 'Very High' : 'Sangat Tinggi'],
                ] as [$key, $label])
                    <button type="button" data-map-fsi="{{ $key }}" aria-pressed="true" class="map-status-option flex min-w-0 items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left transition hover:border-slate-300 hover:bg-slate-50">
                        <span class="truncate">{{ $label }}</span>
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5" class="size-3.5 shrink-0 text-red-600" aria-hidden="true"><path d="m4 10 4 4 8-8"/></svg>
                    </button>
                @endforeach
            </div>

            <label class="mt-3 block">
                <span class="mb-2 block text-[9px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $language === 'en' ? 'Land cover' : 'Tutupan lahan' }}</span>
                <select id="map-land-cover-filter" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-bold text-slate-700 outline-none focus:border-red-400 focus:ring-4 focus:ring-red-100">
                    <option value="all">
                        {{ $language === 'en' ? 'All land covers' : 'Semua tutupan lahan' }}
                    </option>

                    @foreach (collect(config('ember.class_evidence', []))->pluck('name')->unique()->sort()->values() as $landCover)
                        @php
                            $landCoverLabel = match ($landCover) {
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
                                default => $landCover,
                            };
                        @endphp

                        <option value="{{ $landCover }}">{{ $landCoverLabel }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div id="map-year-filter" class="absolute bottom-7 left-3 z-550 w-[min(420px,calc(100%-1.5rem))] sm:bottom-8 sm:left-5">
            <div class="map-year-slider-control" style="--slider-progress: calc(100% - 1.125rem)">
                <span class="sr-only">{{ $language === 'en' ? 'Filter locations by year' : 'Filter lokasi berdasarkan tahun' }}</span>
                <div class="map-year-slider-track" aria-hidden="true">
                    @foreach ($availableYears as $year)
                        <span class="map-year-slider-mark" data-year-mark="{{ $year }}" data-active="false">
                            <span class="map-year-slider-mark-label">{{ $year }}</span>
                            <span class="map-year-slider-dot"></span>
                        </span>
                    @endforeach
                    <span class="map-year-slider-mark" data-year-mark="all" data-active="true">
                        <span class="map-year-slider-mark-label">{{ $language === 'en' ? 'All' : 'Semua' }}</span>
                        <span class="map-year-slider-dot"></span>
                    </span>
                </div>
                <input
                    id="map-year-range"
                    class="map-year-slider-input"
                    type="range"
                    min="0"
                    max="{{ $availableYears->count() }}"
                    value="{{ $availableYears->count() }}"
                    step="1"
                    data-year-values="{{ $availableYears->join(',') }}"
                    aria-label="{{ $language === 'en' ? 'Location year' : 'Tahun lokasi' }}"
                >
                <output id="map-year-output" class="map-year-slider-value" for="map-year-range" aria-live="polite">
                    <span id="map-year-label">{{ $language === 'en' ? 'All' : 'Semua' }}</span>
                    <span class="sr-only">, </span>
                    <span id="map-result-count" class="sr-only">{{ $locations->count() }} {{ $language === 'en' ? 'locations' : 'lokasi' }}</span>
                </output>
            </div>
            <p class="mt-1 text-center text-[10px] font-bold text-slate-500">&larr; {{ $language === 'en' ? 'Drag to change year' : 'Geser untuk mengganti tahun' }} &rarr;</p>
        </div>

        <div class="absolute inset-0 bg-white">
            <div id="ember-map" class="h-full w-full bg-slate-100" aria-label="{{ $language === 'en' ? 'EMBER location distribution map' : 'Peta persebaran lokasi EMBER' }}"></div>
            <div id="map-filter-empty" class="pointer-events-none absolute right-4 top-4 z-550 hidden max-w-sm bg-white/95 px-5 py-4 text-center text-sm font-semibold text-slate-700 shadow-lg backdrop-blur sm:right-5 sm:top-5" aria-live="polite">
                {{ $language === 'en' ? 'No locations match the selected filters.' : 'Tidak ada titik lokasi yang sesuai dengan filter.' }}
            </div>

            <aside id="map-detail-panel" data-open="false" class="map-detail-panel absolute inset-y-0 right-0 z-600 flex w-full max-w-sm flex-col bg-white shadow-2xl" aria-hidden="true" aria-label="{{ $language === 'en' ? 'Location details' : 'Detail lokasi' }}">
                <div class="flex items-start justify-between gap-4 bg-slate-950 p-5 text-white">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-red-400">{{ $language === 'en' ? 'Location details' : 'Detail lokasi' }}</p>
                        <h2 id="map-detail-title" class="mt-2 truncate text-xl font-bold">-</h2>
                        <p id="map-detail-region" class="mt-1 line-clamp-2 text-xs leading-5 text-slate-400">-</p>
                    </div>
                    <button id="map-detail-close" type="button" class="flex size-9 shrink-0 items-center justify-center border border-white/20 text-slate-300 transition hover:bg-white/10 hover:text-white" aria-label="{{ $language === 'en' ? 'Close location details' : 'Tutup detail lokasi' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-5" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-5">
                    <div class="grid gap-px bg-slate-200 sm:grid-cols-2">
                        <div class="bg-slate-50 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $language === 'en' ? 'NASA Confidence' : 'Confidence NASA' }}</p>
                            <p id="map-detail-confidence" class="mt-2 font-bold text-slate-900">-</p>
                        </div>
                        <div class="bg-slate-50 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $language === 'en' ? 'Status' : 'Status' }}</p>
                            <p id="map-detail-status" class="mt-2 font-bold text-slate-900">-</p>
                        </div>
                        <div class="bg-slate-950 p-4 text-white">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-red-300">{{ $language === 'en' ? 'Relative FSI (experimental)' : 'FSI Relatif (eksperimental)' }}</p>
                            <p id="map-detail-fsi" class="mt-2 text-2xl font-black">-</p>
                        </div>
                        <div class="bg-slate-950 p-4 text-white">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $language === 'en' ? 'FSI class' : 'Kelas FSI' }}</p>
                            <p id="map-detail-fsiClass" class="mt-2 font-bold">-</p>
                        </div>
                    </div>
                    <p class="mt-2 text-[10px] leading-4 text-slate-500">
                        {{ $language === 'en'
                            ? 'FSI is an experimental relative class-context index, not a probability or confirmation that a hotspot will become a fire.'
                            : 'FSI adalah indeks relatif eksperimental berdasarkan konteks kelas, bukan probabilitas atau konfirmasi bahwa hotspot akan menjadi kebakaran.' }}
                    </p>

                    <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            {{ $language === 'en' ? 'Land-cover context' : 'Konteks tutupan lahan' }}
                        </p>

                        <p id="map-detail-landCover" class="mt-2 font-bold text-slate-900">-</p>

                        <div class="mt-3 flex items-center justify-between gap-3 text-xs">
                            <span class="font-semibold text-slate-400">
                                {{ $language === 'en' ? 'Hotspot Evidence (HSE)' : 'Evidence Hotspot (HSE)' }}
                            </span>
                            <span id="map-detail-hotspotEvidence" class="font-black text-slate-800">-</span>
                        </div>

                        <div class="mt-2 flex items-center justify-between gap-3 text-xs">
                            <span class="font-semibold text-slate-400">
                                {{ $language === 'en' ? 'Historical Burn Evidence (HBE)' : 'Evidence Historis Kebakaran (HBE)' }}
                            </span>
                            <span id="map-detail-historicalBurnEvidence" class="font-black text-slate-800">-</span>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between gap-3 text-xs">
                        <span class="font-semibold text-slate-400">
                            {{ $language === 'en' ? 'Evidence comparison (QA)' : 'Perbandingan evidence (QA)' }}
                        </span>

                        <span id="map-detail-contextFlag" class="rounded-full bg-white px-2.5 py-1 font-black text-slate-700 ring-1 ring-slate-200">
                            -
                        </span>
                    </div>

                    <dl class="mt-5 divide-y divide-slate-100 border-y border-slate-200">
                        @foreach (($language === 'en' ? [
                            'Province' => 'province', 'Regency/City' => 'regency', 'District' => 'district',
                            'Village' => 'village', 'Date' => 'date', 'Coordinates' => 'coordinates',
                        ] : [
                            'Provinsi' => 'province', 'Kabupaten/Kota' => 'regency', 'Kecamatan' => 'district',
                            'Desa' => 'village', 'Tanggal' => 'date', 'Koordinat' => 'coordinates',
                        ]) as $label => $key)
                            <div class="grid grid-cols-[110px_1fr] gap-3 py-3.5">
                                <dt class="text-xs font-semibold text-slate-400">{{ $label }}</dt>
                                <dd id="map-detail-{{ $key }}" class="wrap-break-word text-sm font-semibold text-slate-800">-</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <div class="border-t border-slate-200 p-5">
                    <a id="map-detail-link" href="#" class="flex w-full items-center justify-center gap-2 bg-red-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-red-500">
                        {{ $language === 'en' ? 'Open full details' : 'Buka detail lengkap' }} <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </aside>
            <script id="ember-map-data" type="application/json">{!! json_encode(['language' => $language, 'locations' => $locations, 'boundaryLayers' => $boundaryLayers], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        </div>
    </section>
@endsection
