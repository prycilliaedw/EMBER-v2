<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContentController extends Controller
{
    public function map(Request $request): View
    {
        $language = $this->language($request);
        $classEvidence = config('ember.class_evidence', []);
        $locations = DB::table('titik_lokasi')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('id')
            ->get()
            ->map(function ($location) use ($language, $classEvidence) {
                $location->detail_url = route('user.locations.show', [
                    'id' => $location->id,
                    'lang' => $language,
                ]);

                $location->confidence_class = $this->confidenceClass($location->confidence);
                $location->fsi_class = $location->fsi_class ?: null;

                if ($location->land_cover_id !== null) {
                    $evidence = $classEvidence[(int) $location->land_cover_id] ?? null;

                    if (is_array($evidence)) {
                        $location->hotspot_evidence = $evidence['hotspot_evidence'] ?? null;
                        $location->historical_burn_evidence = $evidence['historical_burn_evidence'] ?? null;
                    }
                }

                return $location;
            });
        $mapDashboard = app(\App\Services\EmberStatisticsService::class)
            ->dashboard($locations, $this->dashboardFilters($request), $language);

        $boundaryLayers = DB::table('geojson_layers')
            ->where('is_active', true)
            ->where('file_format', 'pmtiles')
            ->orderBy('min_zoom')
            ->get()
            ->map(fn (object $layer) => [
                'name' => $layer->name,
                'url' => route('map-layers.show', $layer->id),
                'level' => $layer->administrative_level,
                'minZoom' => $layer->min_zoom,
                'maxZoom' => $layer->max_zoom,
            ]);

        return view('user.map', [
            'locations' => $locations,
            'boundaryLayers' => $boundaryLayers,
            'mapDashboard' => $mapDashboard,
            'language' => $language,
        ]);
    }

    public function data(Request $request): View
    {
        return view('user.data', [
            'language' => $this->language($request),
            'years' => DB::table('titik_lokasi')
                ->whereNotNull('date')
                ->pluck('date')
                ->map(fn ($date) => (int) substr((string) $date, 0, 4))
                ->filter()
                ->unique()
                ->sortDesc()
                ->values(),
            'provinces' => DB::table('titik_lokasi')
                ->whereNotNull('provinsi')
                ->where('provinsi', '!=', '')
                ->distinct()
                ->orderBy('provinsi')
                ->pluck('provinsi'),
        ]);
    }

    public function downloadLocationCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'in:all,year,province'],
            'year' => ['nullable', 'required_if:scope,year', 'integer', 'digits:4'],
            'province' => ['nullable', 'required_if:scope,province', 'string', 'max:255'],
        ]);
        $scope = $validated['scope'] ?? 'all';
        $year = isset($validated['year']) ? (int) $validated['year'] : null;
        $province = $validated['province'] ?? null;
        $filenameSuffix = match ($scope) {
            'year' => (string) $year,
            'province' => Str::slug((string) $province),
            default => 'semua',
        };

        return response()->streamDownload(function () use ($scope, $year, $province): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'provinsi',
                'kabupaten_kota',
                'kecamatan',
                'desa',
                'latitude',
                'longitude',
                'date',
                'confidence',
                'satellite',
                'instrument',
                'daynight',
                'acq_time',
                'land_cover_id',
                'land_cover',
                'hotspot_evidence',
                'historical_burn_evidence',
                'model_version',
                'fsi_score',
                'fsi_class',
                'context_flag',
            ], ',', '"', '');

            $safeText = static function (mixed $value): mixed {
                if (is_string($value) && preg_match('/^[=+\-@]/', $value) === 1) {
                    return "'{$value}";
                }

                return $value;
            };

            $query = DB::table('titik_lokasi');

            if ($scope === 'year') {
                $query->whereYear('date', $year);
            } elseif ($scope === 'province') {
                $query->where('provinsi', $province);
            }

            $query->orderBy('id')
                ->chunkById(1000, function ($locations) use ($output, $safeText): void {
                    foreach ($locations as $location) {
                        fputcsv($output, [
                            $safeText($location->provinsi),
                            $safeText($location->kabupaten_kota),
                            $safeText($location->kecamatan),
                            $safeText($location->desa),
                            $location->latitude,
                            $location->longitude,
                            $location->date,
                            $location->confidence,
                            $location->satellite ?? null,
                            $location->instrument ?? null,
                            $location->daynight ?? null,
                            $location->acq_time ?? null,
                            $location->land_cover_id ?? null,
                            $safeText($location->land_cover ?? null),
                            $location->hotspot_evidence ?? null,
                            $location->historical_burn_evidence ?? null,
                            $location->model_version ?? null,
                            $location->fsi_score ?? null,
                            $location->fsi_class ?? null,
                            $location->context_flag ?? null,
                        ], ',', '"', '');
                    }
                });

            fclose($output);
        }, "data-titik-lokasi-ember-{$filenameSuffix}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function location(Request $request, int $id): View
    {
        $location = DB::table('titik_lokasi')->where('id', $id)->firstOrFail();

        return view('user.location-detail', [
            'location' => $location,
            'language' => $this->language($request),
            'status' => $this->statusFor($location->confidence, $this->language($request)),
        ]);
    }

    public function search(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $query = trim($validated['q'] ?? '');
        $locations = collect();
        $members = collect();

        if ($query !== '') {
            $locations = DB::table('titik_lokasi')
                ->where(function ($builder) use ($query) {
                    $builder->where('desa', 'like', "%{$query}%")
                        ->orWhere('kecamatan', 'like', "%{$query}%")
                        ->orWhere('kabupaten_kota', 'like', "%{$query}%")
                        ->orWhere('provinsi', 'like', "%{$query}%");
                })
                ->limit(20)
                ->get();

            $members = DB::table('team_members')
                ->where('is_active', true)
                ->where(function ($builder) use ($query) {
                    $builder->where('nama', 'like', "%{$query}%")
                        ->orWhere('npm', 'like', "%{$query}%")
                        ->orWhere('name_id', 'like', "%{$query}%")
                        ->orWhere('name_en', 'like', "%{$query}%")
                        ->orWhere('position_id', 'like', "%{$query}%")
                        ->orWhere('position_en', 'like', "%{$query}%");
                })
                ->limit(20)
                ->get();
        }

        return view('user.search', [
            'query' => $query,
            'locations' => $locations,
            'members' => $members,
            'language' => $this->language($request),
        ]);
    }

    public function about(Request $request): View
    {
        return view('user.about', [
            'about' => DB::table('about_pages')->first(),
            'language' => $this->language($request),
        ]);
    }

    public function team(Request $request): View
    {
        return view('user.team', [
            'members' => DB::table('team_members')->where('is_active', true)->orderBy('sort_order')->get(),
            'language' => $this->language($request),
        ]);
    }

    public function methodology(Request $request): View
    {
        return view('user.methodology', [
            'methodology' => DB::table('methodology_pages')->first(),
            'language' => $this->language($request),
        ]);
    }

    public function faq(Request $request): View
    {
        return view('user.faq', [
            'faqs' => DB::table('faqs')->orderBy('sort_order')->orderBy('id')->get(),
            'language' => $this->language($request),
        ]);
    }

    public function activities(Request $request): View
    {
        return view('user.activities.index', [
            'activities' => DB::table('activities')
                ->where('status', 'publish')
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get(),
            'language' => $this->language($request),
        ]);
    }

    public function activity(Request $request, int $id): View
    {
        return view('user.activities.show', [
            'activity' => DB::table('activities')
                ->where('id', $id)
                ->where('status', 'publish')
                ->firstOrFail(),
            'language' => $this->language($request),
        ]);
    }

    public function statistics(Request $request): View
    {
        $locations = DB::table('titik_lokasi')->select('provinsi', 'date', 'confidence')->get();
        $dashboardLocations = DB::table('titik_lokasi')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();
        $dashboardFilters = $this->dashboardFilters($request);
        $dashboard = app(\App\Services\EmberStatisticsService::class)
            ->dashboard($dashboardLocations, $dashboardFilters, $this->language($request));
        $emptyCounts = ['high' => 0, 'medium' => 0, 'low' => 0, 'unrated' => 0];
        $summary = $locations->reduce(function (array $counts, object $location) {
            $counts[$this->statusKey($location->confidence)]++;

            return $counts;
        }, $emptyCounts);

        $yearlyStatistics = $locations
            ->filter(fn (object $location) => filled($location->date))
            ->groupBy(fn (object $location) => (int) substr((string) $location->date, 0, 4))
            ->map(function ($yearLocations, int $year) use ($emptyCounts) {
                $counts = $yearLocations->reduce(function (array $yearCounts, object $location) {
                    $yearCounts[$this->statusKey($location->confidence)]++;

                    return $yearCounts;
                }, $emptyCounts);
                $total = array_sum($counts);

                return [
                    'year' => $year,
                    'total' => $total,
                    'counts' => $counts,
                    'percentages' => collect($counts)->map(
                        fn (int $count) => $total > 0 ? round(($count / $total) * 100, 2) : 0
                    )->all(),
                ];
            })
            ->sortByDesc('year')
            ->values();

        $years = $yearlyStatistics->pluck('year')->sort()->values();
        $unknownProvince = $this->language($request) === 'en' ? 'Unknown province' : 'Provinsi belum diketahui';
        $provinceStatistics = $locations
            ->groupBy(fn (object $location) => filled($location->provinsi) ? trim($location->provinsi) : $unknownProvince)
            ->map(function ($provinceLocations, string $province) use ($years) {
                return [
                    'name' => $province,
                    'total' => $provinceLocations->count(),
                    'yearly' => $years->map(fn (int $year) => $provinceLocations->filter(
                        fn (object $location) => filled($location->date)
                            && (int) substr((string) $location->date, 0, 4) === $year
                    )->count())->values()->all(),
                    'monthly' => $years->mapWithKeys(fn (int $year) => [
                        (string) $year => collect(range(1, 12))->map(fn (int $month) => $provinceLocations->filter(
                            fn (object $location) => filled($location->date)
                                && (int) substr((string) $location->date, 0, 4) === $year
                                && (int) substr((string) $location->date, 5, 2) === $month
                        )->count())->values()->all(),
                    ])->all(),
                ];
            })
            ->sortByDesc('total')
            ->values();

        return view('user.statistics', [
            'language' => $this->language($request),
            'dashboard' => $dashboard,
            'dashboardFilters' => $dashboardFilters,
            'summary' => ['total' => $locations->count(), ...$summary],
            'yearlyStatistics' => $yearlyStatistics,
            'provinceYears' => $years,
            'provinceStatistics' => $provinceStatistics,
        ]);
    }

    private function dashboardFilters(Request $request): array
    {
        $confidenceOptions = ['high', 'nominal', 'low', 'unrated'];
        $fsiOptions = ['very_low', 'low', 'moderate', 'high', 'very_high', 'unrated'];
        $confidence = $request->query('confidence', []);
        $fsi = $request->query('fsi', []);

        if (is_string($confidence)) {
            $confidence = explode(',', $confidence);
        }
        if (is_string($fsi)) {
            $fsi = explode(',', $fsi);
        }

        return [
            'year' => preg_match('/^\d{4}$/', (string) $request->query('year', ''))
                ? (int) $request->query('year')
                : null,
            'province' => is_string($request->query('province'))
                ? trim($request->query('province'))
                : '',
            'land_cover' => is_string($request->query('land_cover'))
                ? trim($request->query('land_cover'))
                : '',
            'confidence' => array_values(array_intersect($confidenceOptions, is_array($confidence) ? $confidence : [])),
            'fsi' => array_values(array_intersect($fsiOptions, is_array($fsi) ? $fsi : [])),
        ];
    }

    private function language(Request $request): string
    {
        return $request->query('lang') === 'en' ? 'en' : 'id';
    }

    private function confidenceClass(mixed $confidence): ?string
    {
        if ($confidence === null || trim((string) $confidence) === '' || ! is_numeric($confidence)) {
            return null;
        }

        $value = (float) $confidence;

        return match (true) {
            $value < 30 => 'low',
            $value < 80 => 'nominal',
            default => 'high',
        };
    }

    private function statusKey(mixed $confidence): string
    {
        if ($confidence === null || trim((string) $confidence) === '') {
            return 'unrated';
        }

        $value = strtolower(trim((string) $confidence));

        if (in_array($value, ['high', 'tinggi'], true) || (is_numeric($value) && (float) $value >= 80)) {
            return 'high';
        }

        if (in_array($value, ['nominal', 'medium', 'sedang'], true) || (is_numeric($value) && (float) $value >= 50)) {
            return 'medium';
        }

        return 'low';
    }

    private function statusFor(mixed $confidence, string $language): string
    {
        if ($confidence === null || trim((string) $confidence) === '') {
            return $language === 'en' ? 'Unrated' : 'Belum dinilai';
        }

        $value = strtolower(trim((string) $confidence));

        if (in_array($value, ['high', 'tinggi'], true) || (is_numeric($value) && (float) $value >= 80)) {
            return $language === 'en' ? 'High' : 'Tinggi';
        }

        if (in_array($value, ['nominal', 'medium', 'sedang'], true) || (is_numeric($value) && (float) $value >= 50)) {
            return $language === 'en' ? 'Medium' : 'Sedang';
        }

        return $language === 'en' ? 'Low' : 'Rendah';
    }
}
