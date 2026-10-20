<?php

namespace App\Livewire\User;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $locations = DB::table('titik_lokasi')
            ->select([
                'id',
                'provinsi',
                'kabupaten_kota',
                'kecamatan',
                'desa',
                'latitude',
                'longitude',
                'date',
                'confidence',
            ])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('id')
            ->get();

        return view('livewire.user.dashboard', [
            'locations' => $locations,
            'totalLocations' => $locations->count(),
            'totalWarnings' => $locations->whereNotNull('confidence')->count(),
            'latestLocations' => $locations->sortByDesc('date')->take(4)->values(),
            'latestDate' => $locations->pluck('date')->filter()->max(),
            'highWarnings' => $locations->filter(function ($location) {
                $confidence = strtolower(trim((string) $location->confidence));

                return in_array($confidence, ['high', 'tinggi'], true)
                    || (is_numeric($confidence) && (float) $confidence >= 80);
            })->count(),
            'language' => request()->query('lang') === 'en' ? 'en' : 'id',
        ]);
    }
}
