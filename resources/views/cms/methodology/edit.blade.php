@extends('layouts.admin')

@section('title', 'Methodology - EMBER CMS')

@section('content')
    <div class="mx-auto max-w-7xl">
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-wider text-red-600">Konten dua bahasa</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">Methodology</h1>
            <p class="mt-2 text-sm text-slate-600">Kelola konten Methodology untuk bahasa Indonesia dan Inggris.</p>
        </div>

        <form method="POST" action="{{ route('cms.methodology.update') }}" class="bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8">
            @csrf
            @method('PUT')

            <div class="grid gap-px bg-slate-200 lg:grid-cols-2">
                @foreach (['id' => ['Indonesia', 'ID'], 'en' => ['English', 'EN']] as $locale => [$languageName, $code])
                    @php($contentField = 'content_'.$locale)
                    <section class="bg-white p-5 sm:p-6">
                        <div class="mb-5 flex items-center justify-between border-b border-slate-200 pb-4">
                            <h2 class="font-bold text-slate-900">{{ $languageName }}</h2>
                            <span class="bg-slate-900 px-2.5 py-1 text-xs font-bold text-white">{{ $code }}</span>
                        </div>

                        <div>
                            <label for="{{ $contentField }}" class="block text-sm font-semibold text-slate-700">Content {{ $code }}</label>
                            <div class="mt-2">
                                <x-tinymce :id="$contentField" :name="$contentField" :value="old($contentField, $methodology?->{$contentField})" />
                            </div>
                            @error($contentField)<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </section>
                @endforeach
            </div>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="border border-red-600 bg-red-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-red-500">Simpan Methodology</button>
            </div>
        </form>
    </div>
@endsection
