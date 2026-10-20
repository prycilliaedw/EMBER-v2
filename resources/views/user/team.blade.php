@extends('layouts.user')

@section('title', ($language === 'en' ? 'Team' : 'Tim') . ' - EMBER')

@section('content')
    <section class="relative isolate overflow-hidden bg-slate-950 text-white">
        <div class="absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute left-1/2 top-12 size-[34rem] -translate-x-1/2 rounded-full bg-red-600/15 blur-3xl"></div>
            <div class="absolute -left-32 bottom-0 size-80 rounded-full bg-orange-500/10 blur-3xl"></div>
            <div class="absolute inset-0 opacity-[.04]" style="background-image:linear-gradient(rgba(255,255,255,.8) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.8) 1px,transparent 1px);background-size:52px 52px"></div>
        </div>

        <div class="mx-auto max-w-7xl px-4 pb-12 pt-12 text-center sm:px-6 lg:px-8">
            <div class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.2em] text-red-300">
            <span class="size-1.5 rounded-full bg-red-500"></span>
            {{ $language === 'en' ? 'EMBER People' : 'Tim EMBER' }}
            </div>
            <h1 class="mt-4 text-4xl font-black tracking-[-0.04em] sm:text-5xl">{{ $language === 'en' ? 'Meet the team behind EMBER' : 'Kenali tim di balik EMBER' }}</h1>
        </div>

        @if ($members->isNotEmpty())
            <div class="team-carousel pb-14" data-team-carousel data-team-count="{{ $members->count() }}">
                <div class="team-carousel-viewport" aria-label="{{ $language === 'en' ? 'EMBER team carousel' : 'Carousel tim EMBER' }}">
                    @foreach ($members as $member)
                        @php
                            $name = $member->nama ?: $member->name;
                            $description = $member->{'bio_'.$language} ?: $member->bio;
                        @endphp
                        <article class="team-carousel-card" data-team-card data-index="{{ $loop->index }}" data-position="{{ $loop->first ? 'active' : 'hidden' }}" tabindex="{{ $loop->first ? '0' : '-1' }}" aria-hidden="{{ $loop->first ? 'false' : 'true' }}">
                            @if ($member->photo)
                                <img src="{{ asset('storage/'.$member->photo) }}" alt="{{ $name }}" class="absolute inset-0 size-full object-cover">
                            @else
                                <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-slate-700 via-slate-900 to-red-950 text-[9rem] font-black text-white/10">{{ strtoupper(substr($name, 0, 1)) }}</div>
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-b from-slate-950/10 via-slate-950/5 to-slate-950/95"></div>
                            <div class="absolute inset-x-0 top-0 flex items-center justify-between p-5">
                                <span class="rounded-full border border-white/15 bg-slate-950/25 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.16em] text-white/80 backdrop-blur-md">
                                    {{ $language === 'en' ? 'EMBER Team' : 'Tim EMBER' }}
                                </span>
                                <span class="flex size-9 items-center justify-center rounded-full border border-white/15 bg-slate-950/25 text-xs font-bold backdrop-blur-md">{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            </div>
                            <div class="team-card-details absolute inset-x-0 bottom-0 p-6 sm:p-7">
                                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-red-400">NPM {{ $member->npm ?: '-' }}</p>
                                <h2 class="mt-2 text-3xl font-black tracking-tight text-white">{{ $name }}</h2>
                                @if ($description)
                                    <p class="mt-3 line-clamp-3 whitespace-pre-line text-sm leading-6 text-slate-300">{{ $description }}</p>
                                @endif
                                @if ($member->github_url)
                                    <a href="{{ $member->github_url }}" target="_blank" rel="noopener noreferrer" class="mt-5 inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-2.5 text-xs font-black text-white backdrop-blur transition hover:bg-white hover:text-slate-950">
                                        {{ $language === 'en' ? 'GitHub Repository' : 'Repository GitHub' }} <span aria-hidden="true">&nearr;</span>
                                    </a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="mx-auto mt-3 flex max-w-md items-center justify-center gap-4 px-4">
                    <button type="button" data-team-prev class="flex size-11 items-center justify-center rounded-full border border-white/15 bg-white/5 text-white transition hover:border-red-500 hover:bg-red-600" aria-label="{{ $language === 'en' ? 'Previous member' : 'Anggota sebelumnya' }}">
                        <span aria-hidden="true">&larr;</span>
                    </button>
                    <div class="min-w-24 rounded-full border border-white/10 bg-white/5 px-4 py-2 text-center text-xs font-bold text-slate-400">
                        <span data-team-current class="text-white">01</span> / {{ str_pad((string) $members->count(), 2, '0', STR_PAD_LEFT) }}
                    </div>
                    <button type="button" data-team-next class="flex size-11 items-center justify-center rounded-full border border-white/15 bg-white/5 text-white transition hover:border-red-500 hover:bg-red-600" aria-label="{{ $language === 'en' ? 'Next member' : 'Anggota berikutnya' }}">
                        <span aria-hidden="true">&rarr;</span>
                    </button>
                </div>
                <p class="mt-4 text-center text-[11px] font-semibold text-slate-500">{{ $language === 'en' ? 'Use the arrows or swipe the cards' : 'Gunakan tombol panah atau geser kartu' }}</p>
            </div>
        @else
            <div class="mx-auto max-w-3xl px-4 pb-20 text-center text-slate-400">{{ $language === 'en' ? 'Team information is not available yet.' : 'Informasi tim belum tersedia.' }}</div>
        @endif
    </section>
@endsection
