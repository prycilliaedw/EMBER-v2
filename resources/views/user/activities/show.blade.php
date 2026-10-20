@extends('layouts.user')

@section('title', ($language === 'en' ? 'Activity' : 'Aktivitas').' - EMBER')

@section('content')
@php
    $image = $activity->{'image_'.$language};
    $description = $activity->{'description_'.$language};
    $content = $activity->{'content_'.$language};
@endphp

<article>
    <header class="bg-slate-950 px-4 py-14 text-white sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <a href="{{ route('user.activities', ['lang' => $language]) }}" class="text-sm font-bold text-slate-400 transition hover:text-red-400">← {{ $language === 'en' ? 'All activities' : 'Semua aktivitas' }}</a>
            <time datetime="{{ $activity->date }}" class="mt-8 block text-xs font-black uppercase tracking-[0.22em] text-red-400">{{ \Illuminate\Support\Carbon::parse($activity->date)->locale($language)->translatedFormat('d F Y') }}</time>
            <h1 class="mt-4 max-w-4xl text-3xl font-black leading-tight tracking-tight sm:text-5xl">{{ $description }}</h1>
        </div>
    </header>

    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        <img src="{{ asset('storage/'.$image) }}" alt="{{ $description }}" class="max-h-[680px] w-full rounded-3xl object-cover shadow-2xl shadow-slate-950/10">
        <div class="mx-auto mt-12 max-w-4xl">
            <div class="rich-content text-base leading-8 text-slate-700 sm:text-lg">{!! $content !!}</div>
            <div class="mt-12 border-t border-slate-200 pt-8">
                <a href="{{ route('user.activities', ['lang' => $language]) }}" class="inline-flex items-center gap-2 rounded-xl bg-slate-950 px-5 py-3 text-sm font-black text-white transition hover:bg-red-600">← {{ $language === 'en' ? 'Back to activities' : 'Kembali ke aktivitas' }}</a>
            </div>
        </div>
    </div>
</article>
@endsection
