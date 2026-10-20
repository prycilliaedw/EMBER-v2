@extends('layouts.admin')

@section('title', 'Edit FAQ - EMBER CMS')

@section('content')
<div class="mx-auto max-w-4xl">
    <a href="{{ route('cms.faq.index') }}" class="text-sm font-bold text-slate-500 hover:text-red-600">← Kembali ke FAQ</a>
    <div class="mt-5 border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
        <p class="text-xs font-black uppercase tracking-[0.24em] text-red-600">Konten Publik</p>
        <h1 class="mt-2 text-3xl font-black">Edit FAQ</h1>

        @if ($errors->any())
            <div class="mt-5 border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('cms.faq.update', $faq->id) }}" class="mt-7 grid gap-6 md:grid-cols-2">
            @csrf @method('PUT')
            @foreach ([['id', 'Bahasa Indonesia'], ['en', 'English']] as [$locale, $label])
                <fieldset class="space-y-4 border border-slate-200 p-5">
                    <legend class="px-2 text-xs font-black uppercase tracking-widest text-red-600">{{ $label }}</legend>
                    <label class="block text-sm font-bold text-slate-700">Pertanyaan
                        <textarea name="question_{{ $locale }}" rows="3" required class="mt-2 w-full border border-slate-300 px-3 py-2.5 outline-none focus:border-red-500">{{ old('question_'.$locale, $faq->{'question_'.$locale}) }}</textarea>
                    </label>
                    <label class="block text-sm font-bold text-slate-700">Jawaban
                        <textarea name="answer_{{ $locale }}" rows="8" required class="mt-2 w-full border border-slate-300 px-3 py-2.5 outline-none focus:border-red-500">{{ old('answer_'.$locale, $faq->{'answer_'.$locale}) }}</textarea>
                    </label>
                </fieldset>
            @endforeach
            <div class="flex justify-end gap-3 md:col-span-2">
                <a href="{{ route('cms.faq.index') }}" class="border border-slate-300 px-5 py-3 text-sm font-bold">Batal</a>
                <button class="bg-red-600 px-6 py-3 text-sm font-black text-white hover:bg-red-700">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endsection
