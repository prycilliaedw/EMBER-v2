<?php

namespace App\Livewire\User;

use Illuminate\Support\Facades\DB;
use App\Services\EmberStatisticsService;
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
                'is_nrt',
                'data_source',
                'classification_status',
                'land_cover_id',
                'land_cover',
            ])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('id')
            ->get();

        $language = request()->query('lang') === 'en' ? 'en' : 'id';
        $spatial = app(EmberStatisticsService::class)->spatialDataset($locations, $language)['composition'];
        $latestLocations = $locations->filter(function ($location) {
            $village = strtolower(trim((string) $location->desa));

            return $village !== '' && ! in_array($village, ['desa belum tersedia', 'village not available', 'unknown village'], true);
        })->sortByDesc('date')->take(4)->values();

        return view('livewire.user.dashboard', [
            'locations' => $locations,
            'totalLocations' => $spatial['total'],
            'totalWarnings' => $spatial['mapped_total'],
            'latestLocations' => $latestLocations,
            'latestDate' => $locations->pluck('date')->filter()->max(),
            'highWarnings' => $spatial['confidence_counts']['high'] ?? 0,
            'language' => $language,
        ]);
    }
}
