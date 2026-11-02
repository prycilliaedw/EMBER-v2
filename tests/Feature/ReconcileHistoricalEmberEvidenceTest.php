<?php

namespace Tests\Feature;

use App\Services\FireSusceptibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReconcileHistoricalEmberEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private function insertHotspot(array $values = []): int
    {
        return DB::table('titik_lokasi')->insertGetId(array_merge([
            'provinsi' => 'Jambi',
            'kabupaten_kota' => 'Test Kab',
            'kecamatan' => 'Test Kec',
            'desa' => 'Test Desa',
            'latitude' => -1.5,
            'longitude' => 102.5,
            'date' => '2024-01-01',
            'confidence' => 85,
            'classification_status' => 'pending',
            'land_cover_id' => 21,
            'land_cover' => 'Pertanian Lainnya',
        ], $values));
    }

    public function test_all_thirteen_baselines_are_present_and_authoritative(): void
    {
        $service = app(FireSusceptibilityService::class);

        $this->assertCount(13, FireSusceptibilityService::LAND_COVER_NAMES);

        foreach (FireSusceptibilityService::LAND_COVER_NAMES as $classId => $name) {
            $evidence = $service->classEvidence($classId);
            $this->assertIsArray($evidence);
            $this->assertArrayHasKey('hotspot_evidence', $evidence);
            $this->assertArrayHasKey('historical_burn_evidence', $evidence);

            $fsi = $service->fsi(
                (float) $evidence['hotspot_evidence'],
                (float) $evidence['historical_burn_evidence']
            );
            $this->assertNotNull($fsi);
            $this->assertNotNull($service->fsiClass($fsi));
            $this->assertIsString($service->contextFlag(
                (float) $evidence['hotspot_evidence'],
                (float) $evidence['historical_burn_evidence']
            ));
        }
    }

    public function test_dry_run_writes_nothing_and_excludes_unclassified_and_unknown_classes(): void
    {
        $eligible = $this->insertHotspot([
            'hotspot_evidence' => null,
            'historical_burn_evidence' => null,
            'fsi_score' => 10,
            'model_version' => null,
        ]);
        $unclassified = $this->insertHotspot([
            'classification_status' => 'unclassified',
            'land_cover_id' => 21,
        ]);
        $unknown = $this->insertHotspot([
            'classification_status' => 'pending',
            'land_cover_id' => 999,
        ]);

        $beforeEligible = (array) DB::table('titik_lokasi')->where('id', $eligible)->first();
        $beforeUnclassified = (array) DB::table('titik_lokasi')->where('id', $unclassified)->first();
        $beforeUnknown = (array) DB::table('titik_lokasi')->where('id', $unknown)->first();

        $this->artisan('ember:reconcile-historical-fsi')
            ->expectsOutputToContain('Mode: DRY-RUN')
            ->expectsOutputToContain('Total eligible records: 1')
            ->expectsOutputToContain('Dry run complete. No database records were written.')
            ->assertSuccessful();

        $this->assertSame($beforeEligible, (array) DB::table('titik_lokasi')->where('id', $eligible)->first());
        $this->assertSame($beforeUnclassified, (array) DB::table('titik_lokasi')->where('id', $unclassified)->first());
        $this->assertSame($beforeUnknown, (array) DB::table('titik_lokasi')->where('id', $unknown)->first());
    }

    public function test_apply_updates_only_approved_evidence_fields(): void
    {
        $id = $this->insertHotspot([
            'latitude' => -2.12345678,
            'longitude' => 103.12345678,
            'confidence' => 55,
            'date' => '2023-07-08',
            'external_id' => 'NASA-TEST-001',
            'hotspot_evidence' => null,
            'historical_burn_evidence' => null,
            'fsi_score' => 10,
            'fsi_class' => 'Very Low',
            'context_flag' => null,
            'model_version' => null,
        ]);

        $before = DB::table('titik_lokasi')->where('id', $id)->first();

        $this->artisan('ember:reconcile-historical-fsi', ['--apply' => true])
            ->expectsOutputToContain('Mode: APPLY')
            ->assertSuccessful();

        $after = DB::table('titik_lokasi')->where('id', $id)->first();
        $evidence = config('ember.class_evidence.21');
        $service = app(FireSusceptibilityService::class);
        $fsi = $service->fsi((float) $evidence['hotspot_evidence'], (float) $evidence['historical_burn_evidence']);

        $this->assertEquals((float) $evidence['hotspot_evidence'], (float) $after->hotspot_evidence);
        $this->assertEquals((float) $evidence['historical_burn_evidence'], (float) $after->historical_burn_evidence);
        $this->assertEquals($fsi, (float) $after->fsi_score);
        $this->assertSame($service->fsiClass($fsi), $after->fsi_class);
        $this->assertSame($service->contextFlag((float) $evidence['hotspot_evidence'], (float) $evidence['historical_burn_evidence']), $after->context_flag);
        $this->assertSame((string) config('ember.model_version', FireSusceptibilityService::MODEL_VERSION), $after->model_version);

        foreach (['latitude', 'longitude', 'confidence', 'date', 'external_id', 'land_cover_id', 'land_cover', 'raw_mapbiomas_class_id', 'classification_status'] as $field) {
            $this->assertEquals($before->{$field} ?? null, $after->{$field} ?? null, "Protected field {$field} changed");
        }
    }

    public function test_limit_limits_preview_and_apply_selection(): void
    {
        $first = $this->insertHotspot(['latitude' => -1.1]);
        $second = $this->insertHotspot(['latitude' => -1.2, 'land_cover_id' => 24]);

        $this->artisan('ember:reconcile-historical-fsi', ['--apply' => true, '--limit' => 1])
            ->expectsOutputToContain('Total eligible records: 1')
            ->assertSuccessful();

        $this->assertNotNull(DB::table('titik_lokasi')->where('id', $first)->value('model_version'));
        $this->assertNull(DB::table('titik_lokasi')->where('id', $second)->value('model_version'));
    }

    public function test_apply_batch_failure_rolls_back_all_updates(): void
    {
        $this->insertHotspot(['land_cover_id' => 21]);
        $this->insertHotspot([
            'latitude' => -2.6,
            'longitude' => 102.6,
            'land_cover_id' => 24,
            'land_cover' => 'Pemukiman',
        ]);

        DB::unprepared("
            CREATE TRIGGER fail_ember_reconciliation
            BEFORE UPDATE OF model_version ON titik_lokasi
            BEGIN
                SELECT RAISE(ABORT, 'forced reconciliation rollback');
            END
        ");

        $before = DB::table('titik_lokasi')->orderBy('id')->pluck('model_version')->all();

        $this->artisan('ember:reconcile-historical-fsi', ['--apply' => true, '--batch' => 1])
            ->assertFailed();

        $after = DB::table('titik_lokasi')->orderBy('id')->pluck('model_version')->all();
        $this->assertSame($before, $after);
    }
}
