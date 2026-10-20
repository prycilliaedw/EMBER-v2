@extends('layouts.admin')

@section('title', 'Aktivitas - EMBER CMS')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.24em] text-red-600">Konten Publik</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight">Aktivitas</h1>
            <p class="mt-2 text-sm text-slate-600">Kelola dokumentasi aktivitas bilingual beserta status publikasinya.</p>
        </div>
        <a href="{{ route('cms.activities.create') }}" class="bg-red-600 px-5 py-3 text-sm font-black text-white hover:bg-red-700">+ Tambah Aktivitas</a>
    </div>

    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($activities as $activity)
            <article class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div class="grid h-44 grid-cols-2 bg-slate-100">
                    @foreach (['image_id' => 'ID', 'image_en' => 'EN'] as $field => $label)
                        <div class="relative overflow-hidden border-r border-white last:border-0">
                            @if ($activity->{$field})
                                <img src="{{ asset('storage/'.$activity->{$field}) }}" alt="" class="h-full w-full object-cover">
                            @endif
                            <span class="absolute left-2 top-2 bg-slate-950/85 px-2 py-1 text-[10px] font-black text-white">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="p-5">
                    <div class="flex items-center justify-between gap-3">
                        <time class="text-xs font-bold text-slate-500">{{ \Illuminate\Support\Carbon::parse($activity->date)->translatedFormat('d M Y') }}</time>
                        <span class="px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $activity->status === 'publish' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $activity->status }}</span>
                    </div>
                    <p class="mt-4 line-clamp-3 text-sm font-bold leading-6 text-slate-900">{{ $activity->description_id }}</p>
                    <div class="mt-5 flex gap-2 border-t border-slate-100 pt-4">
                        <a href="{{ route('cms.activities.edit', $activity->id) }}" class="flex-1 border border-slate-300 px-3 py-2 text-center text-xs font-bold hover:border-slate-950">Edit</a>
                        <form method="POST" action="{{ route('cms.activities.destroy', $activity->id) }}" onsubmit="return confirm('Hapus aktivitas ini?')">
                            @csrf @method('DELETE')
                            <button class="border border-red-200 px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50">Hapus</button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <div class="border border-dashed border-slate-300 bg-white p-12 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3">Belum ada aktivitas.</div>
        @endforelse
    </div>
</div>
@endsection
