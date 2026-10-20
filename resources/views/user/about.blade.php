@extends('layouts.user')

@section('title', ($language === 'en' ? 'About' : 'Tentang') . ' - EMBER')

@section('content')

    @php
        $content = $about?->{'content_' . $language};
    @endphp

    <section class="w-full max-w-none p-0 m-0">
        @if ($about)

            <div class="rich-content w-full max-w-none m-0 p-0 text-lg leading-8 text-slate-700">
                {!! $content ?: e(
                    $language === 'en'
                        ? 'About content is not available yet.'
                        : 'Konten About belum tersedia.'
                ) !!}
            </div>

        @else

            <p class="m-0 border border-slate-200 bg-white p-8 text-slate-500">
                {{ $language === 'en'
                    ? 'About content is not available yet.'
                    : 'Konten About belum tersedia.' }}
            </p>

        @endif
    </section>

@endsection