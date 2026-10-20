@extends('layouts.admin')

@section('title', 'Titik Lokasi - EMBER CMS')

@section('content')
    <div class="mx-auto max-w-7xl">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-red-600">Data pemantauan</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">Titik Lokasi</h1>
                <p class="mt-2 text-sm text-slate-600">Daftar koordinat yang ditampilkan pada peta publik.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span class="w-fit bg-slate-900 px-4 py-2 text-sm font-bold text-white">{{ $locations->total() }} lokasi</span>
                <a href="{{ route('cms.locations.template') }}" class="inline-flex items-center gap-2 border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">
                    Unduh Template CSV
                </a>
                <a href="{{ route('cms.locations.create') }}" class="inline-flex items-center gap-2 border border-red-600 bg-red-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-red-500">
                    <span aria-hidden="true">+</span> Tambah Titik Lokasi
                </a>
            </div>
        </div>

        <livewire:cms.location-csv-import />

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Lokasi</th>
                            <th class="px-5 py-4">Koordinat</th>
                            <th class="px-5 py-4">Tanggal</th>
                            <th class="px-5 py-4">Confidence</th>
                            <th class="px-5 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($locations as $location)
                            <tr class="transition hover:bg-slate-50">
                                <td class="px-5 py-4">
                                    <p class="font-bold text-slate-900">{{ $location->desa ?: 'Lokasi #'.$location->id }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ collect([$location->kecamatan, $location->kabupaten_kota, $location->provinsi])->filter()->join(', ') ?: 'Wilayah belum dilengkapi' }}</p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-600">{{ $location->latitude }}, {{ $location->longitude }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $location->date ? \Illuminate\Support\Carbon::parse($location->date)->format('d M Y') : '-' }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $location->status['class'] }}">{{ $location->status['label'] }}</span>
                                    <p class="mt-1 text-xs text-slate-400">{{ $location->confidence ?? 'NULL' }}</p>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('cms.locations.show', $location->id) }}" class="inline-flex rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700">Lihat detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-16 text-center text-slate-500">Belum ada titik lokasi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($locations->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">{{ $locations->links() }}</div>
            @endif
        </div>
    </div>
@endsection
