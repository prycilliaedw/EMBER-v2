@extends('layouts.admin')

@section('title', 'Reference Foto - EMBER CMS')

@section('content')
    <div class="mx-auto max-w-7xl">
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-wider text-red-600">Pustaka media</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">Reference Foto</h1>
            <p class="mt-2 text-sm text-slate-600">Simpan foto dan salin URL-nya untuk digunakan pada konten Team.</p>
        </div>

        <div class="grid gap-6 xl:grid-cols-[360px_1fr]">
            <form method="POST" action="{{ route('cms.references.store') }}" enctype="multipart/form-data" class="h-fit bg-white p-6 shadow-sm ring-1 ring-slate-200">
                @csrf
                <h2 class="text-lg font-bold text-slate-900">Upload foto</h2>

                <div class="mt-5 space-y-4">
                    <div>
                        <label for="title" class="block text-sm font-semibold text-slate-700">Judul foto</label>
                        <input id="title" name="title" value="{{ old('title') }}" required class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                        @error('title')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="alt_text" class="block text-sm font-semibold text-slate-700">Alt text</label>
                        <input id="alt_text" name="alt_text" value="{{ old('alt_text') }}" placeholder="Deskripsi singkat foto" class="mt-2 w-full border border-slate-300 px-4 py-3 text-sm focus:border-red-500 focus:outline-none focus:ring-4 focus:ring-red-100">
                        @error('alt_text')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="photo" class="block text-sm font-semibold text-slate-700">File foto</label>
                        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required class="mt-2 block w-full border border-slate-300 bg-white p-3 text-sm text-slate-600 file:mr-4 file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-xs file:font-bold file:text-white">
                        <p class="mt-2 text-xs leading-5 text-slate-500">Format JPG, PNG, atau WebP. Maksimal 5 MB.</p>
                        @error('photo')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <button type="submit" class="mt-5 w-full border border-red-600 bg-red-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-red-500">Simpan Foto</button>
            </form>

            <div>
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900">Foto tersimpan</h2>
                    <span class="bg-slate-900 px-3 py-1.5 text-xs font-bold text-white">{{ $photos->count() }} foto</span>
                </div>

                <div class="grid gap-px bg-slate-200 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($photos as $photo)
                        @php($photoUrl = asset('storage/'.$photo->photo_path))
                        <article class="bg-white">
                            <img src="{{ $photoUrl }}" alt="{{ $photo->alt_text ?: $photo->title }}" class="aspect-[4/3] w-full bg-slate-100 object-cover">
                            <div class="p-4">
                                <h3 class="truncate font-bold text-slate-900">{{ $photo->title }}</h3>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ $photo->original_name }} · {{ number_format($photo->file_size / 1024, 1) }} KB</p>

                                <label for="photo-url-{{ $photo->id }}" class="mt-4 block text-xs font-semibold text-slate-600">URL foto</label>
                                <input id="photo-url-{{ $photo->id }}" value="{{ $photoUrl }}" readonly class="mt-1 w-full border border-slate-300 bg-slate-50 px-3 py-2 text-xs text-slate-600">

                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <button type="button" data-copy-url="{{ $photoUrl }}" class="border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50">Salin URL</button>
                                    <button type="button" onclick="useForTeam('{{ $photoUrl }}')" class="border border-red-300 px-3 py-2 text-xs font-bold text-red-700 transition hover:bg-red-50">Pilih</button>
                                </div>
                                <form method="POST" action="{{ route('cms.references.destroy', $photo->id) }}" onsubmit="return confirm('Hapus foto referensi ini?')" class="mt-2">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-full border border-red-200 px-3 py-2 text-xs font-bold text-red-600 transition hover:bg-red-50">Hapus</button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <div class="bg-white px-6 py-16 text-center text-sm text-slate-500 sm:col-span-2 lg:col-span-3">Belum ada foto referensi.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <script>
        function useForTeam(url) {
            if (window.opener && typeof window.opener.useReferencePhoto === 'function') {
                window.opener.useReferencePhoto(url);
                window.close();
            } else {
                alert('Buka halaman ini dari halaman CMS Team untuk menggunakan fitur pilih.');
            }
        }
    </script>
@endsection
