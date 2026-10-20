<?php

namespace App\Services;

use Illuminate\Support\Collection;

final class EmberStatisticsService
{
    private const COLORS = [
        3 => '#16a34a',
        5 => '#0f766e',
        9 => '#84cc16',
        13 => '#a3e635',
        21 => '#f59e0b',
        24 => '#f97316',
        25 => '#b45309',
        30 => '#374151',
        31 => '#38bdf8',
        33 => '#60a5fa',
        35 => '#22c55e',
        40 => '#facc15',
        76 => '#14532d',
    ];

    private const ENGLISH_NAMES = [
        'Formasi Hutan' => 'Forest Formation',
        'Hutan Rawa Gambut' => 'Peat Swamp Forest',
        'Kebun Kayu' => 'Wood Plantation',
        'Lubang Tambang' => 'Mining Pits',
        'Non-Vegetasi Lainnya' => 'Other Non-Vegetated Areas',
        'Pemukiman' => 'Settlement',
        'Pertanian Lainnya' => 'Other Agriculture',
        'Sawah' => 'Rice Field',
        'Sawit' => 'Oil Palm',
        'Sungai, Danau, Laut' => 'River, Lake, Sea',
        'Tambak' => 'Pond',
        'Tumbuhan Non-Hutan Lainnya' => 'Other Non-Forest Vegetation',
    ];

    public function classDefinitions(string $language = 'id'): Collection
    {
        $susceptibility = app(FireSusceptibilityService::class);

        return collect(config('ember.class_evidence', []))
            ->map(function (array $evidence, int|string $id) use ($language, $susceptibility) {
                $hotspotEvidence = isset($evidence['hotspot_evidence'])
                    ? (float) $evidence['hotspot_evidence']
                    : null;
                $historicalEvidence = isset($evidence['historical_burn_evidence'])
                    ? (float) $evidence['historical_burn_evidence']
                    : null;
                $fsi = $susceptibility->fsi($hotspotEvidence, $historicalEvidence);
                $fsiClass = $susceptibility->fsiClass($fsi);
                $name = (string) ($evidence['name'] ?? 'Land cover');

                return [
                    'id' => (int) $id,
                    'name' => $name,
                    'display_name' => $language === 'en' ? (self::ENGLISH_NAMES[$name] ?? $name) : $name,
                    'color' => self::COLORS[(int) $id] ?? '#64748b',
                    'area_km2' => isset($evidence['area_km2']) ? (float) $evidence['area_km2'] : null,
                    'hse' => $hotspotEvidence,
                    'hbe' => $historicalEvidence,
                    'fsi' => $fsi,
                    'fsi_class' => $fsiClass,
                    'fsi_class_label' => $language === 'en' ? $fsiClass : match ($fsiClass) {
                        'Very Low' => 'Sangat Rendah',
                        'Low' => 'Rendah',
                        'Moderate' => 'Sedang',
                        'High' => 'Tinggi',
                        'Very High' => 'Sangat Tinggi',
                        default => null,
                    },
                    'hotspot_count_2021_2023' => $evidence['hotspot_count_2021_2023'] ?? null,
                    'ever_burn_rate_pre2024_percent' => $evidence['ever_burn_rate_pre2024_percent'] ?? null,
                    'mean_frequency_on_burned_footprint_pre2024' => $evidence['mean_frequency_on_burned_footprint_pre2024'] ?? null,
                    'burn_rate_2024_percent' => $evidence['burn_rate_2024_percent'] ?? null,
                ];
            })
            ->values();
    }

