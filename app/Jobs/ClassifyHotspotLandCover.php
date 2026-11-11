<?php

namespace App\Jobs;

use App\Services\FireSusceptibilityService;
use App\Services\AdministrativeLocationMatcher;
use App\Services\MapBiomasSpatialMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClassifyHotspotLandCover implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];
    public int $uniqueFor = 300;

    public function __construct(private readonly int $hotspotId)
    {
        $this->onQueue('ember-classification');
    }

    public function uniqueId(): string
    {
        return (string) $this->hotspotId;
    }

    public function handle(
        MapBiomasSpatialMatcher $matcher,
        FireSusceptibilityService $susceptibility,
        AdministrativeLocationMatcher $administrativeMatcher
    ): void {
        $hotspot = DB::table('titik_lokasi')->where('id', $this->hotspotId)->first();
        if (! $hotspot) {
            return;
        }

        $latitude = is_numeric($hotspot->latitude ?? null) ? (float) $hotspot->latitude : null;
        $longitude = is_numeric($hotspot->longitude ?? null) ? (float) $hotspot->longitude : null;
        if ($latitude === null || $longitude === null || ! is_finite($latitude) || ! is_finite($longitude)) {
            $this->saveStatus($hotspot->id, 'failed', null, null, 'Missing or invalid latitude/longitude');
            return;
        }

        try {
            $location = $administrativeMatcher->match($latitude, $longitude);
            if (is_array($location)) {
                $locationUpdate = array_filter([
                    'provinsi' => $location['provinsi'] ?? null,
                    'kabupaten_kota' => $location['kabupaten_kota'] ?? null,
                    'kecamatan' => $location['kecamatan'] ?? null,
                    'desa' => $location['desa'] ?? null,
                ], fn ($value) => is_string($value) && trim($value) !== '');
                if ($locationUpdate !== []) {
                    DB::table('titik_lokasi')->where('id', $hotspot->id)->update([
                        ...$locationUpdate,
                        'updated_at' => now(),
                    ]);
                }
            }
        } catch (Throwable $exception) {
            Log::warning('EMBER administrative hotspot matching failed', [
                'hotspot_id' => $hotspot->id,
                'error' => $exception->getMessage(),
            ]);
        }

        if ($hotspot->classification_status === 'classified' && $hotspot->land_cover_id !== null) {
            return;
        }

        $match = $matcher->match($latitude, $longitude);
        if (! is_array($match)) {
            $this->saveStatus($hotspot->id, 'raster_unavailable', null, null, 'Raster matcher returned no result');
            return;
        }

        $rawClassId = $match['raw_class_id'] ?? null;
        $status = $match['status'] ?? 'failed';
        if ($status !== 'classified') {
            $this->saveStatus(
                $hotspot->id,
                $status,
                is_numeric($rawClassId) ? (int) $rawClassId : null,
                null,
                $match['error'] ?? null
            );
            return;
        }

        $landCoverId = (int) $match['id'];
        $evidence = $susceptibility->classEvidence($landCoverId);
        if (! is_array($evidence)) {
            $this->saveStatus($hotspot->id, 'pending_review', $rawClassId, $landCoverId, 'Missing class-level HSE/HBE evidence');
            return;
        }

        $hse = isset($evidence['hotspot_evidence']) ? (float) $evidence['hotspot_evidence'] : null;
        $hbe = isset($evidence['historical_burn_evidence']) ? (float) $evidence['historical_burn_evidence'] : null;
        $fsi = $susceptibility->fsi($hse, $hbe);

        DB::table('titik_lokasi')->where('id', $hotspot->id)->update([
            'raw_mapbiomas_class_id' => $rawClassId,
            'land_cover_id' => $landCoverId,
            'land_cover' => $match['name'] ?? FireSusceptibilityService::LAND_COVER_NAMES[$landCoverId] ?? null,
            'hotspot_evidence' => $hse,
            'historical_burn_evidence' => $hbe,
            'fsi_score' => $fsi,
            'fsi_class' => $susceptibility->fsiClass($fsi),
            'context_flag' => $susceptibility->contextFlag($hse, $hbe),
            'model_version' => FireSusceptibilityService::MODEL_VERSION,
            'classification_status' => 'classified',
            'classification_error' => null,
            'classified_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $hotspot = DB::table('titik_lokasi')->where('id', $this->hotspotId)->first();
        if (! $hotspot) {
            return;
        }
        Log::error('EMBER hotspot classification job failed', [
            'hotspot_id' => $this->hotspotId,
            'error' => $exception->getMessage(),
        ]);
        DB::table('titik_lokasi')->where('id', $this->hotspotId)->update([
            'classification_status' => 'failed',
            'classification_error' => $exception->getMessage(),
            'classification_attempts' => DB::raw('COALESCE(classification_attempts, 0) + 1'),
            'updated_at' => now(),
        ]);
    }

    private function saveStatus(
        int $hotspotId,
        string $status,
        ?int $rawClassId,
        ?int $landCoverId,
        ?string $error
    ): void {
        DB::table('titik_lokasi')->where('id', $hotspotId)->update([
            'raw_mapbiomas_class_id' => $rawClassId,
            'land_cover_id' => $landCoverId,
            'land_cover' => $landCoverId !== null
                ? (FireSusceptibilityService::LAND_COVER_NAMES[$landCoverId] ?? null)
                : null,
            'classification_status' => $status,
            'classification_error' => $error,
            'classified_at' => $status === 'classified' ? now() : null,
            'classification_attempts' => DB::raw('COALESCE(classification_attempts, 0) + 1'),
            'updated_at' => now(),
        ]);
    }
}
