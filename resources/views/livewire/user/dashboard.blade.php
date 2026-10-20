<div>
    @php
        $t = [
            'id' => [
                'eyebrow' => 'Portal pemantauan lingkungan',
                'headline' => 'Data kebakaran hutan dalam satu tampilan.',
                'intro' => 'Pantau persebaran lokasi, tingkat confidence, dan perkembangan data terbaru secara cepat, terbuka, dan mudah dipahami.',
                'map_cta' => 'Buka peta interaktif', 'stats_cta' => 'Lihat statistik',
                'total_locations' => 'Total lokasi', 'monitored_points' => 'titik terpantau',
                'total_alerts' => 'Data terklasifikasi', 'with_confidence' => 'memiliki confidence',
                'high_alerts' => 'Confidence tinggi', 'critical' => 'perlu perhatian',
                'last_updated' => 'Pembaruan terakhir', 'not_available' => 'Belum tersedia', 'latest_data' => 'data terbaru',
                'data_overview' => 'Ringkasan data', 'overview_title' => 'Kondisi terkini dalam angka',
                'overview_desc' => 'Ringkasan otomatis dari seluruh titik yang sudah tersimpan di sistem EMBER.',
                'latest' => 'Lokasi terbaru', 'latest_title' => 'Data yang baru ditambahkan',
                'latest_desc' => 'Akses cepat ke titik pemantauan terbaru beserta status confidence-nya.',
                'open_detail' => 'Lihat detail', 'view_all' => 'Lihat seluruh peta', 'unknown_village' => 'Desa belum tersedia',
                'explore' => 'Kenali EMBER', 'explore_title' => 'Data terbuka, metode yang jelas',
                'about_desc' => 'Kenali tujuan, visi, dan misi di balik sistem pemantauan EMBER.',
                'method_desc' => 'Pelajari sumber data, proses pengolahan, dan klasifikasi confidence.',
                'team_desc' => 'Kenali tim yang mengelola data dan informasi pada portal EMBER.',
                'read_more' => 'Selengkapnya', 'about_title' => 'Tentang', 'method_title' => 'Metodologi', 'team_title' => 'Tim',
                'no_latest' => 'Belum ada lokasi terbaru untuk ditampilkan.',
            ],
            'en' => [
                'eyebrow' => 'Environmental monitoring portal',
                'headline' => 'Forest fire data in one clear view.',
                'intro' => 'Monitor location distribution, confidence levels, and the latest data developments quickly, openly, and clearly.',
                'map_cta' => 'Open interactive map', 'stats_cta' => 'View statistics',
                'total_locations' => 'Total locations', 'monitored_points' => 'monitored points',
                'total_alerts' => 'Classified data', 'with_confidence' => 'have confidence',
                'high_alerts' => 'High confidence', 'critical' => 'need attention',
                'last_updated' => 'Last updated', 'not_available' => 'Not available', 'latest_data' => 'latest data',
                'data_overview' => 'Data overview', 'overview_title' => 'The latest condition at a glance',
                'overview_desc' => 'An automatic overview of every monitoring point stored in EMBER.',
                'latest' => 'Latest locations', 'latest_title' => 'Recently added data',
                'latest_desc' => 'Quick access to the latest monitoring points and their confidence status.',
                'open_detail' => 'View details', 'view_all' => 'View full map', 'unknown_village' => 'Village not available',
                'explore' => 'Discover EMBER', 'explore_title' => 'Open data, transparent methods',
                'about_desc' => 'Discover the purpose, vision, and mission behind the EMBER monitoring system.',
                'method_desc' => 'Learn about data sources, processing methods, and confidence classification.',
                'team_desc' => 'Meet the team managing data and information across the EMBER portal.',
                'read_more' => 'Learn more', 'about_title' => 'About', 'method_title' => 'Methodology', 'team_title' => 'Team',
                'no_latest' => 'No recent locations are available yet.',
            ],
        ][$language];

        $statusMeta = function ($confidence) use ($language) {
            $value = strtolower(trim((string) $confidence));

            if ($value === '') return ['label' => $language === 'en' ? 'Unrated' : 'Belum dinilai', 'class' => 'bg-slate-100 text-slate-600 ring-slate-200'];
            if (in_array($value, ['high', 'tinggi'], true) || (is_numeric($value) && (float) $value >= 80)) return ['label' => $language === 'en' ? 'High' : 'Tinggi', 'class' => 'bg-red-50 text-red-700 ring-red-200'];
            if (in_array($value, ['nominal', 'medium', 'sedang'], true) || (is_numeric($value) && (float) $value >= 50)) return ['label' => $language === 'en' ? 'Medium' : 'Sedang', 'class' => 'bg-amber-50 text-amber-700 ring-amber-200'];
            return ['label' => $language === 'en' ? 'Low' : 'Rendah', 'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'];
        };

        $formattedLatestDate = $latestDate
            ? \Illuminate\Support\Carbon::parse($latestDate)->locale($language)->translatedFormat('d M Y')
            : $t['not_available'];
    @endphp

    <section class="relative isolate min-h-[680px] overflow-hidden bg-slate-950 text-white">
        <img src="{{ asset('images/ember-hero-v2.jpg') }}" alt="" class="ember-hero-image absolute inset-0 -z-20 size-full object-cover" fetchpriority="high">
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-slate-950/35 via-slate-950/20 to-slate-950/95" aria-hidden="true"></div>
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950/60 via-transparent to-slate-950/25" aria-hidden="true"></div>

        <div class="mx-auto flex min-h-[680px] max-w-7xl flex-col px-4 pb-7 pt-12 sm:px-6 sm:pb-9 lg:px-8">
            <div class="flex flex-1 items-center justify-center py-12 text-center">
                <div class="ember-hero-brand">
                    <h1 class="text-6xl font-black tracking-[-0.06em] text-white drop-shadow-2xl sm:text-7xl lg:text-8xl">EMBER</h1>
                    <p class="mx-auto mt-3 max-w-lg text-xs font-bold uppercase tracking-[0.2em] text-white/70 sm:text-sm">Early Monitoring for Burning Environment Response</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_440px] lg:items-end">
                <div class="ember-hero-copy max-w-xl">
                    <p class="text-sm leading-6 text-slate-200 sm:text-base sm:leading-7">{{ $t['intro'] }}</p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <a href="{{ route('user.map', ['lang' => $language]) }}" class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-black/25 transition hover:-translate-y-0.5 hover:bg-red-500 focus:outline-none focus:ring-4 focus:ring-red-400/30">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4" aria-hidden="true"><path d="m9 18-6-3V5l6 3 6-3 6 3v10l-6-3-6 3Z"/><path d="M9 8v10m6-13v10"/></svg>
                            {{ $t['map_cta'] }}
                        </a>
                        <a href="{{ route('user.statistics', ['lang' => $language]) }}" class="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-slate-950/25 px-5 py-3 text-sm font-bold text-white backdrop-blur-md transition hover:-translate-y-0.5 hover:bg-white/10">
                            {{ $t['stats_cta'] }} <span aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                </div>

                <div class="ember-hero-stats grid grid-cols-2 gap-2 rounded-2xl border border-white/10 bg-slate-950/30 p-2 shadow-2xl shadow-black/20 backdrop-blur-xl">
                    @foreach ([
                        [$t['total_locations'], number_format($totalLocations), 'text-white'],
                        [$t['total_alerts'], number_format($totalWarnings), 'text-white'],
                        [$t['high_alerts'], number_format($highWarnings), 'text-red-300'],
                        [$t['last_updated'], $formattedLatestDate, 'text-emerald-300'],
                    ] as [$label, $value, $accent])
                        <div class="ember-stat-card rounded-xl bg-white/[.07] px-3.5 py-3 ring-1 ring-inset ring-white/[.06]">
                            <p class="text-[10px] font-semibold text-slate-400">{{ $label }}</p>
                            <p class="mt-1.5 {{ strlen((string) $value) > 8 ? 'text-sm' : 'text-xl' }} font-black tracking-tight {{ $accent }}">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="relative isolate overflow-hidden border-b border-slate-200/70 bg-[#f6f7f9]">
        <div class="absolute -left-48 top-10 -z-10 size-96 rounded-full bg-red-100/70 blur-3xl" aria-hidden="true"></div>
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-14 sm:px-6 lg:grid-cols-[.72fr_1.28fr] lg:items-center lg:gap-16 lg:px-8 lg:py-20">
            <div class="relative" data-reveal="left">
                <span class="absolute -left-4 -top-14 -z-10 text-[9rem] font-black leading-none text-white/80" aria-hidden="true">01</span>
                <div class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.2em] text-red-600 ring-1 ring-inset ring-red-100">
                    <span class="size-1.5 rounded-full bg-red-500"></span>{{ $t['latest'] }}
                </div>
                <h2 class="mt-4 max-w-md text-3xl font-black leading-tight tracking-[-0.03em] text-slate-950 sm:text-4xl">{{ $t['latest_title'] }}</h2>
                <p class="mt-4 max-w-md text-sm leading-7 text-slate-600">{{ $t['latest_desc'] }}</p>
                <a href="{{ route('user.map', ['lang' => $language]) }}" class="group mt-7 inline-flex items-center gap-3 rounded-xl bg-slate-950 px-4 py-3 text-sm font-black text-white shadow-lg shadow-slate-950/10 transition hover:-translate-y-0.5 hover:bg-red-600">
                    {{ $t['view_all'] }} <span class="transition group-hover:translate-x-1" aria-hidden="true">&rarr;</span>
                </a>
            </div>

            <div class="rounded-[1.75rem] border border-slate-200/80 bg-white/80 p-2.5 shadow-[0_20px_60px_rgba(15,23,42,.08)] backdrop-blur" data-reveal="right">
                @forelse ($latestLocations as $location)
                    @php($meta = $statusMeta($location->confidence))
                    <a href="{{ route('user.locations.show', ['id' => $location->id, 'lang' => $language]) }}" class="group grid gap-3 rounded-2xl p-3.5 transition hover:bg-slate-50 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:px-4" data-reveal data-reveal-delay="{{ $loop->index * 70 }}">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200/60 transition group-hover:bg-red-600 group-hover:text-white group-hover:ring-red-600">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4.5" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-[15px] font-black text-slate-950">{{ $location->desa ?: $t['unknown_village'] }}</p>
                                <p class="mt-1 truncate text-xs font-medium text-slate-500">{{ collect([$location->kecamatan, $location->kabupaten_kota, $location->provinsi])->filter()->join(', ') ?: '-' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between gap-3 pl-12 sm:justify-end sm:pl-0">
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-black ring-1 ring-inset {{ $meta['class'] }}">{{ $meta['label'] }}</span>
                            <span class="flex size-8 items-center justify-center rounded-full text-sm font-bold text-slate-300 transition group-hover:translate-x-0.5 group-hover:bg-white group-hover:text-red-600 group-hover:shadow-sm" aria-hidden="true">&rarr;</span>
                        </div>
                    </a>
                @empty
                    <p class="p-8 text-center text-sm text-slate-500">{{ $t['no_latest'] }}</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="relative isolate overflow-hidden bg-white">
        <div class="absolute right-0 top-0 -z-10 h-full w-1/2 bg-gradient-to-bl from-red-50/70 via-transparent to-transparent" aria-hidden="true"></div>
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-20">
            <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end" data-reveal>
                <div class="max-w-2xl">
                    <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.2em] text-slate-600">
                        <span class="size-1.5 rounded-full bg-red-500"></span>{{ $t['explore'] }}
                    </div>
                    <h2 class="mt-4 text-3xl font-black tracking-[-0.03em] text-slate-950 sm:text-4xl">{{ $t['explore_title'] }}</h2>
                </div>
                <p class="max-w-sm text-sm leading-6 text-slate-500">{{ $language === 'en' ? 'Explore how EMBER works, where the data comes from, and who manages it.' : 'Pelajari cara kerja EMBER, asal data, dan siapa yang mengelolanya.' }}</p>
            </div>
            <div class="mt-9 grid gap-5 md:grid-cols-3">
                @foreach ([
                    ['01', $t['about_title'], $t['about_desc'], route('user.about', ['lang' => $language]), 'M4 19V9l8-5 8 5v10H4Z M9 19v-6h6v6'],
                    ['02', $t['method_title'], $t['method_desc'], route('user.methodology', ['lang' => $language]), 'M5 4h14v16H5z M9 8h6M9 12h6M9 16h4'],
                    ['03', $t['team_title'], $t['team_desc'], route('user.team', ['lang' => $language]), 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z M17 11l2 2 3-3'],
                ] as [$number, $title, $description, $url, $icon])
                    <a href="{{ $url }}" class="motion-lift group relative min-h-72 overflow-hidden rounded-3xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-6 shadow-sm transition duration-300 hover:border-red-200 hover:shadow-2xl hover:shadow-slate-950/[.08] sm:p-7" data-reveal data-reveal-delay="{{ $loop->index * 110 }}">
                        <span class="absolute -right-3 -top-8 text-[8rem] font-black leading-none text-slate-100 transition duration-300 group-hover:text-red-50" aria-hidden="true">{{ $number }}</span>
                        <div class="relative flex items-center justify-between">
                            <span class="flex size-12 items-center justify-center rounded-2xl bg-white text-red-600 shadow-md shadow-slate-950/[.05] ring-1 ring-slate-200 transition group-hover:rotate-[-4deg] group-hover:bg-red-600 group-hover:text-white group-hover:ring-red-600"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5" aria-hidden="true"><path d="{{ $icon }}"/></svg></span>
                            <span class="flex size-9 items-center justify-center rounded-full bg-white text-slate-300 shadow-sm transition group-hover:bg-slate-950 group-hover:text-white" aria-hidden="true">&nearr;</span>
                        </div>
                        <div class="relative mt-12">
                            <h3 class="text-2xl font-black tracking-tight text-slate-950">{{ $title }}</h3>
                            <p class="mt-3 text-sm leading-6 text-slate-600">{{ $description }}</p>
                            <span class="mt-6 inline-flex items-center gap-2 text-xs font-black uppercase tracking-wider text-slate-900 transition group-hover:text-red-600">{{ $t['read_more'] }} <span class="transition group-hover:translate-x-1" aria-hidden="true">&rarr;</span></span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
</div>