    /**
     * Build hotspot and class statistics from the same location set and filters
     * used by the map page. HSE/HBE/FSI remain configured class-level scores.
     */
    public function dashboard(Collection $locations, array $filters = [], string $language = 'id'): array
    {
        $filteredLocations = $this->filterLocations($locations, $filters);
        $definitions = $this->classDefinitions($language);
        $classCounts = $definitions->mapWithKeys(fn (array $definition) => [
            $definition['id'] => 0,
        ])->all();
        $classConfidence = $definitions->mapWithKeys(fn (array $definition) => [
            $definition['id'] => ['high' => 0, 'nominal' => 0, 'low' => 0, 'unrated' => 0],
        ])->all();

        foreach ($filteredLocations as $location) {
            $classId = $this->classId($location, $definitions);

            if ($classId !== null) {
                $classCounts[$classId]++;
                $classConfidence[$classId][$this->confidenceKey($location->confidence ?? null)]++;
            }
        }

        $mappedTotal = array_sum($classCounts);
        $confidenceCounts = ['high' => 0, 'nominal' => 0, 'low' => 0, 'unrated' => 0];
        foreach ($filteredLocations as $location) {
            $confidenceCounts[$this->confidenceKey($location->confidence ?? null)]++;
        }

        $confidenceTotal = array_sum($confidenceCounts);
        $classes = $definitions->map(function (array $definition) use ($classCounts, $classConfidence, $mappedTotal) {
            $count = $classCounts[$definition['id']] ?? 0;
            $confidenceCounts = $classConfidence[$definition['id']];

            return [
                ...$definition,
                'count' => $count,
                'share' => $mappedTotal > 0 ? round(($count / $mappedTotal) * 100, 2) : 0,
                'confidence' => collect($confidenceCounts)->map(fn (int $confidenceCount) => [
                    'count' => $confidenceCount,
                    'share' => $count > 0 ? round(($confidenceCount / $count) * 100, 1) : 0,
                ])->all(),
            ];
        })->values();

        return [
            'total' => $filteredLocations->count(),
            'mapped_total' => $mappedTotal,
            'unclassified_total' => $filteredLocations->count() - $mappedTotal,
            'confidence' => collect($confidenceCounts)->map(fn (int $count) => [
                'count' => $count,
                'share' => $confidenceTotal > 0 ? round(($count / $confidenceTotal) * 100, 2) : 0,
            ])->all(),
            'classes' => $classes,
            'filters' => $filters,
            'years' => $locations->pluck('date')
                ->filter()
                ->map(fn ($date) => (int) substr((string) $date, 0, 4))
                ->unique()
                ->sortDesc()
                ->values(),
            'provinces' => $locations->pluck('provinsi')
                ->filter(fn ($province) => filled($province))
                ->map(fn ($province) => trim((string) $province))
                ->unique()
                ->sort()
                ->values(),
        ];
    }

    public function filterLocations(Collection $locations, array $filters): Collection
    {
        $confidenceFilter = $filters['confidence'] ?? [];
        $fsiFilter = $filters['fsi'] ?? [];

        return $locations->filter(function ($location) use ($filters, $confidenceFilter, $fsiFilter) {
            if (! empty($filters['year'])
                && (! filled($location->date ?? null)
                    || (int) substr((string) $location->date, 0, 4) !== (int) $filters['year'])) {
                return false;
            }

            if (! empty($filters['province'])
                && strcasecmp(trim((string) ($location->provinsi ?? '')), trim((string) $filters['province'])) !== 0) {
                return false;
            }

            if (! empty($filters['land_cover'])) {
                $classId = $location->land_cover_id ?? null;
                $className = config('ember.class_evidence.'.(int) $classId.'.name');
                if (($className ?: trim((string) ($location->land_cover ?? ''))) !== $filters['land_cover']) {
                    return false;
                }
            }

            if ($confidenceFilter !== []
                && ! in_array($this->confidenceKey($location->confidence ?? null), $confidenceFilter, true)) {
                return false;
            }

            if ($fsiFilter !== []
                && ! in_array($this->fsiKey($location->fsi_class ?? null), $fsiFilter, true)) {
                return false;
            }

            return true;
        })->values();
    }

    public function confidenceKey(mixed $confidence): string
    {
        if ($confidence === null || trim((string) $confidence) === '') {
            return 'unrated';
        }

        if (is_numeric($confidence)) {
            return match (true) {
                (float) $confidence < 30 => 'low',
                (float) $confidence < 80 => 'nominal',
                default => 'high',
            };
        }

        return match (strtolower(trim((string) $confidence))) {
            'high', 'tinggi' => 'high',
            'nominal', 'medium', 'sedang' => 'nominal',
            'low', 'rendah' => 'low',
            default => 'unrated',
        };
    }

    private function fsiKey(mixed $fsiClass): string
    {
        if (! filled($fsiClass)) {
            return 'unrated';
        }

        return str_replace(' ', '_', strtolower(trim((string) $fsiClass)));
    }

    private function classId(object $location, Collection $definitions): ?int
    {
        if (isset($location->land_cover_id) && $location->land_cover_id !== null) {
            $id = (int) $location->land_cover_id;

            return $definitions->contains(fn (array $definition) => $definition['id'] === $id) ? $id : null;
        }

        $name = trim((string) ($location->land_cover ?? ''));
        $match = $definitions->first(fn (array $definition) => $definition['name'] === $name);

        return $match['id'] ?? null;
    }
}
