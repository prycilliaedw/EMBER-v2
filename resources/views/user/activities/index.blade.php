@extends('layouts.user')

@section('title', ($language === 'en' ? 'Activities' : 'Aktivitas').' - EMBER')

@section('content')
<section class="relative overflow-hidden bg-slate-950 px-4 py-20 text-white sm:px-6 lg:px-8">
    <div class="absolute -right-20 -top-20 size-80 rounded-full bg-red-600/15 blur-3xl"></div>
    <div class="relative mx-auto max-w-7xl">
        <p class="text-xs font-black uppercase tracking-[0.28em] text-red-400">{{ $language === 'en' ? 'Latest Updates' : 'Pembaruan Terkini' }}</p>
        <h1 class="mt-4 text-4xl font-black tracking-tight sm:text-6xl">{{ $language === 'en' ? 'EMBER Activities' : 'Aktivitas EMBER' }}</h1>
        <p class="mt-5 max-w-2xl text-base leading-7 text-slate-300">{{ $language === 'en' ? 'Follow field activities, data processing, and the latest environmental monitoring updates.' : 'Ikuti kegiatan lapangan, pengolahan data, dan perkembangan terbaru pemantauan lingkungan.' }}</p>
    </div>
</section>

<section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($activities as $activity)
            @php
                $image = $activity->{'image_'.$language};
                $description = $activity->{'description_'.$language};
            @endphp
            <article class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-slate-950/10">
                <a href="{{ route('user.activities.show', ['id' => $activity->id, 'lang' => $language]) }}" class="block overflow-hidden bg-slate-100">
                    <img src="{{ asset('storage/'.$image) }}" alt="{{ $description }}" class="aspect-[16/10] w-full object-cover transition duration-500 group-hover:scale-105">
                </a>
                <div class="p-6">
                    <time datetime="{{ $activity->date }}" class="inline-flex items-center gap-2 text-xs font-black uppercase tracking-widest text-red-600">
                        <span class="size-1.5 rounded-full bg-red-600"></span>
                        {{ \Illuminate\Support\Carbon::parse($activity->date)->locale($language)->translatedFormat('d F Y') }}
                    </time>
                    <h2 class="mt-4 line-clamp-3 text-xl font-black leading-7 text-slate-950">{{ $description }}</h2>
                    <a href="{{ route('user.activities.show', ['id' => $activity->id, 'lang' => $language]) }}" class="mt-6 inline-flex items-center gap-2 text-sm font-black text-slate-900 transition group-hover:text-red-600">
                        {{ $language === 'en' ? 'Read activity' : 'Baca aktivitas' }} <span aria-hidden="true">→</span>
                    </a>
                </div>
            </article>
        @empty
            <div class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500 sm:col-span-2 lg:col-span-3">{{ $language === 'en' ? 'No published activities yet.' : 'Belum ada aktivitas yang dipublikasikan.' }}</div>
        @endforelse
    </div>
</section>
@endsection
