@extends('layouts.user')

@section('title', 'FAQ - EMBER')

@section('content')
<section class="relative overflow-hidden bg-slate-950 px-4 py-20 text-white sm:px-6 lg:px-8">
    <div class="absolute -right-28 -top-28 size-80 rounded-full bg-red-600/15 blur-3xl"></div>
    <div class="relative mx-auto max-w-7xl">
        <p class="text-xs font-black uppercase tracking-[0.28em] text-red-400">{{ $language === 'en' ? 'Help Center' : 'Pusat Bantuan' }}</p>
        <h1 class="mt-4 max-w-3xl text-4xl font-black tracking-tight sm:text-6xl">{{ $language === 'en' ? 'Frequently Asked Questions' : 'Pertanyaan yang Sering Diajukan' }}</h1>
        <p class="mt-5 max-w-2xl text-base leading-7 text-slate-300">{{ $language === 'en' ? 'Quick answers about EMBER data, monitoring, and platform usage.' : 'Jawaban ringkas seputar data, pemantauan, dan penggunaan platform EMBER.' }}</p>
    </div>
</section>

<section class="mx-auto max-w-5xl px-4 py-14 sm:px-6 lg:px-8">
    <div class="space-y-3">
        @forelse ($faqs as $index => $faq)
            @php
                $question = $faq->{'question_'.$language};
                $answer = $faq->{'answer_'.$language};
            @endphp
            <details class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition open:border-red-200 open:shadow-lg open:shadow-red-950/5">
                <summary class="flex cursor-pointer list-none items-center gap-4 px-5 py-5 marker:content-none sm:px-7">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xs font-black text-slate-500 transition group-open:bg-red-600 group-open:text-white">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="flex-1 text-base font-black text-slate-950 sm:text-lg">{{ $question }}</span>
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full border border-slate-200 text-xl text-slate-500 transition group-open:rotate-45 group-open:border-red-200 group-open:text-red-600">+</span>
                </summary>
                <div class="border-t border-slate-100 px-5 py-5 pl-[4.75rem] text-sm leading-7 text-slate-600 sm:px-7 sm:pl-[5.75rem]">
                    {!! nl2br(e($answer)) !!}
                </div>
            </details>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">{{ $language === 'en' ? 'FAQ is not available yet.' : 'FAQ belum tersedia.' }}</div>
        @endforelse
    </div>
</section>
@endsection
