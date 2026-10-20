@extends('layouts.user')

@section('title', ($language === 'en' ? 'Methodology' : 'Metodologi') . ' - EMBER')

@section('content')
    @php($content = trim((string) ($methodology?->{'content_' . $language} ?? '')))

    <section class="w-full max-w-none p-0 m-0">
        @if ($content !== '')
            <div class="methodology-document w-full max-w-none m-0 p-0">
                {!! $content !!}
            </div>
        @else
            <p class="m-0 bg-white p-8 text-base leading-7 text-slate-600">
                {{ $language === 'en'
                    ? 'Methodology content is not available yet.'
                    : 'Konten metodologi belum tersedia.' }}
            </p>
        @endif
    </section>
@endsection
