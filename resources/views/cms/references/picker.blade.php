@extends('layouts.admin')

@section('title', 'Pilih Foto - EMBER CMS')

@section('content')
    <div class="mx-auto max-w-7xl">
        <div class="mb-6">
            <p class="text-sm font-semibold uppercase tracking-wider text-red-600">Pustaka media</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">Pilih Foto untuk Team</h1>
            <p class="mt-2 text-sm text-slate-600">Klik <strong>Pilih</strong> pada foto yang ingin digunakan sebagai photo anggota tim.</p>
        </div>

        @if ($photos->isEmpty())
            <div class="bg-white px-6 py-16 text-center text-sm text-slate-500">Belum ada foto referensi. Upload foto terlebih dahulu di halaman Reference Foto.</div>
        @else
            <div class="grid gap-px bg-slate-200 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($photos as $photo)
                    @php($photoUrl = asset('storage/'.$photo->photo_path))
                    <article class="bg-white">
                        <a href="{{ $photoUrl }}" target="_blank" rel="noopener" class="block">
                            <img src="{{ $photoUrl }}" alt="{{ $photo->alt_text ?: $photo->title }}" class="aspect-[4/3] w-full bg-slate-100 object-cover">
                        </a>
                        <div class="p-4">
                            <h3 class="truncate font-bold text-slate-900">{{ $photo->title }}</h3>
                            <p class="mt-1 truncate text-xs text-slate-500">{{ $photo->original_name }} · {{ number_format($photo->file_size / 1024, 1) }} KB</p>
                            <button type="button" onclick="useForTeam('{{ $photoUrl }}')" class="mt-4 w-full border border-red-600 bg-red-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-red-500">Pilih</button>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
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
