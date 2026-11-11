<?php

namespace Tests\Feature;

use App\Jobs\ClassifyHotspotLandCover;
use App\Services\NasaFirmsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LandCoverClassificationPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_mocked_firms_record_runs_the_complete_ingestion_pipeline(): void
    {
        Config::set('ember.firms.api_key', 'test-only-key');
        Config::set('ember.firms.base_url', 'https://example.test/api');
        Config::set('ember.mapbiomas.raster_directory', base_path('storage/app/ember/source/ember_mapbiomas_collection4'));
        Config::set('queue.default', 'sync');

        Http::fake([
            '*' => Http::response(
                "latitude,longitude,acq_date,acq_time,confidence,satellite,instrument,daynight,frp\n".
                "-3.900000,102.400000,2026-11-02,1200,high,E2E,VIIRS,D,1.25\n",
                200,
                ['Content-Type' => 'text/csv']
            ),
        ]);

        $service = app(NasaFirmsSyncService::class);
        $first = $service->sync(['VIIRS_NOAA21_NRT'], 1);
        $second = $service->sync(['VIIRS_NOAA21_NRT'], 1);

        $this->assertSame(1, DB::table('titik_lokasi')->where('data_source', 'NASA FIRMS')->count());
        $row = DB::table('titik_lokasi')->where('data_source', 'NASA FIRMS')->first();
        $this->assertNotNull($row);
        $this->assertSame(21, (int) $row->raw_mapbiomas_class_id);
        $this->assertSame(21, (int) $row->land_cover_id);
        $this->assertSame('Pertanian Lainnya', $row->land_cover);
        $this->assertSame('classified', $row->classification_status);
        $this->assertNull($row->classification_error);
        $this->assertNotNull($row->hotspot_evidence);
        $this->assertNotNull($row->historical_burn_evidence);
        $this->assertNotNull($row->fsi_score);
        $this->assertNotNull($row->fsi_class);
        $this->assertSame(1, $first['inserted']);
        $this->assertSame(0, $first['updated']);
        $this->assertSame(1, $second['updated']);
        $this->assertSame(0, $second['inserted']);
    }

    public function test_authoritative_raster_inventory_contains_exactly_ten_province_rasters(): void
    {
        $directory = base_path('storage/app/ember/source/ember_mapbiomas_collection4');
        $this->assertDirectoryExists($directory);

        $files = glob($directory.'/ember_mapbiomas_collection4_2024_*.tif') ?: [];
        $authoritative = array_values(array_filter($files, function (string $path) {
            return ! str_contains(basename($path), '_kepri_tile_');
        }));

        $this->assertCount(10, $authoritative);
        $this->assertContains(
            $directory.'/ember_mapbiomas_collection4_2024_kepulauan_riau.tif',
            $authoritative
        );
        $this->assertCount(
            4,
            glob($directory.'/ember_mapbiomas_collection4_2024_kepri_tile_*.tif') ?: []
        );
        $this->assertFileExists($directory.'/ember_kepri_2024.vrt');
    }

    public function test_actual_samples_match_across_all_ten_authoritative_rasters(): void
    {
        Config::set('ember.mapbiomas.python_executable', base_path('.venv/Scripts/python.exe'));

        $samples = [
            ['aoi_01.tif', -2.2768250033651336, 101.2853626948255, 3],
            ['aoi_02.tif', -3.723741431496447, 105.33317136506805, 33],
            ['aoi_03.tif', -1.5607778903934628, 105.49918002957335, 30],
            ['aoi_04.tif', 0.8908143514971236, 99.83790727752371, 3],
            ['aoi_05.tif', -0.7805910661356583, 102.99099392478324, 21],
            ['aoi_06.tif', 5.9071865610773555, 95.21634463531402, 33],
            ['kepulauan_riau.tif', 4.742430963687984, 107.97637425706134, 21],
            ['aoi_08.tif', -1.6930997317442682, 104.27432713967639, 76],
            ['aoi_09.tif', 2.883996303901518, 100.56176973346722, 33],
            ['aoi_10.tif', 4.25949666694533, 98.07945510885975, 21],
        ];

        $directory = base_path('storage/app/ember/source/ember_mapbiomas_collection4');
        $python = base_path('.venv/Scripts/python.exe');
        if (! is_file($python) || ! is_dir($directory)) {
            $this->markTestSkipped('Production raster or project virtualenv is unavailable');
        }

        $matcher = app(\App\Services\MapBiomasSpatialMatcher::class);
        foreach ($samples as [$suffix, $latitude, $longitude, $expectedRaw]) {
            $filename = 'ember_mapbiomas_collection4_2024_'.$suffix;
            $escapedPath = str_replace('\\', '\\\\', $directory.'/'.$filename);
            $script = 'import rasterio; ds=rasterio.open('.var_export($escapedPath, true).'); row,col=ds.index('.$longitude.','.$latitude.'); print(int(ds.read(1)[row,col]))';
            exec(escapeshellarg($python).' -c '.escapeshellarg($script).' 2>NUL', $output, $exitCode);

            $this->assertSame(0, $exitCode);
            $this->assertSame($expectedRaw, (int) trim(implode('', $output)));

            $result = $matcher->match((float) $latitude, (float) $longitude);
            $this->assertNotNull($result);
            $this->assertSame((int) $expectedRaw, (int) ($result['raw_class_id'] ?? -1));
            $output = [];
        }
    }

    public function test_real_raster_samples_include_valid_zero_and_outside_results(): void
    {
        $directory = base_path('storage/app/ember/source/ember_mapbiomas_collection4');
        if (! is_dir($directory)) {
            $this->markTestSkipped('Production MapBiomas rasters are not installed');
        }

        Config::set('ember.mapbiomas.raster_directory', $directory);
        $matcher = app(\App\Services\MapBiomasSpatialMatcher::class);

        $this->assertSame('classified', $matcher->match(-3.9, 102.4)['status'] ?? null);
        $this->assertSame('zero_pixel', $matcher->match(-2.4, 107.0)['status'] ?? null);
        $this->assertSame('outside_raster', $matcher->match(-20.0, 90.0)['status'] ?? null);
    }

    public function test_new_job_dispatches_without_blocking_and_missing_raster_fails_safely(): void
    {
        Queue::fake();

        $id = DB::table('titik_lokasi')->insertGetId([
            'provinsi' => 'Aceh',
            'kabupaten_kota' => 'Test',
            'kecamatan' => 'Test',
            'desa' => 'Test',
            'latitude' => 4.5,
            'longitude' => 96.5,
            'date' => '2026-11-02',
            'confidence' => 'high',
            'is_nrt' => true,
            'classification_status' => 'pending',
        ]);

        ClassifyHotspotLandCover::dispatch($id);
        Queue::assertPushed(ClassifyHotspotLandCover::class, fn (ClassifyHotspotLandCover $job) => true);

        Config::set('ember.mapbiomas.raster_path', null);
        Config::set('ember.mapbiomas.raster_directory', '/path/that/does/not/exist');
        (new ClassifyHotspotLandCover($id))->handle(
            app(\App\Services\MapBiomasSpatialMatcher::class),
            app(\App\Services\FireSusceptibilityService::class)
        );

        $row = DB::table('titik_lokasi')->where('id', $id)->first();
        $this->assertSame('raster_unavailable', $row->classification_status);
        $this->assertSame(1, (int) $row->classification_attempts);
    }

    public function test_nrt_backfill_command_updates_only_matching_nrt_rows(): void
    {
        $nrtId = DB::table('titik_lokasi')->insertGetId([
            'provinsi' => 'Aceh',
            'kabupaten_kota' => 'Test',
            'kecamatan' => 'Test',
            'desa' => 'NRT',
            'latitude' => 4.5,
            'longitude' => 96.5,
            'date' => '2026-11-02',
            'confidence' => 'high',
            'is_nrt' => true,
            'classification_status' => 'unclassified',
        ]);
        DB::table('titik_lokasi')->insert([
            'provinsi' => 'Aceh',
            'kabupaten_kota' => 'Test',
            'kecamatan' => 'Test',
            'desa' => 'Historical',
            'latitude' => 4.5,
            'longitude' => 96.5,
            'date' => '2023-01-01',
            'confidence' => 'high',
            'is_nrt' => false,
            'classification_status' => 'classified',
            'land_cover_id' => 13,
            'land_cover' => 'Tumbuhan Non-Hutan Lainnya',
        ]);

        $csv = 'storage/app/ember/output/nrt_pipeline_test.csv';
        file_put_contents(base_path($csv),
            'hotspot_id,latitude,longitude,raw_class_id,class_name,sampling_status'."\n".
            $nrtId.',4.50000000,96.50000000,35,Palm Oil,MATCHED'."\n".
            '999999,0,0,76,UNVERIFIED,MATCHED'."\n"
        );

        $this->artisan('ember:backfill-nrt-land-cover', ['--execute' => true, '--csv' => $csv])
            ->assertSuccessful();

        $nrt = DB::table('titik_lokasi')->where('id', $nrtId)->first();
        $this->assertSame(35, (int) $nrt->land_cover_id);
        $this->assertSame(35, (int) $nrt->raw_mapbiomas_class_id);
        $this->assertSame('classified', $nrt->classification_status);
        $this->assertSame(13, (int) DB::table('titik_lokasi')->where('is_nrt', false)->value('land_cover_id'));
        $this->assertSame('Tumbuhan Non-Hutan Lainnya', DB::table('titik_lokasi')->where('is_nrt', false)->value('land_cover'));

        @unlink(base_path($csv));
    }

    public function test_nasa_firms_sync_deduplicates_and_dispatches_classification(): void
    {
        Queue::fake();
        Config::set('ember.firms.api_key', 'test-key-not-exposed');
        Config::set('ember.firms.base_url', 'https://example.test/api');

        Http::fake([
            '*' => Http::response(
                "latitude,longitude,acq_date,acq_time,confidence,satellite,instrument,daynight,frp\n".
                "4.500000,96.500000,2026-11-02,1200,high,N21,VIIRS,D,1.25\n",
                200,
                ['Content-Type' => 'text/csv']
            ),
        ]);

        $service = app(NasaFirmsSyncService::class);
        $first = $service->sync(['VIIRS_NOAA21_NRT'], 2);
        $second = $service->sync(['VIIRS_NOAA21_NRT'], 2);

        $this->assertSame(1, DB::table('titik_lokasi')->where('is_nrt', true)->count());
        $this->assertGreaterThanOrEqual(1, $first['inserted'] + $first['updated']);
        $this->assertGreaterThanOrEqual(1, $second['inserted'] + $second['updated']);
        Queue::assertPushed(ClassifyHotspotLandCover::class);
    }

    public function test_firms_dry_run_fetches_without_writing_or_dispatching_jobs(): void
    {
        Queue::fake();
        Config::set('ember.firms.api_key', 'test-key-not-exposed');
        Config::set('ember.firms.base_url', 'https://example.test/api');

        Http::fake([
            '*' => Http::response(
                "latitude,longitude,acq_date,acq_time,confidence,satellite,instrument,daynight,frp\n".
                "4.500000,96.500000,2026-11-02,1200,high,N21,VIIRS,D,1.25\n".
                "-3.900000,102.400000,2026-11-02,1210,nominal,N21,VIIRS,N,2.50\n",
                200,
                ['Content-Type' => 'text/csv']
            ),
        ]);

        $beforeRuns = DB::table('firms_sync_runs')->count();
        $beforeHotspots = DB::table('titik_lokasi')->count();
        $summary = app(NasaFirmsSyncService::class)->sync(['VIIRS_NOAA21_NRT'], 1, true, 1);

        $this->assertSame($beforeRuns, DB::table('firms_sync_runs')->count());
        $this->assertSame($beforeHotspots, DB::table('titik_lokasi')->count());
        $this->assertSame(0, $summary['inserted']);
        $this->assertSame(0, $summary['updated']);
        $this->assertSame(1, $summary['processed']);
        $this->assertSame(4.5, $summary['first_record']['latitude']);
        Queue::assertNothingPushed();
    }

    public function test_firms_limit_is_applied_before_database_persistence_and_dispatch(): void
    {
        Queue::fake();
        Config::set('ember.firms.api_key', 'test-key-not-exposed');
        Config::set('ember.firms.base_url', 'https://example.test/api');

        Http::fake([
            '*' => Http::response(
                "latitude,longitude,acq_date,acq_time,confidence,satellite,instrument,daynight,frp\n".
                "4.500000,96.500000,2026-11-02,1200,high,N21,VIIRS,D,1.25\n".
                "-3.900000,102.400000,2026-11-02,1210,nominal,N21,VIIRS,N,2.50\n",
                200,
                ['Content-Type' => 'text/csv']
            ),
        ]);

        $summary = app(NasaFirmsSyncService::class)->sync(['VIIRS_NOAA21_NRT'], 1, false, 1);

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['inserted']);
        $this->assertSame(1, DB::table('titik_lokasi')->where('data_source', 'NASA FIRMS')->count());
        Queue::assertPushed(ClassifyHotspotLandCover::class, 1);
    }
}
