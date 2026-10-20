@extends('layouts.admin')

@php($editing = (bool) $activity)
@section('title', ($editing ? 'Edit' : 'Tambah').' Aktivitas - EMBER CMS')

@section('content')
<div class="mx-auto max-w-6xl">
    <a href="{{ route('cms.activities.index') }}" class="text-sm font-bold text-slate-500 hover:text-red-600">← Kembali ke Aktivitas</a>
    <div class="mt-5 border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
        <p class="text-xs font-black uppercase tracking-[0.24em] text-red-600">Konten Publik</p>
        <h1 class="mt-2 text-3xl font-black">{{ $editing ? 'Edit' : 'Tambah' }} Aktivitas</h1>

        @if ($errors->any())
            <div class="mt-5 border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <p class="font-bold">Periksa kembali data berikut:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('cms.activities.update', $activity->id) : route('cms.activities.store') }}" class="mt-7 space-y-7">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="grid gap-6 md:grid-cols-2">
                @foreach ([['id', 'Bahasa Indonesia'], ['en', 'English']] as [$locale, $label])
                    <fieldset class="space-y-5 border border-slate-200 p-5 sm:p-6">
                        <legend class="px-2 text-xs font-black uppercase tracking-widest text-red-600">{{ $label }}</legend>
                        @php($imageField = 'image_'.$locale)
                        @if ($editing && $activity->{$imageField})
                            <img src="{{ asset('storage/'.$activity->{$imageField}) }}" alt="" class="h-48 w-full object-cover">
                        @endif
                        <label class="block text-sm font-bold text-slate-700">Gambar {{ $label }}
                            <input type="file" name="{{ $imageField }}" accept="image/jpeg,image/png,image/webp" {{ $editing ? '' : 'required' }} class="mt-2 block w-full border border-slate-300 bg-slate-50 p-2 text-sm file:mr-3 file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:font-bold file:text-white">
                            <span class="mt-1 block text-xs font-normal text-slate-500">JPG, PNG, atau WebP. Maksimal 5 MB.</span>
                        </label>
                        <label class="block text-sm font-bold text-slate-700">Deskripsi
                            <textarea name="description_{{ $locale }}" rows="4" required class="mt-2 w-full border border-slate-300 px-3 py-2.5 outline-none focus:border-red-500">{{ old('description_'.$locale, $activity?->{'description_'.$locale}) }}</textarea>
                        </label>
                        <div>
                            <label for="content_{{ $locale }}" class="block text-sm font-bold text-slate-700">Konten</label>
                            <div class="mt-2">
                                <x-tinymce :id="'content_'.$locale" :name="'content_'.$locale" :value="old('content_'.$locale, $activity?->{'content_'.$locale})" />
                            </div>
                        </div>
                    </fieldset>
                @endforeach
            </div>

            <div class="grid gap-5 border-t border-slate-200 pt-6 md:grid-cols-2">
                <label class="block text-sm font-bold text-slate-700">Tanggal
                    <input type="date" name="date" required value="{{ old('date', $activity?->date) }}" class="mt-2 w-full border border-slate-300 px-3 py-3 outline-none focus:border-red-500">
                </label>
                <label class="block text-sm font-bold text-slate-700">Status
                    <select name="status" required class="mt-2 w-full border border-slate-300 bg-white px-3 py-3 outline-none focus:border-red-500">
                        <option value="draft" @selected(old('status', $activity?->status ?? 'draft') === 'draft')>Draft</option>
                        <option value="publish" @selected(old('status', $activity?->status) === 'publish')>Publish</option>
                    </select>
                </label>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-200 pt-6">
                <a href="{{ route('cms.activities.index') }}" class="border border-slate-300 px-5 py-3 text-sm font-bold">Batal</a>
                <button class="bg-red-600 px-6 py-3 text-sm font-black text-white hover:bg-red-700">{{ $editing ? 'Simpan Perubahan' : 'Simpan Aktivitas' }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
