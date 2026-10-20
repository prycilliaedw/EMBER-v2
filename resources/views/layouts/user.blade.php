<!DOCTYPE html>
<html lang="{{ request('lang') === 'en' ? 'en' : 'id' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="EMBER - Early Monitoring for Burning Environment Response">
    <title>@yield('title', 'EMBER')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col bg-[#f7f8fa] text-slate-900 antialiased selection:bg-red-100 selection:text-red-900">
    @php($currentLanguage = request('lang') === 'en' ? 'en' : 'id')
    <div class="relative z-[70] bg-slate-950 px-4 py-2 text-center text-[10px] font-bold uppercase tracking-[0.2em] text-slate-300">
        <span class="mr-2 inline-block size-1.5 rounded-full bg-red-500 align-middle shadow-[0_0_10px_rgba(239,68,68,.9)]"></span>
        Early Monitoring for Burning Environment Response
    </div>
        <header class="relative z-[1000] border-b border-slate-200 bg-white shadow-[0_1px_12px_rgba(15,23,42,.04)]">        <nav class="mx-auto flex min-h-17 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8" aria-label="{{ $currentLanguage === 'en' ? 'Main navigation' : 'Navigasi utama' }}">
            <a href="{{ route('user.dashboard', ['lang' => $currentLanguage]) }}" class="flex shrink-0 items-center gap-3">
                <img src="{{ asset('images/ember-logo.png') }}" alt="EMBER - Early Monitoring for Burning Environment Response" class="h-16 w-16 shrink-0 object-contain sm:h-20 sm:w-20">
            </a>

            <div class="hidden items-center gap-1 rounded-xl bg-slate-100/80 p-1 text-xs font-bold text-slate-600 lg:flex">
                <a href="{{ route('user.dashboard', ['lang' => $currentLanguage]) }}" class="rounded-lg px-3 py-2 transition {{ request()->routeIs('user.dashboard') ? 'bg-white text-red-600 shadow-sm ring-1 ring-slate-200/70' : 'hover:bg-white/70 hover:text-slate-950' }}">{{ $currentLanguage === 'en' ? 'Home' : 'Beranda' }}</a>
                <details class="group relative">
                    <summary class="flex cursor-pointer list-none items-center gap-1.5 rounded-lg px-3 py-2 marker:content-none {{ request()->routeIs('user.map') || request()->routeIs('user.data.*') ? 'bg-white text-red-600 shadow-sm ring-1 ring-slate-200/70' : 'hover:bg-white/70 hover:text-slate-950' }}">
                        {{ $currentLanguage === 'en' ? 'Map & Data' : 'Peta & Data' }}
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" class="size-3 transition group-open:rotate-180" aria-hidden="true"><path d="m5 7.5 5 5 5-5"/></svg>
                    </summary>
                    <div class="absolute left-0 top-11 z-[90] w-52 overflow-hidden rounded-xl border border-slate-200 bg-white p-1.5 shadow-2xl shadow-slate-950/15">
                        <a href="{{ route('user.map', ['lang' => $currentLanguage]) }}" class="block rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:text-red-600">{{ $currentLanguage === 'en' ? 'Interactive Map' : 'Peta Interaktif' }}</a>
                        <a href="{{ route('user.data.index', ['lang' => $currentLanguage]) }}" class="block rounded-lg px-3 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:text-red-600">{{ $currentLanguage === 'en' ? 'Data Download' : 'Unduh Data' }}</a>
                    </div>
                </details>
                @foreach ([
                    ['user.statistics', $currentLanguage === 'en' ? 'Statistics' : 'Statistik'],
                    ['user.about', $currentLanguage === 'en' ? 'About' : 'Tentang'],
                    ['user.methodology', $currentLanguage === 'en' ? 'Methodology' : 'Metodologi'],
                    ['user.team', $currentLanguage === 'en' ? 'Team' : 'Tim'],
                    ['user.activities', $currentLanguage === 'en' ? 'Activities' : 'Aktivitas'],
                    ['user.faq', 'FAQ'],
                ] as [$routeName, $label])
                    <a href="{{ route($routeName, ['lang' => $currentLanguage]) }}" class="rounded-lg px-3 py-2 transition {{ request()->routeIs($routeName) ? 'bg-white text-red-600 shadow-sm ring-1 ring-slate-200/70' : 'hover:bg-white/70 hover:text-slate-950' }}">{{ $label }}</a>
                @endforeach
            </div>

            <div class="flex items-center gap-2">
                <div class="hidden overflow-hidden rounded-lg border border-slate-200 bg-slate-50 text-[11px] font-black sm:flex">
                    <a data-language-switch="id" href="{{ request()->fullUrlWithQuery(['lang' => 'id']) }}" class="px-2.5 py-2 {{ $currentLanguage === 'id' ? 'bg-slate-900 text-white' : 'text-slate-500 hover:bg-slate-50' }}">ID</a>
                    <a data-language-switch="en" href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}" class="px-2.5 py-2 {{ $currentLanguage === 'en' ? 'bg-slate-900 text-white' : 'text-slate-500 hover:bg-slate-50' }}">EN</a>
                </div>
                <form method="GET" action="{{ route('user.search') }}" class="hidden items-center overflow-hidden rounded-lg border border-slate-300 bg-white xl:flex">
                    <input type="hidden" name="lang" value="{{ $currentLanguage }}">
                    <label for="header-search" class="sr-only">{{ $currentLanguage === 'en' ? 'Search' : 'Cari' }}</label>
                    <input id="header-search" name="q" value="{{ request('q') }}" placeholder="{{ $currentLanguage === 'en' ? 'Search...' : 'Cari...' }}" class="w-32 border-0 px-3 py-2 text-xs outline-none placeholder:text-slate-400">
                    <button type="submit" class="flex size-9 items-center justify-center bg-slate-900 text-white" aria-label="{{ $currentLanguage === 'en' ? 'Search' : 'Cari' }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-4" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    </button>
                </form>

                <details class="group relative lg:hidden">
                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm marker:content-none">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-5" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </summary>
                    <div class="absolute right-0 top-12 z-[80] w-72 overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl shadow-slate-950/15">
                        <a href="{{ route('user.dashboard', ['lang' => $currentLanguage]) }}" class="block rounded-lg px-4 py-3 text-sm font-semibold hover:bg-slate-50">{{ $currentLanguage === 'en' ? 'Home' : 'Beranda' }}</a>
                        <details class="group">
                            <summary class="flex cursor-pointer list-none items-center justify-between rounded-lg px-4 py-3 text-sm font-semibold hover:bg-slate-50 marker:content-none">
                                {{ $currentLanguage === 'en' ? 'Map & Data' : 'Peta & Data' }}
                                <span class="transition group-open:rotate-180">⌄</span>
                            </summary>
                            <div class="mx-3 mb-2 border-l-2 border-red-200 pl-2">
                                <a href="{{ route('user.map', ['lang' => $currentLanguage]) }}" class="block rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-slate-50">{{ $currentLanguage === 'en' ? 'Interactive Map' : 'Peta Interaktif' }}</a>
                                <a href="{{ route('user.data.index', ['lang' => $currentLanguage]) }}" class="block rounded-lg px-3 py-2.5 text-sm font-semibold hover:bg-slate-50">{{ $currentLanguage === 'en' ? 'Data Download' : 'Unduh Data' }}</a>
                            </div>
                        </details>
                        <a href="{{ route('user.statistics', ['lang' => $currentLanguage]) }}" class="block rounded-lg px-4 py-3 text-sm font-semibold hover:bg-slate-50">{{ $currentLanguage === 'en' ? 'Statistics' : 'Statistik' }}</a>
                        <a href="{{ route('user.about', ['lang' => $currentLanguage]) }}" class="block rounded-lg px-4 py-3 text-sm font-semibold hover:bg-slate-50">{{ $currentLanguage === 'en' ? 'About' : 'Tentang' }}</a>
                        <a href="{{ route('user.methodology', ['lang' => $currentLanguage]) }}" class="block rounded-lg px-4 py-3 text-sm font-semibold hover:bg-slate-50">{{ $currentLanguage === 'en' ? 'Methodology' : 'Metodologi' }}</a>
                        <a href="{{ route('user.team', ['lang' => $currentLanguage]) }}" class="block rounded-lg px-4 py-3 text-sm font-semibold hover:bg-slate-50">{{ $currentLanguage === 'en' ? 'Team' : 'Tim' }}</a>
                        <a href="{{ route('user.activities', ['lang' => $currentLanguage]) }}" class="block rounded-lg px-4 py-3 text-sm font-semibold hover:bg-slate-50">{{ $currentLanguage === 'en' ? 'Activities' : 'Aktivitas' }}</a>
                        <a href="{{ route('user.faq', ['lang' => $currentLanguage]) }}" class="block rounded-lg px-4 py-3 text-sm font-semibold hover:bg-slate-50">FAQ</a>
                        <div class="mt-2 grid grid-cols-2 border-t border-slate-200 pt-2 text-center text-xs font-bold">
                            <a data-language-switch="id" href="{{ request()->fullUrlWithQuery(['lang' => 'id']) }}" class="px-3 py-2 {{ $currentLanguage === 'id' ? 'bg-slate-900 text-white' : '' }}">ID</a>
                            <a data-language-switch="en" href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}" class="px-3 py-2 {{ $currentLanguage === 'en' ? 'bg-slate-900 text-white' : '' }}">EN</a>
                        </div>
                        <form method="GET" action="{{ route('user.search') }}" class="mt-2 flex border-t border-slate-200 pt-2">
                            <input type="hidden" name="lang" value="{{ $currentLanguage }}">
                            <input name="q" value="{{ request('q') }}" placeholder="{{ $currentLanguage === 'en' ? 'Search...' : 'Cari...' }}" class="min-w-0 flex-1 border border-slate-300 px-3 py-2 text-sm outline-none">
                            <button type="submit" class="bg-slate-900 px-3 text-xs font-bold text-white">{{ $currentLanguage === 'en' ? 'Search' : 'Cari' }}</button>
                        </form>
                    </div>
                </details>
            </div>
        </nav>
    </header>

    <main class="flex-1">
        @yield('content')
    </main>

    @if (! View::hasSection('hideFooter'))
    <footer class="border-t border-slate-800 bg-slate-950 text-white">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-[1fr_auto] lg:px-8">
            <div>
                <div class="flex items-center gap-3"><span class="flex size-9 items-center justify-center rounded-xl bg-red-600 font-black">E</span><p class="font-black tracking-wide">EMBER</p></div>
                <p class="mt-3 max-w-md text-sm leading-6 text-slate-400">Early Monitoring for Burning Environment Response.</p>
                <p class="mt-4 text-xs text-slate-500">&copy; {{ date('Y') }} EMBER.</p>
            </div>
            <div class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm font-semibold text-slate-400 sm:grid-cols-3">
                <a href="{{ route('user.dashboard', ['lang' => $currentLanguage]) }}" class="hover:text-red-600">{{ $currentLanguage === 'en' ? 'Home' : 'Beranda' }}</a>
                <a href="{{ route('user.about', ['lang' => $currentLanguage]) }}" class="hover:text-red-600">{{ $currentLanguage === 'en' ? 'About' : 'Tentang' }}</a>
                <a href="{{ route('user.methodology', ['lang' => $currentLanguage]) }}" class="hover:text-red-600">{{ $currentLanguage === 'en' ? 'Methodology' : 'Metodologi' }}</a>
                <a href="{{ route('user.team', ['lang' => $currentLanguage]) }}" class="hover:text-red-600">{{ $currentLanguage === 'en' ? 'Team' : 'Tim' }}</a>
                <a href="{{ route('user.map', ['lang' => $currentLanguage]) }}" class="hover:text-red-600">{{ $currentLanguage === 'en' ? 'Map' : 'Peta' }}</a>
                <a href="{{ route('user.data.index', ['lang' => $currentLanguage]) }}" class="hover:text-red-600">{{ $currentLanguage === 'en' ? 'Data' : 'Data' }}</a>
                <a href="{{ route('user.statistics', ['lang' => $currentLanguage]) }}" class="hover:text-red-600">{{ $currentLanguage === 'en' ? 'Statistics' : 'Statistik' }}</a>
                <a href="{{ route('user.activities', ['lang' => $currentLanguage]) }}" class="hover:text-red-600">{{ $currentLanguage === 'en' ? 'Activities' : 'Aktivitas' }}</a>
                <a href="{{ route('user.faq', ['lang' => $currentLanguage]) }}" class="hover:text-red-600">FAQ</a>
                <a href="{{ route('user.search', ['lang' => $currentLanguage]) }}" class="hover:text-red-600">{{ $currentLanguage === 'en' ? 'Search' : 'Pencarian' }}</a>
            </div>
        </div>
    </footer>

    <button id="back-to-top" type="button" aria-label="{{ $currentLanguage === 'en' ? 'Back to top' : 'Kembali ke atas' }}" title="{{ $currentLanguage === 'en' ? 'Back to top' : 'Kembali ke atas' }}" class="pointer-events-none fixed bottom-5 right-5 z-50 flex size-12 translate-y-3 items-center justify-center rounded-full bg-red-600 text-white opacity-0 shadow-xl shadow-red-600/25 transition duration-200 hover:bg-red-500 focus:outline-none focus:ring-4 focus:ring-red-200 sm:bottom-7 sm:right-7">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" class="size-5" aria-hidden="true">
            <path d="m6 15 6-6 6 6"/>
        </svg>
    </button>
    @endif

    @livewireScripts
</body>
</html>
