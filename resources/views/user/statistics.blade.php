@extends('layouts.user')

@section('title', ($language === 'en' ? 'Statistics' : 'Statistik') . ' - EMBER')

@section('content')
    @php
        $statuses = [
            'high' => ['label' => $language === 'en' ? 'High' : 'Tinggi', 'color' => 'bg-red-700', 'text' => 'text-red-700', 'hex' => '#b91c1c'],
            'medium' => ['label' => $language === 'en' ? 'Medium' : 'Sedang', 'color' => 'bg-amber-600', 'text' => 'text-amber-700', 'hex' => '#d97706'],
            'low' => ['label' => $language === 'en' ? 'Low' : 'Rendah', 'color' => 'bg-emerald-700', 'text' => 'text-emerald-700', 'hex' => '#047857'],
            'unrated' => ['label' => $language === 'en' ? 'Unrated' : 'Belum dinilai', 'color' => 'bg-slate-700', 'text' => 'text-slate-700', 'hex' => '#334155'],
        ];
        $chartStatistics = $yearlyStatistics->sortBy('year')->values();
        $maxYearTotal = max(1, (int) $chartStatistics->max('total'));
        $latestStatistic = $chartStatistics->last();
        $donutStops = [];
        $donutOffset = 0;
        foreach ($statuses as $key => $status) {
            $donutEnd = $donutOffset + ($latestStatistic ? $latestStatistic['percentages'][$key] : 0);
            $donutStops[] = $status['hex'].' '.$donutOffset.'% '.$donutEnd.'%';
            $donutOffset = $donutEnd;
        }
        $donutBackground = $donutOffset > 0
            ? 'conic-gradient('.implode(', ', $donutStops).')'
            : 'conic-gradient(#e2e8f0 0% 100%)';

        $dashboardStatuses = [
            'high' => ['label' => $language === 'en' ? 'High' : 'Tinggi', 'hex' => '#b91c1c'],
            'nominal' => ['label' => 'Nominal', 'hex' => '#d97706'],
            'low' => ['label' => $language === 'en' ? 'Low' : 'Rendah', 'hex' => '#047857'],
            'unrated' => ['label' => $language === 'en' ? 'Unrated' : 'Belum dinilai', 'hex' => '#334155'],
        ];
        $dashboardClasses = $dashboard['classes']->sortBy('id')->values();
        $confidenceStops = [];
        $confidenceOffset = 0;
        foreach ($dashboardStatuses as $key => $status) {
            $share = $dashboard['confidence'][$key]['share'];
            $confidenceStops[] = $status['hex'].' '.$confidenceOffset.'% '.($confidenceOffset + $share).'%';
            $confidenceOffset += $share;
        }
        $confidenceDonut = $confidenceOffset > 0
            ? 'conic-gradient('.implode(', ', $confidenceStops).')'
            : 'conic-gradient(#e2e8f0 0% 100%)';
        $classStops = [];
        $classOffset = 0;
        foreach ($dashboardClasses as $class) {
            $share = $class['share'];
            if ($share > 0) {
                $classStops[] = $class['color'].' '.$classOffset.'% '.($classOffset + $share).'%';
            }
            $classOffset += $share;
        }
        $classDonut = $classOffset > 0 && $classStops !== []
            ? 'conic-gradient('.implode(', ', $classStops).')'
            : 'conic-gradient(#e2e8f0 0% 100%)';
        $metricCharts = [
            'hse' => [
                'title' => $language === 'en' ? 'HSE — Hotspot Evidence' : 'HSE — Bukti Hotspot',
                'description' => $language === 'en'
                    ? 'Confidence-weighted hotspot evidence by land-cover class (0–100), based on the 2021–2023 source period; class-level context, not an aggregate.'
                    : 'Bukti hotspot berbobot confidence per kelas tutupan lahan (0–100), berdasarkan periode sumber 2021–2023; konteks tingkat kelas, bukan agregat.',
                'color' => '#dc2626',
            ],
            'hbe' => [
                'title' => $language === 'en' ? 'HBE — Historical Burn Evidence' : 'HBE — Bukti Kebakaran Historis',
                'description' => $language === 'en'
                    ? 'Class-level historical burn evidence (0–100). Supporting burn footprint and frequency metrics remain separate.'
                    : 'Bukti historis kebakaran tingkat kelas (0–100). Metrik jejak terbakar dan frekuensi ditampilkan terpisah.',
                'color' => '#d97706',
            ],
            'fsi' => [
                'title' => $language === 'en' ? 'FSI — Relative Susceptibility Index' : 'FSI — Indeks Kerentanan Relatif',
                'description' => $language === 'en'
                    ? 'Existing experimental class-context index (0–100), not the probability of fire.'
                    : 'Indeks eksperimental berdasarkan konteks kelas (0–100), bukan probabilitas kebakaran.',
                'color' => '#2563eb',
            ],
        ];
    @endphp

    <section class="relative isolate overflow-hidden bg-slate-950 px-4 py-14 text-white sm:px-6 lg:px-8 lg:py-18">
        <div class="absolute -right-32 -top-40 -z-10 size-[32rem] rounded-full bg-red-600/20 blur-3xl" aria-hidden="true"></div>
        <div class="absolute inset-0 -z-10 opacity-[.04]" style="background-image:linear-gradient(rgba(255,255,255,.8) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.8) 1px,transparent 1px);background-size:48px 48px" aria-hidden="true"></div>
        <div class="mx-auto max-w-7xl">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.2em] text-red-300">
                <span class="size-1.5 rounded-full bg-red-500"></span> EMBER DATA
            </div>
            <h1 class="mt-5 text-4xl font-black tracking-[-0.04em] sm:text-5xl">{{ $language === 'en' ? 'Annual Statistics' : 'Statistik Tahunan' }}</h1>
            <p class="mt-4 max-w-2xl leading-7 text-slate-300">{{ $language === 'en' ? 'Yearly distribution of EMBER locations by confidence status.' : 'Distribusi titik lokasi EMBER setiap tahun berdasarkan status confidence.' }}</p>
        </div>
    </section>

    <section class="relative mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5" data-reveal>
            <article class="relative overflow-hidden rounded-2xl bg-slate-950 p-5 text-white shadow-xl shadow-slate-950/10">
                <div class="absolute -right-5 -top-5 size-24 rounded-full bg-red-500/20 blur-2xl" aria-hidden="true"></div>
                <p class="relative text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">{{ $language === 'en' ? 'All locations' : 'Semua lokasi' }}</p>
                <p class="relative mt-3 text-4xl font-black tracking-tight">{{ number_format($summary['total']) }}</p>
                <p class="relative mt-2 text-xs text-slate-500">{{ $language === 'en' ? 'Recorded monitoring points' : 'Titik pemantauan tercatat' }}</p>
            </article>
            @foreach ($statuses as $key => $status)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">{{ $status['label'] }}</p>
                        <span class="size-2.5 rounded-full {{ $status['color'] }} shadow-[0_0_0_4px_rgba(148,163,184,.1)]"></span>
                    </div>
                    <p class="mt-3 text-4xl font-black tracking-tight {{ $status['text'] }}">{{ number_format($summary[$key]) }}</p>
                    <p class="mt-2 text-xs text-slate-400">{{ $summary['total'] > 0 ? number_format(($summary[$key] / $summary['total']) * 100, 1) : 0 }}% {{ $language === 'en' ? 'of total' : 'dari total' }}</p>
                </article>
            @endforeach
        </div>

        @if ($chartStatistics->isNotEmpty())
            <section class="mt-8 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_20px_60px_rgba(15,23,42,.07)]" data-reveal>
                <div class="flex flex-col gap-5 border-b border-slate-100 px-5 py-6 sm:px-7 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-600">{{ $language === 'en' ? 'Data visualization' : 'Visualisasi data' }}</p>
                        <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950">{{ $language === 'en' ? 'Location trend by year' : 'Tren lokasi per tahun' }}</h2>
                        <p class="mt-2 text-sm text-slate-500">{{ $language === 'en' ? 'Stacked bars show the confidence composition for each year.' : 'Batang bertumpuk menunjukkan komposisi confidence pada setiap tahun.' }}</p>
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        @foreach ($statuses as $status)
                            <span class="inline-flex items-center gap-2 text-xs font-bold text-slate-600"><span class="size-2.5 rounded-full {{ $status['color'] }}"></span>{{ $status['label'] }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="overflow-x-auto px-4 pb-6 pt-7 sm:px-7">
                    <div class="relative" style="min-width: {{ max(620, $chartStatistics->count() * 105) }}px">
                        <div class="pointer-events-none absolute inset-x-0 top-0 h-72" aria-hidden="true">
                            @foreach ([100, 75, 50, 25, 0] as $level)
                                <div class="absolute inset-x-0 flex items-center gap-3" style="top: {{ 100 - $level }}%">
                                    <span class="w-8 -translate-y-1/2 text-right text-[10px] font-semibold text-slate-400">{{ (int) round($maxYearTotal * $level / 100) }}</span>
                                    <span class="h-px flex-1 bg-slate-100"></span>
                                </div>
                            @endforeach
                        </div>

                        <div class="relative ml-11 flex h-[324px] items-end justify-around gap-5 px-3">
                            @foreach ($chartStatistics as $statistic)
                                @php($barHeight = max(4, ($statistic['total'] / $maxYearTotal) * 100))
                                <div class="group relative flex h-full min-w-16 flex-1 flex-col items-center justify-end">
                                    <div class="pointer-events-none absolute bottom-[calc(var(--bar-height)+2.8rem)] left-1/2 z-20 hidden w-48 -translate-x-1/2 rounded-2xl bg-slate-950 p-4 text-white shadow-2xl group-hover:block" style="--bar-height: {{ $barHeight }}%">
                                        <div class="flex items-center justify-between gap-4">
                                            <strong class="text-base">{{ $statistic['year'] }}</strong>
                                            <span class="text-xs font-bold text-red-300">{{ $statistic['total'] }} {{ $language === 'en' ? 'points' : 'titik' }}</span>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-2 text-[10px] text-slate-300">
                                            @foreach ($statuses as $key => $status)
                                                <span class="flex items-center gap-1.5"><i class="size-1.5 rounded-full {{ $status['color'] }}"></i>{{ $status['label'] }}: {{ $statistic['counts'][$key] }}</span>
                                            @endforeach
                                        </div>
                                    </div>

                                    <span class="mb-2 text-xs font-black text-slate-700">{{ $statistic['total'] }}</span>
                                    <div class="statistics-bar flex w-full max-w-16 flex-col overflow-hidden rounded-t-xl bg-slate-100 shadow-sm ring-1 ring-inset ring-slate-200" style="height: {{ $barHeight }}%; animation-delay: {{ $loop->index * 90 }}ms" aria-label="{{ $statistic['year'] }}: {{ $statistic['total'] }} {{ $language === 'en' ? 'locations' : 'lokasi' }}">
                                        @foreach ($statuses as $key => $status)
                                            @if ($statistic['percentages'][$key] > 0)
                                                <span class="{{ $status['color'] }} transition group-hover:brightness-110" style="height: {{ $statistic['percentages'][$key] }}%" title="{{ $status['label'] }}: {{ $statistic['counts'][$key] }}"></span>
                                            @endif
                                        @endforeach
                                    </div>
                                    <span class="mt-3 text-xs font-black text-slate-600">{{ $statistic['year'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="grid border-t border-slate-100 bg-slate-50/70 sm:grid-cols-3">
                    <div class="p-5 sm:border-r sm:border-slate-200">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ $language === 'en' ? 'Years recorded' : 'Tahun tercatat' }}</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">{{ $chartStatistics->count() }}</p>
                    </div>
                    <div class="border-y border-slate-200 p-5 sm:border-y-0 sm:border-r">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ $language === 'en' ? 'Highest annual total' : 'Total tahunan tertinggi' }}</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">{{ number_format($maxYearTotal) }}</p>
                    </div>
                    <div class="p-5">
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">{{ $language === 'en' ? 'Latest year' : 'Tahun terbaru' }}</p>
                        <p class="mt-2 text-2xl font-black text-slate-950">{{ $latestStatistic['year'] }}</p>
                    </div>
                </div>
            </section>

            <section class="mt-8 overflow-hidden rounded-3xl border border-slate-200 bg-slate-950 text-white shadow-[0_20px_60px_rgba(15,23,42,.12)]" data-reveal data-province-trend>
                <div class="flex flex-col gap-5 border-b border-white/10 px-5 py-6 sm:px-7 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-400">{{ $language === 'en' ? 'Province distribution' : 'Sebaran provinsi' }}</p>
                        <h2 class="mt-2 text-2xl font-black tracking-tight">{{ $language === 'en' ? 'Trend by province' : 'Tren per provinsi' }}</h2>
                        <p class="mt-2 text-sm text-slate-400">{{ $language === 'en' ? 'Select a province to view changes in its number of locations.' : 'Pilih provinsi untuk melihat perubahan jumlah lokasinya.' }}</p>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <label class="text-xs font-bold text-slate-300">
                            {{ $language === 'en' ? 'Period' : 'Periode' }}
                            <select data-province-period class="mt-2 block w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm font-bold text-white outline-none focus:border-red-400 focus:ring-4 focus:ring-red-500/15">
                                <option value="yearly" class="bg-slate-900">{{ $language === 'en' ? 'By year' : 'Per tahun' }}</option>
                                <option value="monthly" class="bg-slate-900">{{ $language === 'en' ? 'By month' : 'Per bulan' }}</option>
                            </select>
                        </label>
                        <label data-province-year-wrapper class="hidden text-xs font-bold text-slate-300">
                            {{ $language === 'en' ? 'Year' : 'Tahun' }}
                            <select data-province-year class="mt-2 block w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm font-bold text-white outline-none focus:border-red-400 focus:ring-4 focus:ring-red-500/15">
                                @foreach ($provinceYears->sortDesc() as $year)
                                    <option value="{{ $year }}" class="bg-slate-900">{{ $year }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-xs font-bold text-slate-300">
                            {{ $language === 'en' ? 'Selected province' : 'Provinsi terpilih' }}
                            <select data-province-select class="mt-2 block w-full min-w-56 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm font-bold text-white outline-none focus:border-red-400 focus:ring-4 focus:ring-red-500/15">
                                @foreach ($provinceStatistics as $province)
                                    <option value="{{ $loop->index }}" class="bg-slate-900">{{ $province['name'] }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </div>

                <div class="grid lg:grid-cols-[minmax(0,1fr)_300px]">
                    <div class="min-w-0 border-b border-white/10 p-4 sm:p-6 lg:border-b-0 lg:border-r">
                        <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p data-province-chart-name class="text-xl font-black">-</p>
                                <p data-province-chart-subtitle class="mt-1 text-xs text-slate-500">{{ $language === 'en' ? 'Number of locations per year' : 'Jumlah lokasi per tahun' }}</p>
                            </div>
                            <p class="text-right text-xs text-slate-400"><strong data-province-chart-total class="block text-2xl font-black text-red-400">0</strong>{{ $language === 'en' ? 'total locations' : 'total lokasi' }}</p>
                        </div>
                        <div class="relative overflow-x-auto rounded-2xl border border-white/10 bg-white/[.035] p-2">
                            <svg data-province-chart class="h-[330px] min-w-[620px] w-full" viewBox="0 0 900 330" role="img" aria-label="{{ $language === 'en' ? 'Province annual trend line chart' : 'Grafik garis tren tahunan provinsi' }}"></svg>
                            <div data-province-tooltip class="pointer-events-none absolute hidden rounded-xl bg-white px-3 py-2 text-xs font-bold text-slate-950 shadow-xl"></div>
                        </div>
                    </div>

                    <aside class="p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-black">{{ $language === 'en' ? 'Province list' : 'Daftar provinsi' }}</h3>
                            <span class="rounded-full bg-white/10 px-2.5 py-1 text-[10px] font-bold text-slate-400">{{ $provinceStatistics->count() }}</span>
                        </div>
                        <div class="mt-4 max-h-[340px] space-y-1.5 overflow-y-auto pr-1" data-province-list>
                            @foreach ($provinceStatistics as $province)
                                <button type="button" data-province-option="{{ $loop->index }}" class="group flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left transition hover:bg-white/10">
                                    <span class="min-w-0 truncate text-xs font-bold text-slate-300 group-hover:text-white">{{ $province['name'] }}</span>
                                    <span data-province-option-total class="shrink-0 rounded-full bg-white/5 px-2 py-1 text-[10px] font-black text-slate-500 group-hover:text-red-300">{{ number_format($province['total']) }}</span>
                                </button>
                            @endforeach
                        </div>
                    </aside>
                </div>

                <script data-province-trend-data type="application/json">{!! json_encode([
                    'years' => $provinceYears,
                    'provinces' => $provinceStatistics,
                    'locationLabel' => $language === 'en' ? 'locations' : 'lokasi',
                    'yearlySubtitle' => $language === 'en' ? 'Number of locations per year' : 'Jumlah lokasi per tahun',
                    'monthlySubtitle' => $language === 'en' ? 'Monthly locations in the selected year' : 'Jumlah lokasi bulanan pada tahun terpilih',
                    'months' => $language === 'en'
                        ? ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
                        : ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            </section>

            <section class="mt-8 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_20px_60px_rgba(15,23,42,.07)]" data-reveal data-annual-donut>
                <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-red-600">{{ $language === 'en' ? 'Status composition' : 'Komposisi status' }}</p>
                        <h2 class="mt-1 text-2xl font-black text-slate-950">{{ $language === 'en' ? 'Annual donut statistics' : 'Statistik donat tahunan' }}</h2>
                        <p class="mt-2 text-sm text-slate-500">{{ $language === 'en' ? 'Select a year to view its confidence distribution.' : 'Pilih tahun untuk melihat distribusi confidence.' }}</p>
                    </div>
                    <label class="text-xs font-black uppercase tracking-wider text-slate-500">
                        {{ $language === 'en' ? 'Selected year' : 'Tahun terpilih' }}
                        <select data-annual-donut-year class="mt-2 block min-w-40 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-black text-slate-950 outline-none focus:border-red-500 focus:ring-4 focus:ring-red-100">
                            @foreach ($chartStatistics->reverse() as $statistic)
                                <option value="{{ $statistic['year'] }}">{{ $statistic['year'] }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="grid items-center gap-8 p-5 sm:p-7 lg:grid-cols-[minmax(280px,380px)_minmax(0,1fr)] lg:p-9">
                    <div class="relative mx-auto aspect-square w-full max-w-[340px]">
                        <div data-annual-donut-chart class="absolute inset-0 rounded-full shadow-[0_20px_50px_rgba(15,23,42,.12)] transition-[background] duration-500" style="background: {{ $donutBackground }}" role="img" aria-label="{{ $language === 'en' ? 'Annual confidence status donut chart' : 'Grafik donat status confidence tahunan' }}"></div>
                        <div class="absolute inset-[22%] flex flex-col items-center justify-center rounded-full bg-white text-center shadow-inner ring-1 ring-slate-100">
                            <span data-annual-donut-year-label class="text-xs font-black uppercase tracking-[0.18em] text-red-600">{{ $latestStatistic['year'] }}</span>
                            <strong data-annual-donut-total class="mt-2 text-4xl font-black tracking-tight text-slate-950">{{ number_format($latestStatistic['total']) }}</strong>
                            <span class="mt-1 text-xs font-semibold text-slate-400">{{ $language === 'en' ? 'total locations' : 'total lokasi' }}</span>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($statuses as $key => $status)
                            <article data-annual-donut-item="{{ $key }}" class="border border-slate-200 bg-slate-50/70 p-4 transition hover:border-slate-300 hover:bg-white">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="inline-flex items-center gap-2 text-sm font-black text-slate-700"><i class="size-3 rounded-full {{ $status['color'] }}"></i>{{ $status['label'] }}</span>
                                    <span data-annual-donut-percentage class="rounded-full bg-white px-2.5 py-1 text-xs font-black {{ $status['text'] }} ring-1 ring-slate-200">{{ number_format($latestStatistic['percentages'][$key], 1) }}%</span>
                                </div>
                                <p data-annual-donut-count class="mt-4 text-3xl font-black tracking-tight text-slate-950">{{ number_format($latestStatistic['counts'][$key]) }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $language === 'en' ? 'monitoring points' : 'titik pemantauan' }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
                <script data-annual-donut-data type="application/json">{!! json_encode([
                    'locale' => $language === 'en' ? 'en-US' : 'id-ID',
                    'statistics' => $chartStatistics,
                    'statuses' => collect($statuses)->map(fn (array $status) => ['color' => $status['hex']]),
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            </section>
        @else
            <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-500">{{ $language === 'en' ? 'No dated location data is available yet.' : 'Belum ada data lokasi yang memiliki tanggal.' }}</div>
        @endif
    </section>
    <section id="ember-statistics-dashboard" class="mx-auto max-w-7xl px-4 pb-4 sm:px-6 lg:px-8" data-hotspot-total="{{ $dashboard['total'] }}" data-classified-total="{{ $dashboard['mapped_total'] }}">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-600">{{ $language === 'en' ? 'Interactive analytical view' : 'Tampilan analitik interaktif' }}</p>
                <h2 class="mt-1 text-3xl font-black tracking-tight text-slate-950">{{ $language === 'en' ? 'EMBER hotspot statistics' : 'Statistik hotspot EMBER' }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">{{ $language === 'en' ? 'Live hotspot counts use the same georeferenced records and filters as the interactive map. HSE, HBE, and FSI remain class-level results under the existing methodology.' : 'Jumlah hotspot memakai data georeferensi dan filter yang sama dengan peta interaktif. HSE, HBE, dan FSI tetap merupakan hasil tingkat kelas sesuai metodologi yang ada.' }}</p>
            </div>
            <a href="{{ route('user.map', array_filter([
                'lang' => $language,
                'year' => $dashboardFilters['year'],
                'province' => $dashboardFilters['province'],
                'land_cover' => $dashboardFilters['land_cover'],
                'confidence' => $dashboardFilters['confidence'] === [] ? null : implode(',', $dashboardFilters['confidence']),
                'fsi' => $dashboardFilters['fsi'] === [] ? null : implode(',', $dashboardFilters['fsi']),
            ])) }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-slate-950 px-4 py-3 text-xs font-black text-white transition hover:bg-red-700">
                {{ $language === 'en' ? 'Open filtered map' : 'Buka peta terfilter' }} <span aria-hidden="true">→</span>
            </a>
        </div>

        <form method="GET" action="{{ route('user.statistics') }}" class="mb-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
            <input type="hidden" name="lang" value="{{ $language }}">
            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">
                {{ $language === 'en' ? 'Year' : 'Tahun' }}
                <select name="year" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold normal-case text-slate-800">
                    <option value="">{{ $language === 'en' ? 'All years' : 'Semua tahun' }}</option>
                    @foreach ($dashboard['years'] as $year)
                        <option value="{{ $year }}" @selected($dashboardFilters['year'] === $year)>{{ $year }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">
                {{ $language === 'en' ? 'Province' : 'Provinsi' }}
                <select name="province" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold normal-case text-slate-800">
                    <option value="">{{ $language === 'en' ? 'All provinces' : 'Semua provinsi' }}</option>
                    @foreach ($dashboard['provinces'] as $province)
                        <option value="{{ $province }}" @selected($dashboardFilters['province'] === $province)>{{ $province }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-[10px] font-black uppercase tracking-wider text-slate-500">
                {{ $language === 'en' ? 'Land-cover class' : 'Kelas tutupan lahan' }}
                <select name="land_cover" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold normal-case text-slate-800">
                    <option value="">{{ $language === 'en' ? 'All classes' : 'Semua kelas' }}</option>
                    @foreach ($dashboardClasses as $class)
                        <option value="{{ $class['name'] }}" @selected($dashboardFilters['land_cover'] === $class['name'])>{{ $class['display_name'] }}</option>
                    @endforeach
                </select>
            </label>
            <fieldset class="text-[10px] font-black uppercase tracking-wider text-slate-500">
                <legend>{{ $language === 'en' ? 'NASA confidence' : 'Confidence NASA' }}</legend>
                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-2">
                    @foreach ($dashboardStatuses as $key => $status)
                            <label class="inline-flex items-center gap-1.5 text-[10px] font-semibold normal-case text-slate-700">
                                <input type="checkbox" name="confidence[]" value="{{ $key }}" @checked($dashboardFilters['confidence'] === [] || in_array($key, $dashboardFilters['confidence'], true))>
                            {{ $status['label'] }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset class="text-[10px] font-black uppercase tracking-wider text-slate-500">
                <legend>{{ $language === 'en' ? 'FSI class' : 'Kelas FSI' }}</legend>
                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-2">
                    @foreach ([
                        'very_low' => $language === 'en' ? 'Very low' : 'Sangat rendah',
                        'low' => $language === 'en' ? 'Low' : 'Rendah',
                        'moderate' => $language === 'en' ? 'Moderate' : 'Sedang',
                        'high' => $language === 'en' ? 'High' : 'Tinggi',
                        'very_high' => $language === 'en' ? 'Very high' : 'Sangat tinggi',
                        'unrated' => $language === 'en' ? 'Unrated' : 'Belum dinilai',
                    ] as $key => $label)
                        <label class="inline-flex items-center gap-1.5 text-[10px] font-semibold normal-case text-slate-700">
                            <input type="checkbox" name="fsi[]" value="{{ $key }}" @checked($dashboardFilters['fsi'] === [] || in_array($key, $dashboardFilters['fsi'], true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="flex gap-2 sm:col-span-2 lg:col-span-5">
                <button type="submit" class="rounded-lg bg-red-700 px-4 py-2 text-xs font-black text-white hover:bg-red-800">{{ $language === 'en' ? 'Apply filters' : 'Terapkan filter' }}</button>
                <a href="{{ route('user.statistics', ['lang' => $language]) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-black text-slate-700 hover:bg-slate-50">{{ $language === 'en' ? 'Clear filters' : 'Hapus filter' }}</a>
            </div>
        </form>

        <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl bg-slate-950 p-4 text-white">
                <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">{{ $language === 'en' ? 'Filtered georeferenced hotspots' : 'Hotspot georeferensi terfilter' }}</p>
                <p class="mt-2 text-3xl font-black">{{ number_format($dashboard['total']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-[9px] font-black uppercase tracking-wider text-slate-500">{{ $language === 'en' ? 'Classified hotspots' : 'Hotspot dengan kelas' }}</p>
                <p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($dashboard['mapped_total']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-[9px] font-black uppercase tracking-wider text-slate-500">{{ $language === 'en' ? 'Without a recognized class' : 'Tanpa kelas yang dikenali' }}</p>
                <p class="mt-2 text-3xl font-black text-slate-900">{{ number_format($dashboard['unclassified_total']) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-[9px] font-black uppercase tracking-wider text-slate-500">{{ $language === 'en' ? 'Classes represented' : 'Kelas yang ditampilkan' }}</p>
                <p class="mt-2 text-3xl font-black text-slate-900">{{ $dashboardClasses->count() }} <span class="text-base font-bold text-slate-400">/ 13</span></p>
            </article>
        </div>

        <div class="grid gap-5 xl:grid-cols-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="stats-confidence-title">
                <h3 id="stats-confidence-title" class="text-lg font-black text-slate-950">{{ $language === 'en' ? 'A. NASA Confidence Distribution' : 'A. Distribusi Confidence NASA' }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ $language === 'en' ? 'Categories and proportions for all filtered georeferenced hotspots.' : 'Kategori dan proporsi seluruh hotspot georeferensi yang lolos filter.' }}</p>
                <div class="mt-5 grid items-center gap-5 sm:grid-cols-[160px_1fr]">
                    <div class="relative mx-auto size-40 rounded-full" style="background: {{ $confidenceDonut }}" role="img" aria-label="{{ $language === 'en' ? 'NASA confidence distribution for ' : 'Distribusi confidence NASA untuk ' }}{{ number_format($dashboard['total']) }} {{ $language === 'en' ? 'hotspots' : 'hotspot' }}">
                        <div class="absolute inset-[25%] flex flex-col items-center justify-center rounded-full bg-white text-center shadow-inner">
                            <strong class="text-2xl font-black">{{ number_format($dashboard['total']) }}</strong>
                            <span class="text-[8px] font-bold uppercase text-slate-500">{{ $language === 'en' ? 'hotspots' : 'hotspot' }}</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        @foreach ($dashboardStatuses as $key => $status)
                            @php($item = $dashboard['confidence'][$key])
                            <div class="flex items-center gap-2 text-xs" title="{{ $status['label'] }}: {{ number_format($item['count']) }} / {{ number_format($dashboard['total']) }} ({{ number_format($item['share'], 1) }}%)">
                                <span class="size-2.5 rounded-full" style="background: {{ $status['hex'] }}"></span>
                                <span class="flex-1 font-semibold text-slate-700">{{ $status['label'] }}</span>
                                <strong>{{ number_format($item['count']) }}</strong>
                                <span class="w-14 text-right text-slate-500">{{ number_format($item['share'], 1) }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="stats-class-title">
                <h3 id="stats-class-title" class="text-lg font-black text-slate-950">{{ $language === 'en' ? 'B. Hotspot Distribution by Land-Cover Class' : 'B. Distribusi Hotspot per Kelas Tutupan Lahan' }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ $language === 'en' ? 'Thirteen official classes; percentages use classified hotspots as the denominator.' : 'Tiga belas kelas resmi; persentase menggunakan hotspot berkelas sebagai penyebut.' }}</p>
                <div class="mt-5 grid items-center gap-5 sm:grid-cols-[160px_1fr]">
                    <div class="relative mx-auto size-40 rounded-full" style="background: {{ $classDonut }}" role="img" aria-label="{{ $language === 'en' ? 'Distribution across all 13 land-cover classes; denominator ' : 'Distribusi seluruh 13 kelas tutupan lahan; penyebut ' }}{{ number_format($dashboard['mapped_total']) }}">
                        <div class="absolute inset-[25%] flex flex-col items-center justify-center rounded-full bg-white text-center shadow-inner">
                            <strong class="text-2xl font-black">{{ number_format($dashboard['mapped_total']) }}</strong>
                            <span class="text-[8px] font-bold uppercase text-slate-500">{{ $language === 'en' ? 'classified' : 'berkelas' }}</span>
                        </div>
                    </div>
                    <div class="max-h-48 space-y-1 overflow-y-auto pr-1">
                        @foreach ($dashboardClasses as $class)
                            <div class="flex items-center gap-2 text-[10px]" title="{{ $class['display_name'] }}: {{ number_format($class['count']) }} / {{ number_format($dashboard['mapped_total']) }} ({{ number_format($class['share'], 2) }}%)">
                                <span class="size-2 shrink-0 rounded-full" style="background: {{ $class['color'] }}"></span>
                                <span class="min-w-0 flex-1 truncate font-semibold text-slate-700">{{ $class['display_name'] }}</span>
                                <strong>{{ number_format($class['count']) }}</strong>
                                <span class="w-12 text-right text-slate-500">{{ number_format($class['share'], 2) }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <p class="mt-3 text-[10px] leading-4 text-slate-500">{{ $language === 'en' ? 'Zero means no matching hotspot was recorded in that class; it is distinct from missing class data. ' : 'Nol berarti tidak ada hotspot yang cocok pada kelas tersebut; berbeda dari data kelas yang tidak tersedia. ' }}{{ $language === 'en' ? 'Unclassified locations are excluded from this donut denominator and counted above.' : 'Lokasi tanpa kelas dikeluarkan dari penyebut donat ini dan dihitung di atas.' }}</p>
            </section>

            @foreach ($metricCharts as $metric => $chart)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="stats-{{ $metric }}-title">
                    <h3 id="stats-{{ $metric }}-title" class="text-lg font-black text-slate-950">{{ $loop->iteration === 1 ? 'C. ' : ($loop->iteration === 2 ? 'D. ' : 'E. ') }}{{ $chart['title'] }}</h3>
                    <p class="mt-1 text-xs leading-5 text-slate-500">{{ $chart['description'] }}</p>
                    <div class="mt-4 space-y-2">
                        @foreach ($dashboardClasses as $class)
                            @php($score = $class[$metric])
                            <button type="button" data-statistics-class="{{ $class['name'] }}" class="grid w-full grid-cols-[minmax(0,1fr)_minmax(72px,1fr)_42px] items-center gap-2 rounded text-left text-[10px] hover:bg-slate-50" title="{{ $class['display_name'] }}: {{ $score === null ? ($language === 'en' ? 'unavailable' : 'tidak tersedia') : number_format($score, 3) . ' / 100' }}">
                                <span class="truncate font-semibold text-slate-700">{{ $class['display_name'] }}</span>
                                <span class="h-2 overflow-hidden rounded-full bg-slate-100">
                                    <span class="block h-full rounded-full" style="width: {{ $score === null ? 0 : max(0, min(100, (float) $score)) }}%; background: {{ $chart['color'] }}"></span>
                                </span>
                                <strong class="text-right">{{ $score === null ? ($language === 'en' ? 'N/A' : 'T/A') : number_format($score, 1) }}</strong>
                            </button>
                        @endforeach
                    </div>
                    <p class="mt-3 text-[10px] text-slate-500">{{ $language === 'en' ? 'Scale: 0–100. Scores are class-level and are not aggregated across classes.' : 'Skala: 0–100. Skor tingkat kelas dan tidak diagregasi lintas kelas.' }}</p>
                </section>
            @endforeach
        </div>

        <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="stats-table-title">
            <div class="border-b border-slate-200 p-5">
                <h3 id="stats-table-title" class="text-xl font-black text-slate-950">{{ $language === 'en' ? 'Complete class-level statistics' : 'Statistik lengkap tingkat kelas' }}</h3>
                <p class="mt-1 text-xs leading-5 text-slate-500">{{ $language === 'en' ? 'Hotspot counts reflect the filters above. HSE/HBE/FSI and supporting source metrics are class-level model data, not per-year or per-province recalculations.' : 'Jumlah hotspot mengikuti filter di atas. HSE/HBE/FSI dan metrik sumber adalah data model tingkat kelas, bukan hitung ulang per tahun atau provinsi.' }}</p>
                <p class="mt-1 text-xs leading-5 text-slate-500">{{ $language === 'en' ? 'Configured mapped class area is the 2024 source area; it is not adjusted to the selected year or province. Historical burn inputs are provisional reconstructions.' : 'Luas kelas terpetakan yang dikonfigurasi adalah luas sumber tahun 2024; tidak disesuaikan dengan tahun atau provinsi terpilih. Masukan kebakaran historis merupakan rekonstruksi sementara.' }}</p>
            </div>
            <div class="overflow-x-auto">
                <p class="px-5 py-3 text-[10px] leading-4 text-slate-500">{{ $language === 'en' ? 'Hotspot counts and confidence composition use the selected filters' : 'Jumlah hotspot dan komposisi confidence memakai filter terpilih' }}: {{ $dashboardFilters['year'] ?? ($language === 'en' ? 'all years' : 'semua tahun') }} · {{ $dashboardFilters['province'] ?: ($language === 'en' ? 'all provinces' : 'semua provinsi') }} · {{ $dashboardFilters['land_cover'] ?: ($language === 'en' ? 'all classes' : 'semua kelas') }}. {{ $language === 'en' ? 'Evidence source periods: HSE 2021–2023; HBE pre-2024 reconstruction; class area and 2024 burn rate from 2024.' : 'Periode sumber evidence: HSE 2021–2023; rekonstruksi HBE pra-2024; luas kelas dan laju terbakar 2024 bersumber dari 2024.' }}</p>
                <table class="min-w-[1500px] w-full text-left text-[10px]">
                    <thead class="bg-slate-50 text-[9px] font-black uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-3">{{ $language === 'en' ? 'ID' : 'ID' }}</th>
                            <th class="px-3 py-3">{{ $language === 'en' ? 'Official class' : 'Kelas resmi' }}</th>
                            <th class="px-3 py-3 text-right">{{ $language === 'en' ? 'Hotspots' : 'Hotspot' }}</th>
                            <th class="px-3 py-3 text-right">{{ $language === 'en' ? 'Share' : 'Persentase' }}</th>
                            @foreach ($dashboardStatuses as $status)
                                <th class="px-3 py-3 text-right">{{ $status['label'] }} (n, %)</th>
                            @endforeach
                            <th class="px-3 py-3 text-right">{{ $language === 'en' ? 'HSE source hotspots 2021–2023' : 'Hotspot sumber HSE 2021–2023' }}</th>
                            <th class="px-3 py-3 text-right">HSE / 100</th>
                            <th class="px-3 py-3 text-right">HBE / 100</th>
                            <th class="px-3 py-3 text-right">{{ $language === 'en' ? 'Ever burned pre-2024' : 'Pernah terbakar pra-2024' }}</th>
                            <th class="px-3 py-3 text-right">{{ $language === 'en' ? 'Mean burn frequency' : 'Rerata frekuensi terbakar' }}</th>
                            <th class="px-3 py-3 text-right">{{ $language === 'en' ? '2024 burn rate' : 'Laju terbakar 2024' }}</th>
                            <th class="px-3 py-3 text-right">FSI / 100</th>
                            <th class="px-3 py-3">{{ $language === 'en' ? 'FSI class' : 'Kelas FSI' }}</th>
                            <th class="px-3 py-3 text-right">{{ $language === 'en' ? '2024 area (km²)' : 'Luas 2024 (km²)' }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($dashboardClasses as $class)
                                            <tr class="hover:bg-slate-50 {{ $dashboardFilters['land_cover'] === $class['name'] ? 'bg-red-50' : '' }}" data-class-row="{{ $class['name'] }}">
                                <td class="px-3 py-3 font-bold text-slate-500">{{ $class['id'] }}</td>
                                <td class="px-3 py-3 font-bold text-slate-900">{{ $class['display_name'] }}</td>
                                <td class="px-3 py-3 text-right font-black">{{ number_format($class['count']) }}</td>
                                <td class="px-3 py-3 text-right">{{ number_format($class['share'], 2) }}%</td>
                                @foreach ($dashboardStatuses as $key => $status)
                                    <td class="px-3 py-3 text-right">{{ number_format($class['confidence'][$key]['count']) }} · {{ number_format($class['confidence'][$key]['share'], 1) }}%</td>
                                @endforeach
                                <td class="px-3 py-3 text-right">{{ $class['hotspot_count_2021_2023'] === null ? ($language === 'en' ? 'Unavailable' : 'Tidak tersedia') : number_format($class['hotspot_count_2021_2023']) }}</td>
                                <td class="px-3 py-3 text-right">{{ $class['hse'] === null ? ($language === 'en' ? 'Unavailable' : 'Tidak tersedia') : number_format($class['hse'], 3) }}</td>
                                <td class="px-3 py-3 text-right">{{ $class['hbe'] === null ? ($language === 'en' ? 'Unavailable' : 'Tidak tersedia') : number_format($class['hbe'], 3) }}</td>
                                <td class="px-3 py-3 text-right">{{ $class['ever_burn_rate_pre2024_percent'] === null ? '—' : number_format($class['ever_burn_rate_pre2024_percent'], 4).'%' }}</td>
                                <td class="px-3 py-3 text-right">{{ $class['mean_frequency_on_burned_footprint_pre2024'] === null ? '—' : number_format($class['mean_frequency_on_burned_footprint_pre2024'], 4) }}</td>
                                <td class="px-3 py-3 text-right">{{ $class['burn_rate_2024_percent'] === null ? '—' : number_format($class['burn_rate_2024_percent'], 4).'%' }}</td>
                                <td class="px-3 py-3 text-right">{{ $class['fsi'] === null ? ($language === 'en' ? 'Unavailable' : 'Tidak tersedia') : number_format($class['fsi'], 2) }}</td>
                                <td class="px-3 py-3">{{ $class['fsi_class_label'] ?? ($language === 'en' ? 'Unavailable' : 'Tidak tersedia') }}</td>
                                <td class="px-3 py-3 text-right">{{ $class['area_km2'] === null ? ($language === 'en' ? 'Unavailable' : 'Tidak tersedia') : number_format($class['area_km2'], 3) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </section>

    @endsection
