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
        'Mangrove' => 'Mangrove',
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
        $approvedIds = array_keys(FireSusceptibilityService::LAND_COVER_NAMES);
        $evidenceById = config('ember.class_evidence', []);

        return collect($approvedIds)
            ->map(function (int $id) use ($language, $susceptibility, $evidenceById) {
                $evidence = is_array($evidenceById[$id] ?? null) ? $evidenceById[$id] : [];
                $hotspotEvidence = isset($evidence['hotspot_evidence'])
                    ? (float) $evidence['hotspot_evidence']
                    : null;
                $historicalEvidence = isset($evidence['historical_burn_evidence'])
                    ? (float) $evidence['historical_burn_evidence']
                    : null;
                $fsi = $susceptibility->fsi($hotspotEvidence, $historicalEvidence);
                $fsiClass = $susceptibility->fsiClass($fsi);
                $contextFlag = $susceptibility->contextFlag($hotspotEvidence, $historicalEvidence);
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
                    'context_flag' => $contextFlag,
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
            'georeferenced_total' => $filteredLocations->count(),
            'mapped_total' => $mappedTotal,
            'unclassified_total' => $filteredLocations->count() - $mappedTotal,
            'stale_persisted_fsi_count' => $filteredLocations->filter(function ($location) {
                if (! filled($location->land_cover_id ?? null)) {
                    return false;
                }

                return ! filled($location->model_version ?? null)
                    || $location->model_version !== config('ember.model_version');
            })->count(),
            'model_version' => (string) config('ember.model_version', ''),
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

    /**
     * Aggregate the complete spatial dataset from its authoritative sources.
     * Historical confidence comes from the enriched NASA source; candidates
     * contribute only their crosswalked class, and database rows outside the
     * historical set contribute only when their class is explicitly verified.
     *
     * @param array<int, array<string, mixed>> $candidatePoints
     * @return array<string, mixed>
     */
    public function spatialDataset(Collection $databaseLocations, string $language = 'id'): array
    {
        $path = storage_path('app/ember/output/nrt_621_collection4_final_verified.csv');
        $candidateData = ['points' => [], 'overlap_by_coordinate' => [], 'overlap_count' => 0];
        $candidateConfidence = $this->candidateConfidenceLookup();

        if (is_file($path) && ($handle = fopen($path, 'r')) !== false) {
            $headers = fgetcsv($handle, escape: '') ?: [];
            $headers = array_map(fn ($header) => strtolower(trim((string) $header, "\" \t\n\r\0\x0B\xEF\xBB\xBF")), $headers);
            $databaseCoordinates = [];
            foreach ($databaseLocations as $location) {
                $key = $this->coordinateKey((float) $location->latitude, (float) $location->longitude);
                $layer = (bool) ($location->is_nrt ?? false)
                    ? 'nrt'
                    : (($location->data_source ?? null) === 'NASA FIRMS' ? 'historical' : 'legacy');
                if (! isset($databaseCoordinates[$key]) || $layer === 'nrt') {
                    $databaseCoordinates[$key] = $layer;
                }
            }

            $points = [];
            $overlapByCoordinate = [];
            $seenCoordinates = [];
            $matcher = app(MapBiomasSpatialMatcher::class);
            $classEvidence = config('ember.class_evidence', []);

            while (($values = fgetcsv($handle, escape: '')) !== false) {
                if (count($values) !== count($headers)) {
                    continue;
                }
                $row = array_combine($headers, $values);
                if (! is_array($row) || ! is_numeric($row['latitude'] ?? null) || ! is_numeric($row['longitude'] ?? null)) {
                    continue;
                }
                $latitude = (float) $row['latitude'];
                $longitude = (float) $row['longitude'];
                if (! is_finite($latitude) || ! is_finite($longitude) || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                    continue;
                }
                $key = $this->coordinateKey($latitude, $longitude);
                if (isset($seenCoordinates[$key])) {
                    continue;
                }
                $seenCoordinates[$key] = true;
                $candidateId = trim((string) ($row['hotspot_id'] ?? ''));
                $confidenceRecord = $candidateConfidence[$candidateId] ?? [];

                $rawClassId = filter_var($row['raw_class_id'] ?? null, FILTER_VALIDATE_INT);
                $landCoverId = $rawClassId === false ? null : $matcher->mapRawClassId((int) $rawClassId);
                $class = $landCoverId === null ? null : ($classEvidence[$landCoverId] ?? null);
                $point = [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'raw_mapbiomas_class_id' => $rawClassId === false ? null : (int) $rawClassId,
                    'land_cover_id' => $landCoverId,
                    'land_cover' => is_array($class) ? ($class['name'] ?? null) : null,
                    'classification_status' => $landCoverId === null ? 'unmapped_candidate' : 'sampled_candidate',
                    'candidate_id' => $candidateId,
                    'confidence' => $confidenceRecord['confidence'] ?? null,
                    'confidence_date' => $confidenceRecord['acq_date'] ?? null,
                    'confidence_time' => $confidenceRecord['acq_time'] ?? null,
                    'confidence_satellite' => $confidenceRecord['satellite'] ?? null,
                    'coincident_with_database' => isset($databaseCoordinates[$key]),
                    'coincident_layer' => $databaseCoordinates[$key] ?? null,
                ];
                $points[] = $point;
                if ($point['coincident_with_database']) {
                    $overlapByCoordinate[$key] = $point;
                }
            }
            fclose($handle);

            $candidateData = [
                'points' => $points,
                'overlap_by_coordinate' => $overlapByCoordinate,
                'overlap_count' => count($overlapByCoordinate),
            ];
        }

        return [
            'candidate_data' => $candidateData,
            'composition' => $this->uniqueSpatialDataset($databaseLocations, $candidateData['points'], $language),
        ];
    }

    private function candidateConfidenceLookup(): array
    {
        $path = storage_path('app/ember/output/nrt_candidate_confidence_enriched.csv');
        if (! is_file($path) || ($handle = fopen($path, 'r')) === false) {
            return [];
        }

        $headers = fgetcsv($handle, escape: '') ?: [];
        $headers = array_map(fn ($header) => strtolower(trim((string) $header, "\" \t\n\r\0\x0B\xEF\xBB\xBF")), $headers);
        $records = [];
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if (count($values) !== count($headers)) {
                continue;
            }
            $row = array_combine($headers, $values);
            $id = trim((string) ($row['hotspot_id'] ?? ''));
            if ($id !== '' && trim((string) ($row['confidence'] ?? '')) !== '') {
                $records[$id] = $row;
            }
        }
        fclose($handle);

        return $records;
    }

    public function coordinateKey(float $latitude, float $longitude): string
    {
        return number_format($latitude, 8, '.', '').'|'.number_format($longitude, 8, '.', '');
    }

    private function uniqueSpatialDataset(Collection $databaseLocations, array $candidatePoints, string $language = 'id'): array
    {
        $definitions = $this->classDefinitions($language);
        $approvedIds = $definitions->pluck('id')->all();
        $classCounts = array_fill_keys($approvedIds, 0);
        $classConfidence = array_fill_keys($approvedIds, ['high' => 0, 'nominal' => 0, 'low' => 0, 'unrated' => 0]);
        $confidenceCounts = ['high' => 0, 'nominal' => 0, 'low' => 0, 'unrated' => 0];
        $confidenceRecords = [];
        $unresolvedPoints = [];
        $historicalCount = 0;
        $seenHistorical = [];
        $historicalPath = storage_path('app/ember/source/EMBER_Hotspots_Enriched_Evidence_v2.csv');

        if (is_file($historicalPath) && ($handle = fopen($historicalPath, 'r')) !== false) {
            $headers = fgetcsv($handle, escape: '') ?: [];
            $headers = array_map(fn ($header) => strtolower(trim((string) $header, "\" \t\n\r\0\x0B\xEF\xBB\xBF")), $headers);
            $line = 1;

            while (($values = fgetcsv($handle, escape: '')) !== false) {
                $line++;
                if (count($values) !== count($headers)) {
                    continue;
                }
                $row = array_combine($headers, $values);
                if (! is_array($row)) {
                    continue;
                }

                $identity = implode('|', array_map(fn (string $key) => trim((string) ($row[$key] ?? '')), [
                    'satellite', 'instrument', 'acq_date', 'acq_time', 'latitude', 'longitude',
                ]));
                if ($identity === '|||||' || isset($seenHistorical[$identity])) {
                    if (isset($seenHistorical[$identity])) {
                        continue;
                    }
                    $identity = 'source-row-'.$line;
                }
                $seenHistorical[$identity] = true;
                $historicalCount++;

                $mapbiomasId = filter_var($row['mapbiomas_id'] ?? null, FILTER_VALIDATE_INT);
                $landCoverId = filter_var($row['land_cover_id'] ?? null, FILTER_VALIDATE_INT);
                $classId = $mapbiomasId !== false && $landCoverId !== false && (int) $mapbiomasId === (int) $landCoverId
                    ? (int) $landCoverId
                    : null;
                if ($classId !== null && in_array($classId, $approvedIds, true)) {
                    $classCounts[$classId]++;
                } else {
                    $unresolvedPoints[] = [
                        'source' => 'historical_source',
                        'row' => $line,
                        'latitude' => $row['latitude'] ?? null,
                        'longitude' => $row['longitude'] ?? null,
                        'reason' => 'Historical source class IDs are missing, inconsistent, or not approved.',
                    ];
                }

                $confidence = trim((string) ($row['confidence'] ?? ''));
                if ($confidence !== '') {
                    $key = $this->confidenceKey($confidence);
                    $confidenceCounts[$key]++;
                    if ($classId !== null && in_array($classId, $approvedIds, true)) {
                        $classConfidence[$classId][$key]++;
                    }
                    $confidenceRecords[] = [
                        'date' => $row['acq_date'] ?? null,
                        'confidence_key' => $key,
                    ];
                }
            }
            fclose($handle);
        }

        $nonHistoricalDatabaseCount = 0;
        foreach ($databaseLocations as $location) {
            $isNrt = (bool) ($location->is_nrt ?? false);
            $isHistorical = ! $isNrt && ($location->data_source ?? null) === 'NASA FIRMS';
            if ($isHistorical) {
                continue;
            }
            $nonHistoricalDatabaseCount++;

            $classId = strtolower(trim((string) ($location->classification_status ?? ''))) === 'classified'
                ? (int) ($location->land_cover_id ?? 0)
                : 0;
            if (in_array($classId, $approvedIds, true)) {
                $classCounts[$classId]++;
            } else {
                $unresolvedPoints[] = [
                    'source' => $isNrt ? 'nrt_database' : 'legacy_database',
                    'id' => $location->id ?? null,
                    'latitude' => $location->latitude ?? null,
                    'longitude' => $location->longitude ?? null,
                    'stored_land_cover_id' => $location->land_cover_id ?? null,
                    'classification_status' => $location->classification_status ?? null,
                    'reason' => 'The database record has no verified approved class assignment.',
                ];
            }

            $confidence = trim((string) ($location->confidence ?? ''));
            if ($isNrt && $confidence !== '') {
                $key = $this->confidenceKey($confidence);
                $confidenceCounts[$key]++;
                if (in_array($classId, $approvedIds, true)) {
                    $classConfidence[$classId][$key]++;
                }
                $confidenceRecords[] = [
                    'date' => $location->date ?? null,
                    'confidence_key' => $key,
                ];
            }
        }

        $candidateOverlapCount = 0;
        $candidateAdditionalCount = 0;
        foreach ($candidatePoints as $candidate) {
            if ((bool) ($candidate['coincident_with_database'] ?? false)) {
                $candidateOverlapCount++;
                continue;
            }
            $candidateAdditionalCount++;
            $classId = filter_var($candidate['land_cover_id'] ?? null, FILTER_VALIDATE_INT);
            if ($classId !== false && in_array((int) $classId, $approvedIds, true)) {
                $classCounts[(int) $classId]++;
                $confidence = trim((string) ($candidate['confidence'] ?? ''));
                if ($confidence !== '') {
                    $confidenceKey = $this->confidenceKey($confidence);
                    $confidenceCounts[$confidenceKey]++;
                    $classConfidence[(int) $classId][$confidenceKey]++;
                    $confidenceRecords[] = [
                        'date' => $candidate['confidence_date'] ?? null,
                        'confidence_key' => $confidenceKey,
                    ];
                }
            } else {
                $unresolvedPoints[] = [
                    'source' => 'collection4_candidate',
                    'id' => null,
                    'latitude' => $candidate['latitude'] ?? null,
                    'longitude' => $candidate['longitude'] ?? null,
                    'raw_mapbiomas_class_id' => $candidate['raw_mapbiomas_class_id'] ?? null,
                    'reason' => 'The candidate raw class has no approved EMBER mapping.',
                ];
            }
        }

        $uniqueTotal = $historicalCount + $nonHistoricalDatabaseCount + $candidateAdditionalCount;
        $mappedTotal = array_sum($classCounts);
        $classes = $definitions->map(function (array $definition) use ($classCounts, $classConfidence, $mappedTotal, $uniqueTotal) {
            $id = $definition['id'];
            $confidenceByClass = $classConfidence[$id];
            $classConfidenceTotal = $confidenceByClass['high'] + $confidenceByClass['nominal'] + $confidenceByClass['low'];

            return [
                ...$definition,
                'count' => $classCounts[$id],
                'share' => $uniqueTotal > 0 ? round(($classCounts[$id] / $uniqueTotal) * 100, 2) : 0,
                'confidence' => collect($confidenceByClass)->map(fn (int $count) => [
                    'count' => $count,
                    'share' => $classConfidenceTotal > 0 ? round(($count / $classConfidenceTotal) * 100, 1) : 0,
                ])->all(),
            ];
        })->values();
        $confidenceTotal = array_sum($confidenceCounts);
        $confidenceCategoryCounts = array_intersect_key($confidenceCounts, array_flip(['high', 'nominal', 'low']));
        $confidenceCategoryTotal = array_sum($confidenceCategoryCounts);

        return [
            'total' => $uniqueTotal,
            'mapped_total' => $mappedTotal,
            'unclassified_total' => $uniqueTotal - $mappedTotal,
            'classes' => $classes,
            'confidence' => collect($confidenceCategoryCounts)->map(fn (int $count) => [
                'count' => $count,
                'share' => $confidenceCategoryTotal > 0 ? round(($count / $confidenceCategoryTotal) * 100, 2) : 0,
            ])->all(),
            'confidence_total' => $confidenceTotal,
            'confidence_category_total' => $confidenceCategoryTotal,
            'confidence_cohort_total' => $uniqueTotal,
            'confidence_unavailable_total' => max(0, $uniqueTotal - $confidenceCategoryTotal),
            'confidence_unrated_total' => $confidenceCounts['unrated'],
            'confidence_counts' => $confidenceCategoryCounts,
            'confidence_records' => $confidenceRecords,
            'candidate_overlap_count' => $candidateOverlapCount,
            'candidate_additional_count' => $candidateAdditionalCount,
            'historical_total' => $historicalCount,
            'nonhistorical_database_total' => $nonHistoricalDatabaseCount,
            'unresolved_points' => $unresolvedPoints,
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
            'h', 'high', 'tinggi' => 'high',
            'n', 'nominal', 'medium', 'sedang' => 'nominal',
            'l', 'low', 'rendah' => 'low',
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
        if (strtolower(trim((string) ($location->classification_status ?? ''))) !== 'classified') {
            return null;
        }

        if (isset($location->land_cover_id) && $location->land_cover_id !== null) {
            $id = (int) $location->land_cover_id;

            return $definitions->contains(fn (array $definition) => $definition['id'] === $id) ? $id : null;
        }

        $name = trim((string) ($location->land_cover ?? ''));
        $match = $definitions->first(fn (array $definition) => $definition['name'] === $name);

        return $match['id'] ?? null;
    }
}
