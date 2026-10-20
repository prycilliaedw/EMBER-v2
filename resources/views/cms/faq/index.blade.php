@extends('layouts.admin')

@section('title', 'FAQ - EMBER CMS')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div>
        <p class="text-xs font-black uppercase tracking-[0.24em] text-red-600">Konten Publik</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">FAQ</h1>
        <p class="mt-2 text-sm text-slate-600">Kelola pertanyaan dan jawaban dalam Bahasa Indonesia dan Inggris.</p>
    </div>

    @if ($errors->any())
        <div class="border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <p class="font-bold">Periksa kembali data berikut:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
        <form method="POST" action="{{ route('cms.faq.store') }}" class="h-fit border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            @csrf
            <h2 class="text-lg font-black">Tambah FAQ</h2>
            <div class="mt-5 space-y-5">
                @foreach ([
                    ['id', 'Bahasa Indonesia'],
                    ['en', 'English'],
                ] as [$locale, $label])
                    <fieldset class="space-y-3 border-t border-slate-200 pt-4 first:border-0 first:pt-0">
                        <legend class="text-xs font-black uppercase tracking-widest text-red-600">{{ $label }}</legend>
                        <label class="block text-sm font-bold text-slate-700">Pertanyaan
                            <textarea name="question_{{ $locale }}" rows="2" required class="mt-2 w-full border border-slate-300 px-3 py-2.5 outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-100">{{ old('question_'.$locale) }}</textarea>
                        </label>
                        <label class="block text-sm font-bold text-slate-700">Jawaban
                            <textarea name="answer_{{ $locale }}" rows="5" required class="mt-2 w-full border border-slate-300 px-3 py-2.5 outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-100">{{ old('answer_'.$locale) }}</textarea>
                        </label>
                    </fieldset>
                @endforeach
            </div>
            <button class="mt-6 w-full bg-red-600 px-5 py-3 text-sm font-black text-white transition hover:bg-red-700">Simpan FAQ</button>
        </form>

        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-black">Daftar FAQ</h2>
                <span class="bg-slate-900 px-3 py-1 text-xs font-bold text-white">{{ $faqs->count() }} data</span>
            </div>
            @forelse ($faqs as $faq)
                <article class="border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-widest text-red-600">Indonesia</p>
                            <h3 class="mt-1 font-black text-slate-950">{{ $faq->question_id }}</h3>
                            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $faq->answer_id }}</p>
                            <div class="mt-4 border-t border-slate-100 pt-4">
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">English</p>
                                <h3 class="mt-1 font-bold text-slate-800">{{ $faq->question_en }}</h3>
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <a href="{{ route('cms.faq.edit', $faq->id) }}" class="border border-slate-300 px-3 py-2 text-xs font-bold hover:border-slate-950">Edit</a>
                            <form method="POST" action="{{ route('cms.faq.destroy', $faq->id) }}" onsubmit="return confirm('Hapus FAQ ini?')">
                                @csrf @method('DELETE')
                                <button class="border border-red-200 px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50">Hapus</button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Belum ada FAQ.</div>
            @endforelse
        </section>
    </div>
</div>
@endsection
