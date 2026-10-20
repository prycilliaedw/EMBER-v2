@extends('layouts.admin')

@section('title', 'Detail Titik Lokasi - EMBER CMS')

@section('content')
    <div class="mx-auto max-w-5xl">
        <a href="{{ route('cms.locations.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-red-600">&larr; Kembali ke Titik Lokasi</a>

        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-red-600">Detail lokasi</p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">{{ $location->desa ?: 'Titik lokasi #'.$location->id }}</h1>
                <p class="mt-2 text-sm text-slate-600">Informasi lengkap data pemantauan.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex w-fit rounded-full px-3 py-1.5 text-sm font-bold {{ $location->status['class'] }}">{{ $location->status['label'] }}</span>
                <a href="{{ route('cms.locations.edit', $location->id) }}" class="border border-red-600 bg-red-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-red-500">Edit Detail</a>
            </div>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3">
                @php
                    $details = [
                        'Provinsi' => $location->provinsi,
                        'Kabupaten/Kota' => $location->kabupaten_kota,
                        'Kecamatan' => $location->kecamatan,
                        'Desa' => $location->desa,
                        'Latitude' => $location->latitude,
                        'Longitude' => $location->longitude,
                        'Tanggal' => $location->date,
                        'Confidence' => $location->confidence,
                        'Status' => $location->status['label'],
                        'Tutupan Lahan' => $location->land_cover ?? null,
                        'Hotspot Evidence (HSE)' => isset($location->hotspot_evidence) ? number_format((float) $location->hotspot_evidence, 1).' / 100' : null,
                        'Historical Burn Evidence (HBE)' => isset($location->historical_burn_evidence) ? number_format((float) $location->historical_burn_evidence, 1).' / 100' : null,
                        'Model Version' => $location->model_version ?? null,
                        'FSI' => isset($location->fsi_score) ? number_format((float) $location->fsi_score, 1).' / 100' : null,
                        'Kelas FSI' => $location->fsi_class ?? null,
                        'Context Flag' => $location->context_flag ?? null,
                    ];
                @endphp

                @foreach ($details as $label => $value)
                    <div class="border-b border-slate-100 p-5 sm:border-r">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $label }}</p>
                        <p class="mt-2 break-words font-semibold text-slate-900">{{ $value ?? '-' }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-6 rounded-2xl bg-slate-950 p-6 text-white">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Koordinat</p>
            <p class="mt-2 font-mono text-lg">{{ $location->latitude }}, {{ $location->longitude }}</p>
            <a href="https://www.openstreetmap.org/?mlat={{ $location->latitude }}&mlon={{ $location->longitude }}#map=12/{{ $location->latitude }}/{{ $location->longitude }}" target="_blank" rel="noopener" class="mt-4 inline-flex rounded-lg bg-white/10 px-4 py-2 text-sm font-semibold transition hover:bg-white/20">Buka di OpenStreetMap</a>
        </div>
    </div>
@endsection
