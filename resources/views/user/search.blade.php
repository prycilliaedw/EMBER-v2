@extends('layouts.user')

@section('title', ($language === 'en' ? 'Search' : 'Pencarian') . ' - EMBER')

@section('content')
    <section class="bg-slate-950 px-4 py-14 text-white sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <p class="text-sm font-bold uppercase tracking-[0.2em] text-red-400">EMBER</p>
            <h1 class="mt-3 text-4xl font-bold tracking-tight">{{ $language === 'en' ? 'Search information' : 'Cari informasi' }}</h1>
            <form method="GET" action="{{ route('user.search') }}" class="mt-8 flex max-w-3xl bg-white p-1">
                <input type="hidden" name="lang" value="{{ $language }}">
                <label for="main-search" class="sr-only">{{ $language === 'en' ? 'Search' : 'Cari' }}</label>
                <input id="main-search" name="q" value="{{ $query }}" placeholder="{{ $language === 'en' ? 'Search location or team...' : 'Cari lokasi atau anggota tim...' }}" autofocus class="min-w-0 flex-1 border-0 px-4 py-3 text-sm text-slate-900 outline-none">
                <button type="submit" class="bg-red-600 px-6 py-3 text-sm font-bold text-white hover:bg-red-500">{{ $language === 'en' ? 'Search' : 'Cari' }}</button>
            </form>
        </div>
    </section>

    <section class="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
        @if ($query === '')
            <div class="border border-slate-200 bg-white p-8 text-slate-500">{{ $language === 'en' ? 'Enter a keyword to start searching.' : 'Masukkan kata kunci untuk mulai mencari.' }}</div>
        @else
            <div class="flex items-end justify-between border-b border-slate-200 pb-5">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-red-600">{{ $language === 'en' ? 'Search results' : 'Hasil pencarian' }}</p>
                    <h2 class="mt-2 text-2xl font-bold text-slate-950">“{{ $query }}”</h2>
                </div>
                <p class="text-sm text-slate-500">{{ $locations->count() + $members->count() }} {{ $language === 'en' ? 'results' : 'hasil' }}</p>
            </div>

            <div class="mt-8 space-y-10">
                <section>
                    <h3 class="text-lg font-bold text-slate-950">{{ $language === 'en' ? 'Locations' : 'Titik Lokasi' }} <span class="text-slate-400">({{ $locations->count() }})</span></h3>
                    <div class="mt-4 divide-y divide-slate-100 border border-slate-200 bg-white">
                        @forelse ($locations as $location)
                            <a href="{{ route('user.locations.show', ['id' => $location->id, 'lang' => $language]) }}" class="flex items-center justify-between gap-4 p-5 transition hover:bg-slate-50">
                                <div>
                                    <p class="font-bold text-slate-900">{{ $location->desa ?: ($language === 'en' ? 'Village not available' : 'Desa belum tersedia') }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ collect([$location->kecamatan, $location->kabupaten_kota, $location->provinsi])->filter()->join(', ') ?: '-' }}</p>
                                </div>
                                <span class="text-red-600">&rarr;</span>
                            </a>
                        @empty
                            <p class="p-5 text-sm text-slate-500">{{ $language === 'en' ? 'No locations found.' : 'Lokasi tidak ditemukan.' }}</p>
                        @endforelse
                    </div>
                </section>

                <section>
                <h3 class="text-lg font-bold text-slate-950">
                    {{ $language === 'en' ? 'Team' : 'Tim' }}
                    <span class="text-slate-400">({{ $members->count() }})</span>
                </h3>
                    <div class="mt-4 grid gap-px bg-slate-200 sm:grid-cols-2">
                        @forelse ($members as $member)
                            <article class="bg-white p-5">
                                <p class="font-bold text-slate-900">{{ $member->{'name_'.$language} ?: $member->name }}</p>
                                <p class="mt-1 text-sm text-red-600">{{ $member->{'position_'.$language} ?: $member->position }}</p>
                            </article>
                        @empty
                            <p class="bg-white p-5 text-sm text-slate-500 sm:col-span-2">{{ $language === 'en' ? 'No team members found.' : 'Anggota tim tidak ditemukan.' }}</p>
                        @endforelse
                    </div>
                </section>
            </div>
        @endif
    </section>
@endsection
