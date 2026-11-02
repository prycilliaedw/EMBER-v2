<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_the_map_page_exposes_locations(): void
    {
        $locationId = DB::table('titik_lokasi')->insertGetId([
            'desa' => 'Desa Contoh',
            'latitude' => -2.56422,
            'longitude' => 102.77008,
            'confidence' => 'high',
        ]);

        $response = $this->get('/map');

        $response
            ->assertOk()
            ->assertSee('id="ember-map"', false)
            ->assertSee('-2.56422', false)
            ->assertSee('102.77008', false)
            ->assertSee('detail_url', false)
            ->assertSee('locations\\/1?lang=id', false)
            ->assertSee('id="map-detail-panel"', false)
            ->assertSee('id="map-detail-close"', false)
            ->assertSee('id="map-drilldown-control"', false)
            ->assertSee('id="map-boundary-breadcrumb"', false)
            ->assertSee('id="map-status-filter"', false)
            ->assertSee('id="map-status-filter" class="absolute bottom-28 left-3', false)
            ->assertSee('id="map-statistics-panel"', false)
            ->assertSee('id="map-yearly-donut"', false)
            ->assertSee('id="map-class-statistics"', false)
            ->assertSee('id="map-score-chart-hse"', false)
            ->assertSee('id="map-score-chart-hbe"', false)
            ->assertSee('id="map-score-chart-fsi"', false)
            ->assertSee('area_km2', false)
            ->assertSee('1. Distribusi confidence NASA')
            ->assertSee('2. Distribusi hotspot per kelas tutupan lahan')
            ->assertSee('3. HSE')
            ->assertSee('4. HBE')
            ->assertSee('5. FSI')
            ->assertSee('id="map-monthly-statistics" class="hidden', false)
            ->assertSee('data-map-month-bar="0"', false)
            ->assertSee('data-map-month-bar="11"', false)
            ->assertSee('Statistik status')
            ->assertSee('Status per bulan')
            ->assertSee('data-map-status="high"', false)
            ->assertSee('data-map-status="unrated"', false)
            ->assertSee('Pilih semua')
            ->assertSee('Klik provinsi untuk melihat kabupaten/kota.')
            ->assertSee('Buka detail lengkap')
            ->assertSee('h-[calc(100vh-6.5rem)]', false)
            ->assertDontSee('<footer', false);

        $this->assertSame(39, substr_count($response->getContent(), 'data-score-class='));

        $this->get('/map?lang=en')
            ->assertOk()
            ->assertSee('1. NASA confidence distribution')
            ->assertSee('2. Hotspot distribution by land-cover class')
            ->assertSee('3. HSE')
            ->assertSee('4. HBE')
            ->assertSee('5. FSI');

        $this->get(route('user.locations.show', ['id' => $locationId, 'lang' => 'id']))
            ->assertOk()
            ->assertSee('Desa Contoh')
            ->assertSee('Confidence')
            ->assertSee('Tinggi')
            ->assertSee('id="location-detail-map"', false)
            ->assertSee('Buka di OpenStreetMap');
    }

    public function test_map_detail_evidence_uses_persisted_hotspot_scores(): void
    {
        DB::table('titik_lokasi')->insert([
            [
                'desa' => 'Sawah',
                'latitude' => -2.5,
                'longitude' => 102.7,
                'land_cover_id' => 40,
                'land_cover' => 'Sawah',
                'hotspot_evidence' => 0,
                'historical_burn_evidence' => 0,
                'fsi_score' => 42.5,
                'classification_status' => 'classified',
            ],
            [
                'desa' => 'Mangrove',
                'latitude' => -2.6,
                'longitude' => 102.8,
                'land_cover_id' => 5,
                'land_cover' => 'Mangrove',
                'hotspot_evidence' => 99,
                'historical_burn_evidence' => 99,
                'fsi_score' => 31.5,
                'classification_status' => 'classified',
            ],
        ]);

        $response = $this->get('/map')->assertOk();
        preg_match('/<script id="ember-map-data" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches);
        $mapData = json_decode($matches[1] ?? '', true, flags: JSON_THROW_ON_ERROR);
        $locations = collect($mapData['locations'])->keyBy('land_cover_id');

        $this->assertEquals(0.0, $locations[40]['hotspot_evidence']);
        $this->assertEquals(0.0, $locations[40]['historical_burn_evidence']);
        $this->assertSame(42.5, $locations[40]['fsi_score']);
        $this->assertEquals(99.0, $locations[5]['hotspot_evidence']);
        $this->assertEquals(99.0, $locations[5]['historical_burn_evidence']);
        $this->assertSame(31.5, $locations[5]['fsi_score']);
    }

    public function test_map_exposes_collection4_candidates_with_current_class_mapping_and_suppresses_visible_overlap(): void
    {
        DB::table('titik_lokasi')->insert([
            'desa' => 'Existing NRT coordinate',
            'latitude' => -5.33841,
            'longitude' => 102.23989,
            'data_source' => 'NASA FIRMS',
            'source_product' => 'VIIRS_SNPP_NRT',
            'is_nrt' => true,
            'classification_status' => 'classified',
            'land_cover_id' => 13,
        ]);

        $response = $this->get('/map?lang=en')->assertOk();
        $candidates = $response->viewData('collection4Candidates');

        $this->assertCount(621, $candidates);
        $this->assertSame(1, $response->viewData('collection4CandidateOverlapCount'));
        $this->assertSame(620, $response->viewData('collection4CandidateVisibleCount'));
        $raw40 = collect($candidates)->firstWhere('raw_mapbiomas_class_id', 40);
        $raw76 = collect($candidates)->firstWhere('raw_mapbiomas_class_id', 76);
        $this->assertSame(40, $raw40['land_cover_id']);
        $this->assertSame(76, $raw76['land_cover_id']);
        $this->assertSame('sampled_candidate', $raw40['classification_status']);
        $this->assertTrue(collect($candidates)->contains(fn (array $candidate) =>
            $candidate['latitude'] === -5.33841
            && $candidate['longitude'] === 102.23989
            && $candidate['coincident_with_database']
        ));
        $response->assertDontSee('id="map-layer-control"', false)
            ->assertSee('data-spatial-total="33830"', false)
            ->assertSee('33,830 unique spatial points');
    }

    public function test_map_province_filter_matches_database_locations_and_dashboard(): void
    {
        DB::table('titik_lokasi')->insert([
            [
                'desa' => 'Aceh 1',
                'provinsi' => 'Aceh',
                'latitude' => 4.5,
                'longitude' => 96.5,
                'confidence' => 'high',
                'is_nrt' => 0,
            ],
            [
                'desa' => 'Riau 1',
                'provinsi' => 'Riau',
                'latitude' => 0.5,
                'longitude' => 101.5,
                'confidence' => 'high',
                'is_nrt' => 0,
            ],
        ]);

        $response = $this->get('/map?province=Aceh')->assertOk();

        $dashboard = $response->viewData('mapDashboard');
        $this->assertSame(1, $dashboard['total']);
        $this->assertSame('Aceh', $dashboard['filters']['province']);
        $response->assertSee('"provinsi":"Aceh"', false);
    }

    public function test_nrt_province_command_updates_only_missing_nrt_rows(): void
    {
        DB::table('titik_lokasi')->insert([
            [
                'desa' => 'NRT Riau',
                'provinsi' => null,
                'latitude' => 0.5,
                'longitude' => 101.5,
                'confidence' => 'high',
                'is_nrt' => 1,
            ],
            [
                'desa' => 'Historis Riau',
                'provinsi' => null,
                'latitude' => 0.5,
                'longitude' => 101.5,
                'confidence' => 'high',
                'is_nrt' => 0,
            ],
        ]);

        $this->artisan('ember:attribute-nrt-provinces')
            ->assertSuccessful()
            ->expectsOutputToContain('NRT berhasil mendapatkan provinsi: 1')
            ->expectsOutputToContain('NRT gagal mendapatkan provinsi: 0');

        $this->assertSame('Riau', DB::table('titik_lokasi')->where('desa', 'NRT Riau')->value('provinsi'));
        $this->assertNull(DB::table('titik_lokasi')->where('desa', 'Historis Riau')->value('provinsi'));
    }

    public function test_map_language_switch_links_preserve_active_filter_query(): void
    {
        $this->get('/map?lang=id&year=2023&confidence=low%2Chigh&fsi=moderate&land_cover=Sawah')
            ->assertOk()
            ->assertSee('data-language-switch="id"', false)
            ->assertSee('data-language-switch="en"', false)
            ->assertSee('lang=en&amp;year=2023&amp;confidence=low%2Chigh&amp;fsi=moderate&amp;land_cover=Sawah', false)
            ->assertSee('lang=id&amp;year=2023&amp;confidence=low%2Chigh&amp;fsi=moderate&amp;land_cover=Sawah', false);
    }

    public function test_the_public_dashboard_supports_english_and_content_navigation(): void
    {
        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('images/ember-hero-v2.jpg', false)
            ->assertSee('Early Monitoring for Burning Environment Response')
            ->assertSee('Open interactive map')
            ->assertSee('Methodology')
            ->assertSee(route('user.about', ['lang' => 'en']), false)
            ->assertSee(route('user.team', ['lang' => 'en']), false)
            ->assertSee('id="back-to-top"', false)
            ->assertSee('<body class="flex min-h-screen flex-col', false)
            ->assertSee('<main class="flex-1">', false)
            ->assertSee('header class="relative z-[1000]', false)
            ->assertDontSee('header class="sticky', false)
            ->assertSee('Recently added data');
    }

    public function test_public_search_finds_a_location_and_has_no_cms_button(): void
    {
        DB::table('titik_lokasi')->insert([
            'desa' => 'Suka Maju',
            'kecamatan' => 'Contoh',
            'latitude' => -2.92494,
            'longitude' => 104.68752,
        ]);

        $this->get('/search?q=Suka+Maju&lang=id')
            ->assertOk()
            ->assertSee('Suka Maju')
            ->assertSee('Hasil pencarian')
            ->assertDontSee('>CMS<', false);
    }

    public function test_public_can_download_location_data_as_csv(): void
    {
        DB::table('titik_lokasi')->insert([
            [
                'provinsi' => 'Sumatera Selatan',
                'kabupaten_kota' => 'Palembang',
                'kecamatan' => 'Ilir Timur I',
                'desa' => '20 Ilir D III',
                'latitude' => -2.976073,
                'longitude' => 104.775431,
                'date' => '2025-04-12',
                'confidence' => 'high',
            ],
            [
                'provinsi' => 'Aceh',
                'kabupaten_kota' => 'Banda Aceh',
                'kecamatan' => 'Kuta Alam',
                'desa' => 'Kuta Alam',
                'latitude' => 5.5483,
                'longitude' => 95.3238,
                'date' => '2024-02-10',
                'confidence' => 'medium',
            ],
        ]);

        $response = $this->get(route('user.data.download'));

        $response
            ->assertOk()
            ->assertDownload('data-titik-lokasi-ember-semua.csv');

        $content = $response->streamedContent();
        $this->assertStringContainsString('provinsi,kabupaten_kota,kecamatan,desa,latitude,longitude,date,confidence', $content);
        $rows = array_values(array_filter(preg_split('/\R/', $content)));
        $location = str_getcsv($rows[1], ',', '"', '');
        $this->assertSame(['Sumatera Selatan', 'Palembang', 'Ilir Timur I', '20 Ilir D III'], array_slice($location, 0, 4));

        $this->get(route('user.data.index', ['lang' => 'id']))
            ->assertOk()
            ->assertSee('Semua data')
            ->assertSee('Per tahun')
            ->assertSee('Per provinsi')
            ->assertSee('2025')
            ->assertSee('Sumatera Selatan');

        $yearResponse = $this->get(route('user.data.download', ['scope' => 'year', 'year' => 2025]));
        $yearResponse->assertDownload('data-titik-lokasi-ember-2025.csv');
        $this->assertStringContainsString('20 Ilir D III', $yearResponse->streamedContent());
        $this->assertStringNotContainsString('Kuta Alam', $yearResponse->streamedContent());

        $provinceResponse = $this->get(route('user.data.download', ['scope' => 'province', 'province' => 'Aceh']));
        $provinceResponse->assertDownload('data-titik-lokasi-ember-aceh.csv');
        $this->assertStringContainsString('Kuta Alam', $provinceResponse->streamedContent());
        $this->assertStringNotContainsString('20 Ilir D III', $provinceResponse->streamedContent());
    }

    public function test_map_year_filter_uses_the_available_date_range(): void
    {
        DB::table('titik_lokasi')->insert([
            [
                'latitude' => -2.56422,
                'longitude' => 102.77008,
                'date' => '2022-06-15',
            ],
            [
                'latitude' => 4.90892,
                'longitude' => 97.47369,
                'date' => '2024-09-20',
            ],
        ]);

        $this->get('/map?lang=id')
            ->assertOk()
            ->assertSee('id="map-year-range"', false)
            ->assertSee('data-year-values="2022,2024"', false)
            ->assertSee('>Semua<', false)
            ->assertSee('2 lokasi');
    }

    public function test_public_statistics_groups_location_statuses_by_year(): void
    {
        DB::table('titik_lokasi')->insert([
            ['provinsi' => 'Jambi', 'latitude' => -2.5, 'longitude' => 102.7, 'date' => '2024-02-01', 'confidence' => 'high'],
            ['provinsi' => 'Jambi', 'latitude' => -2.6, 'longitude' => 102.8, 'date' => '2024-03-01', 'confidence' => 'low'],
            ['provinsi' => 'Sumatera Selatan', 'latitude' => -2.7, 'longitude' => 102.9, 'date' => '2025-04-01', 'confidence' => null],
        ]);

        $statistics = $this->get(route('user.statistics', ['lang' => 'id']))
            ->assertOk()
            ->assertSee('Statistik Tahunan')
            ->assertSee('2024')
            ->assertSee('2025')
            ->assertSee('Tinggi')
            ->assertSee('Tanpa kelas FSI')
            ->assertSee('Tren per provinsi')
            ->assertSee('Jambi')
            ->assertSee('Sumatera Selatan')
            ->assertSee('data-province-chart', false)
            ->assertSee('data-province-period', false)
            ->assertSee('data-province-year', false)
            ->assertSee('Per bulan')
            ->assertSee('data-annual-donut', false)
            ->assertSee('data-annual-donut-year', false)
            ->assertSee('Statistik donat tahunan')
            ->assertSee('Titik spasial unik')
            ->assertSee('Tren lokasi per tahun')
            ->assertSee('Tren per provinsi')
            ->assertSee('Statistik donat tahunan')
            ->assertSee('Statistik hotspot EMBER')
            ->assertSee('A. Distribusi Confidence NASA')
            ->assertSee('B. Distribusi Hotspot per Kelas Tutupan Lahan')
            ->assertSee('C. HSE')
            ->assertSee('D. HBE')
            ->assertSee('E. Perbandingan HSE dan HBE')
            ->assertSee('F. FSI Sugeno')
            ->assertDontSee('Rekap tahunan')
            ->assertDontSee('hover:-translate-y-1', false);

        $jambi = collect($statistics->viewData('provinceStatistics'))->firstWhere('name', 'Jambi');
        $this->assertSame([0, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0], $jambi['monthly']['2024']);

        $this->get(route('user.statistics', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Annual Statistics')
            ->assertSee('No FSI class')
            ->assertSeeInOrder([
                'Location trend by year',
                'Trend by province',
                'Annual donut statistics',
                'EMBER hotspot statistics',
                'A. NASA Confidence Distribution',
                'B. Hotspot Distribution by Land-Cover Class',
                'C. HSE',
                'D. HBE',
                'E. HSE vs HBE Comparison',
                'F. Sugeno FSI',
            ]);
    }

    public function test_statistics_dashboard_uses_map_filters_and_keeps_all_land_cover_classes(): void
    {
        DB::table('titik_lokasi')->insert([
            [
                'provinsi' => 'Jambi',
                'latitude' => -2.5,
                'longitude' => 102.7,
                'date' => '2024-01-10',
                'confidence' => 85,
                'land_cover_id' => 40,
                'land_cover' => 'Sawah',
                'fsi_class' => 'High',
                'classification_status' => 'classified',
            ],
            [
                'provinsi' => 'Jambi',
                'latitude' => -2.6,
                'longitude' => 102.8,
                'date' => '2024-02-10',
                'confidence' => 55,
                'land_cover_id' => 40,
                'land_cover' => 'Sawah',
                'fsi_class' => 'Moderate',
                'classification_status' => 'classified',
            ],
            [
                'provinsi' => 'Jambi',
                'latitude' => -2.7,
                'longitude' => 102.9,
                'date' => '2023-02-10',
                'confidence' => 15,
                'land_cover_id' => 3,
                'land_cover' => 'Formasi Hutan',
                'fsi_class' => 'Low',
                'classification_status' => 'pending',
            ],
        ]);

        $query = '?lang=en&year=2024&province=Jambi&land_cover=Sawah&confidence=high%2Cnominal';
        $statistics = $this->get('/statistics'.$query)
            ->assertOk()
            ->assertSee('id="ember-statistics-dashboard"', false)
            ->assertSee('data-hotspot-total="33833"', false)
            ->assertSee('data-classified-total="33832"', false)
            ->assertSee('data-unclassified-total="1"', false)
            ->assertSee('data-confidence-total="33209"', false)
            ->assertSee('A. NASA Confidence Distribution')
            ->assertSee('B. Hotspot Distribution by Land-Cover Class')
            ->assertSee('C. HSE')
            ->assertSee('D. HBE')
            ->assertSee('E. HSE vs HBE Comparison')
            ->assertSee('F. Sugeno FSI')
            ->assertSee('data-class-row="Sawah"', false)
            ->assertSee('Other Non-Vegetated Areas')
            ->assertSee('Peat Swamp Forest')
            ->assertSee('area is the 2024', false);

        $this->assertSame(13, substr_count($statistics->getContent(), 'data-class-row='));

        $map = $this->get('/map'.$query)->assertOk();
        $mapSpatial = $map->viewData('spatialDashboard');
        $map->assertSee('id="map-class-statistics"', false)
            ->assertSee('data-hotspot-total="'.$mapSpatial['total'].'"', false)
            ->assertSee('data-classified-total="'.$mapSpatial['mapped_total'].'"', false)
            ->assertSee('data-filtered-database-total="2"', false)
            ->assertSee('data-map-statistics-link', false)
            ->assertSee('data-class-evidence', false);

        $this->get('/statistics?lang=id&year=2024&province=Jambi&land_cover=Sawah&confidence=high%2Cnominal')
            ->assertOk()
            ->assertSee('A. Distribusi Confidence NASA')
            ->assertSee('B. Distribusi Hotspot per Kelas Tutupan Lahan')
            ->assertSee('C. HSE')
            ->assertSee('D. HBE')
            ->assertSee('E. Perbandingan HSE dan HBE')
            ->assertSee('F. FSI Sugeno');
    }

    public function test_statistics_source_breakdown_uses_real_georeferenced_and_database_totals(): void
    {
        Schema::table('titik_lokasi', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->change();
            $table->decimal('longitude', 11, 8)->nullable()->change();
        });

        DB::table('titik_lokasi')->insert([
            ['provinsi' => 'A', 'kabupaten_kota' => 'A', 'kecamatan' => 'A', 'desa' => 'Historical 1', 'latitude' => -1, 'longitude' => 100, 'date' => '2023-01-01', 'confidence' => 60, 'data_source' => 'NASA FIRMS', 'source_product' => null, 'is_nrt' => false, 'classification_status' => 'classified', 'land_cover_id' => 3, 'land_cover' => 'Formasi Hutan'],
            ['provinsi' => 'A', 'kabupaten_kota' => 'A', 'kecamatan' => 'A', 'desa' => 'Historical 2', 'latitude' => -1.1, 'longitude' => 100.1, 'date' => '2023-01-02', 'confidence' => 70, 'data_source' => 'NASA FIRMS', 'source_product' => null, 'is_nrt' => false, 'classification_status' => 'classified', 'land_cover_id' => 5, 'land_cover' => 'Mangrove'],
            ['provinsi' => 'A', 'kabupaten_kota' => 'A', 'kecamatan' => 'A', 'desa' => 'NRT', 'latitude' => -1.2, 'longitude' => 100.2, 'date' => '2026-11-01', 'confidence' => 'n', 'data_source' => 'NASA FIRMS', 'source_product' => 'VIIRS_SNPP_NRT', 'is_nrt' => true, 'classification_status' => 'classified', 'land_cover_id' => 13, 'land_cover' => 'Tumbuhan Non-Hutan Lainnya'],
            ['provinsi' => 'A', 'kabupaten_kota' => 'A', 'kecamatan' => 'A', 'desa' => 'Legacy georeferenced', 'latitude' => -1.3, 'longitude' => 100.3, 'date' => '2024-01-01', 'confidence' => null, 'data_source' => null, 'source_product' => null, 'is_nrt' => false, 'classification_status' => 'pending', 'land_cover_id' => 3, 'land_cover' => 'Formasi Hutan'],
            ['provinsi' => 'A', 'kabupaten_kota' => 'A', 'kecamatan' => 'A', 'desa' => 'Legacy no coordinates', 'latitude' => null, 'longitude' => null, 'date' => '2024-01-02', 'confidence' => null, 'data_source' => null, 'source_product' => null, 'is_nrt' => false, 'classification_status' => 'pending', 'land_cover_id' => 3, 'land_cover' => 'Formasi Hutan'],
        ]);

        $response = $this->get('/statistics?lang=en')->assertOk();
        $breakdown = $response->viewData('recordBreakdown');
        $this->assertSame(4, $response->viewData('dashboard')['total']);
        $this->assertSame(3, $response->viewData('dashboard')['mapped_total']);
        $this->assertSame(1, $response->viewData('dashboard')['unclassified_total']);
        $this->assertSame(5, $breakdown['database_total']);
        $this->assertSame(4, $breakdown['georeferenced_total']);
        $this->assertSame(2, $breakdown['historical_total']);
        $this->assertSame(1, $breakdown['nrt_total']);
        $this->assertSame(2, $breakdown['legacy_total']);
        $this->assertSame(1, $breakdown['legacy_georeferenced']);
        $this->assertSame(3, $response->viewData('dashboard')['mapped_total']);
        $response->assertSee('data-hotspot-total="33832"', false)
            ->assertSee('data-classified-total="33831"', false)
            ->assertSee('data-confidence-total="33210"', false)
            ->assertDontSee('Georeferenced legacy point')
            ->assertDontSee('Coincident candidate coordinates')
            ->assertDontSee('NASA FIRMS NRT</p>', false)
            ->assertDontSee('Legacy records</p>', false)
            ->assertDontSee('FSI values are stale', false);

        $this->get('/statistics?lang=en&year=1999')
            ->assertOk()
            ->assertViewHas('dashboard.total', 0);
        $this->get('/statistics?lang=en')
            ->assertOk()
            ->assertViewHas('dashboard.total', 4);
    }

    public function test_pending_legacy_land_cover_is_not_counted_as_classified(): void
    {
        $locations = collect([
            (object) ['latitude' => -1, 'longitude' => 100, 'land_cover_id' => 3, 'land_cover' => 'Formasi Hutan', 'classification_status' => 'pending'],
            (object) ['latitude' => -1.1, 'longitude' => 100.1, 'land_cover_id' => 3, 'land_cover' => 'Formasi Hutan', 'classification_status' => 'classified'],
        ]);

        $dashboard = app(\App\Services\EmberStatisticsService::class)->dashboard($locations);

        $this->assertSame(2, $dashboard['total']);
        $this->assertSame(1, $dashboard['mapped_total']);
        $this->assertSame(1, $dashboard['unclassified_total']);
        $this->assertSame(1, collect($dashboard['classes'])->firstWhere('id', 3)['count']);
    }

    public function test_statistics_reconciles_unique_spatial_classes_and_confidence_without_guessing_legacy_class(): void
    {
        DB::table('titik_lokasi')->insert([
            'desa' => 'Verified NRT at a candidate coordinate',
            'latitude' => -5.33841,
            'longitude' => 102.23989,
            'date' => '2026-11-01',
            'confidence' => 'n',
            'data_source' => 'NASA FIRMS',
            'source_product' => 'VIIRS_SNPP_NRT',
            'is_nrt' => true,
            'classification_status' => 'classified',
            'land_cover_id' => 13,
            'land_cover' => 'Tumbuhan Non-Hutan Lainnya',
        ]);
        DB::table('titik_lokasi')->insert([
            'desa' => 'Unverified legacy class',
            'latitude' => -1,
            'longitude' => 100,
            'confidence' => 85,
            'is_nrt' => false,
            'classification_status' => 'pending',
            'land_cover_id' => 3,
        ]);

        $response = $this->get('/statistics?lang=en')->assertOk();
        $spatial = $response->viewData('spatialDashboard');
        $classes = $spatial['classes'];

        $this->assertSame(33831, $spatial['total']);
        $this->assertSame(33830, $spatial['mapped_total']);
        $this->assertSame(1, $spatial['unclassified_total']);
        $this->assertSame(1, $spatial['candidate_overlap_count']);
        $this->assertSame(620, $spatial['candidate_additional_count']);
        $this->assertSame([3, 5, 9, 13, 21, 24, 25, 30, 31, 33, 35, 40, 76], $classes->pluck('id')->all());
        $this->assertSame(33830, $classes->sum('count'));
        $this->assertSame(7335, $classes->firstWhere('id', 13)['count']);
        $this->assertSame(939, $classes->firstWhere('id', 40)['count']);
        $this->assertSame(552, $classes->firstWhere('id', 76)['count']);
        $this->assertSame(33210, $spatial['confidence_total']);
        $this->assertSame(33209, array_sum($spatial['confidence_counts']));
        $this->assertSame(33209, $spatial['confidence_category_total']);
        $this->assertSame(1, $spatial['confidence_unrated_total']);
        $unresolved = collect($spatial['unresolved_points'])->firstWhere('id', 2);
        $this->assertSame('legacy_database', $unresolved['source']);
        $this->assertEquals(-1, $unresolved['latitude']);
        $this->assertEquals(100, $unresolved['longitude']);

        $response->assertSee('data-hotspot-total="33831"', false)
            ->assertSee('data-classified-total="33830"', false)
            ->assertSee('data-confidence-total="33210"', false)
            ->assertSee('data-confidence-category-total="33209"', false)
            ->assertSee('database ID 2 at -1, 100')
            ->assertDontSee('Without a recognized class')
            ->assertDontSee('Tanpa kelas yang dikenali')
            ->assertDontSee('Georeferenced legacy point')
            ->assertDontSee('Coincident candidate coordinates')
            ->assertDontSee('NASA FIRMS NRT</p>', false);
        $response->assertDontSee('data-spatial-composition', false)
            ->assertDontSee('Belum dinilai')
            ->assertDontSee('Not Evaluated');
    }

    public function test_statistics_confidence_thresholds_use_high_nominal_low_unrated(): void
    {
        DB::table('titik_lokasi')->insert([
            ['latitude' => -1, 'longitude' => 100, 'date' => '2024-01-01', 'confidence' => 29],
            ['latitude' => -1, 'longitude' => 101, 'date' => '2024-01-02', 'confidence' => 30],
            ['latitude' => -1, 'longitude' => 102, 'date' => '2024-01-03', 'confidence' => 79],
            ['latitude' => -1, 'longitude' => 103, 'date' => '2024-01-04', 'confidence' => 80],
            ['latitude' => -1, 'longitude' => 104, 'date' => '2024-01-05', 'confidence' => null],
        ]);

        $response = $this->get('/statistics?lang=en')->assertOk();
        $summary = $response->viewData('summary');
        $databaseConfidence = $response->viewData('dashboard')['confidence'];

        $this->assertSame(1, $databaseConfidence['low']['count']);
        $this->assertSame(2, $databaseConfidence['nominal']['count']);
        $this->assertSame(1, $databaseConfidence['high']['count']);
        $this->assertSame(1, $databaseConfidence['unrated']['count']);
        $this->assertSame(33209, $summary['confidence_total']);
        $this->assertSame($response->viewData('spatialDashboard')['total'], $summary['total']);
        $this->assertSame(5, $response->viewData('dashboard')['total']);
    }

    public function test_spatial_totals_and_confidence_scope_are_consistent_across_public_pages(): void
    {
        DB::table('titik_lokasi')->insert([
            [
                'desa' => 'Legacy point', 'latitude' => -1, 'longitude' => 100,
                'date' => null, 'confidence' => null, 'data_source' => null,
                'source_product' => null, 'classification_status' => 'pending',
                'land_cover_id' => null, 'land_cover' => null, 'is_nrt' => false,
            ],
            [
                'desa' => null, 'latitude' => -5.33841, 'longitude' => 102.23989,
                'date' => '2026-11-01', 'confidence' => 'n', 'data_source' => 'NASA FIRMS',
                'source_product' => 'VIIRS_SNPP_NRT', 'is_nrt' => true,
                'classification_status' => 'classified', 'land_cover_id' => 13,
                'land_cover' => 'Tumbuhan Non-Hutan Lainnya',
            ],
        ]);

        foreach (['id', 'en'] as $language) {
            $home = $this->get('/?lang='.$language)->assertOk();
            $home->assertSee('33,831');
            $home->assertDontSee('Desa belum tersedia');
            $home->assertDontSee('Village not available');

            $map = $this->get('/map?lang='.$language)->assertOk();
            $map->assertSee('data-hotspot-total="33831"', false)
                ->assertSee('data-classified-total="33830"', false)
                ->assertSee('data-spatial-total="33831"', false)
                ->assertSee($language === 'en' ? 'classifiable values' : 'nilai berkategori')
                ->assertDontSee('id="map-layer-control"', false);

            $statistics = $this->get('/statistics?lang='.$language)->assertOk();
            $spatial = $statistics->viewData('spatialDashboard');
            $statistics->assertSee('data-hotspot-total="33831"', false)
                ->assertSee('data-classified-total="33830"', false)
                ->assertSee('data-confidence-total="33210"', false)
                ->assertSee('data-confidence-category-total="33209"', false)
                ->assertDontSee('data-spatial-composition', false)
                ->assertDontSee('33,211')
                ->assertDontSee('Belum dinilai')
                ->assertDontSee('Not Evaluated');
            $this->assertSame(13, substr_count($statistics->getContent(), 'data-class-row='));
            $this->assertSame(33830, $spatial['classes']->sum('count'));
            $this->assertSame(6807, $spatial['confidence_counts']['high']);
            $this->assertSame(23999, $spatial['confidence_counts']['nominal']);
            $this->assertSame(2403, $spatial['confidence_counts']['low']);
            $this->assertSame(33209, array_sum($spatial['confidence_counts']));
        }

        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        $bundlePath = public_path('build/'.$manifest['resources/js/app.js']['file']);
        $bundle = file_get_contents($bundlePath);
        $this->assertStringContainsString(basename($bundlePath), $map->getContent());
        $this->assertStringContainsString('confidenceLocations', file_get_contents(resource_path('js/app.js')));
        $this->assertStringContainsString('map-yearly-total', $bundle);
        $this->assertStringNotContainsString('map-layer-control', $bundle);
        $this->assertStringNotContainsString('33211', $bundle);
    }

    public function test_confidence_not_evaluated_is_not_counted_as_low(): void
    {
        DB::table('titik_lokasi')->insert([
            [
                'latitude' => -1.0,
                'longitude' => 102.0,
                'date' => '2026-01-01',
                'confidence' => 25,
            ],
            [
                'latitude' => -1.1,
                'longitude' => 102.1,
                'date' => '2026-01-01',
                'confidence' => 'Not Evaluated',
            ],
        ]);

        $locations = DB::table('titik_lokasi')->whereNotNull('latitude')->whereNotNull('longitude')->get();
        $dashboard = app(\App\Services\EmberStatisticsService::class)->dashboard($locations);

        $this->assertSame(1, $dashboard['confidence']['low']['count']);
        $this->assertSame(0, $dashboard['confidence']['nominal']['count']);
        $this->assertSame(0, $dashboard['confidence']['high']['count']);
        $this->assertSame(1, $dashboard['confidence']['unrated']['count']);
        $this->assertSame(50.0, $dashboard['confidence']['unrated']['share']);
    }

    public function test_statistics_page_uses_thirteen_current_class_baselines_and_sugeno_results(): void
    {
        $statistics = app(\App\Services\EmberStatisticsService::class);
        $classes = $statistics->classDefinitions();
        $service = app(\App\Services\FireSusceptibilityService::class);

        $this->assertCount(13, $classes);
        $this->assertSame([3, 5, 9, 13, 21, 24, 25, 30, 31, 33, 35, 40, 76], $classes->pluck('id')->all());

        foreach ($classes as $class) {
            $expectedFsi = $service->fsi($class['hse'], $class['hbe']);
            $this->assertEquals($expectedFsi, $class['fsi']);
            $this->assertSame($service->fsiClass($expectedFsi), $class['fsi_class']);
            $this->assertNotNull($class['context_flag']);
        }

        $this->get(route('user.statistics', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('E. HSE vs HBE Comparison')
            ->assertSee('F. Sugeno FSI — Relative Susceptibility Index')
            ->assertSee('fixed HSE and HBE baselines');
    }

    public function test_fixed_class_baselines_do_not_change_when_an_nrt_hotspot_is_added(): void
    {
        $statistics = app(\App\Services\EmberStatisticsService::class);
        $before = $statistics->classDefinitions()->mapWithKeys(fn (array $class) => [
            $class['id'] => ['hse' => $class['hse'], 'hbe' => $class['hbe'], 'fsi' => $class['fsi']],
        ])->all();

        DB::table('titik_lokasi')->insert([
            'latitude' => -4.74858,
            'longitude' => 104.74796,
            'date' => '2026-11-02',
            'confidence' => 95,
            'is_nrt' => true,
            'land_cover_id' => 21,
            'land_cover' => 'Pertanian Lainnya',
            'classification_status' => 'classified',
        ]);

        $after = $statistics->classDefinitions()->mapWithKeys(fn (array $class) => [
            $class['id'] => ['hse' => $class['hse'], 'hbe' => $class['hbe'], 'fsi' => $class['fsi']],
        ])->all();

        $this->assertSame($before, $after);
        $this->assertCount(13, $after);
    }

    public function test_dashboard_reports_persisted_fsi_records_with_a_stale_model_version(): void
    {
        DB::table('titik_lokasi')->insert([
            'latitude' => -4.74858,
            'longitude' => 104.74796,
            'date' => '2026-11-02',
            'confidence' => 95,
            'land_cover_id' => 21,
            'land_cover' => 'Pertanian Lainnya',
            'model_version' => 'OLD-MODEL-VERSION',
            'fsi_score' => 40,
        ]);

        $locations = DB::table('titik_lokasi')->whereNotNull('latitude')->whereNotNull('longitude')->get();
        $dashboard = app(\App\Services\EmberStatisticsService::class)->dashboard($locations);

        $this->assertSame(1, $dashboard['stale_persisted_fsi_count']);
        $this->assertSame(config('ember.model_version'), $dashboard['model_version']);
    }

    public function test_land_cover_percentages_use_two_decimals_for_small_classes(): void
    {
        $locations = collect();

        for ($index = 0; $index < 33193; $index++) {
            $locations->push((object) [
                'land_cover_id' => 3,
                'land_cover' => 'Formasi Hutan',
                'confidence' => 85,
                'classification_status' => 'classified',
            ]);
        }

        for ($index = 0; $index < 16; $index++) {
            $locations->push((object) [
                'land_cover_id' => 76,
                'land_cover' => 'Tambak',
                'confidence' => 85,
                'classification_status' => 'classified',
            ]);
        }

        $dashboard = app(\App\Services\EmberStatisticsService::class)->dashboard($locations);
        $tambak = collect($dashboard['classes'])->firstWhere('id', 76);

        $this->assertSame(33209, $dashboard['mapped_total']);
        $this->assertSame(16, $tambak['count']);
        $this->assertEqualsWithDelta(0.05, $tambak['share'], 0.001);
    }

    public function test_methodology_renders_one_bilingual_cms_document_and_keeps_both_editor_fields(): void
    {
        $contentId = '<div><h1>Approved methodology ID</h1><h2>HSE</h2><p>2021–2023 confidence-weighted density normalized 0–100 across 13 MapBiomas Landy classes.</p><h2>HBE</h2><p>Burned-area proportion and frequency; provisional pre-2024 reconstruction.</p><h2>Zero-Order Sugeno FIS</h2><p>Separate HSE and HBE inputs, quartile anchors, and output constants 10, 30, 50, 70, 90.</p><h2>Research status</h2><p>Independent validation and temporal alignment limitations.</p></div>';
        $contentEn = '<div><h1>Approved methodology EN</h1><h2>HSE</h2><p>2021–2023 confidence-weighted density normalized 0–100 across 13 MapBiomas Landy classes.</p><h2>HBE</h2><p>Burned-area proportion and frequency; provisional pre-2024 reconstruction.</p><h2>Zero-Order Sugeno FIS</h2><p>Separate HSE and HBE inputs, quartile anchors, and output constants 10, 30, 50, 70, 90.</p><h2>Research status</h2><p>Independent validation and temporal alignment limitations.</p></div>';

        DB::table('methodology_pages')->insert([
            'title_id' => 'Metodologi EMBER',
            'title_en' => 'EMBER Methodology',
            'content_id' => $contentId,
            'content_en' => $contentEn,
        ]);

        $this->get('/methodology?lang=id')
            ->assertOk()
            ->assertSee('Approved methodology ID')
            ->assertSee('2021–2023 confidence-weighted density')
            ->assertSee('class="methodology-document w-full max-w-none m-0 p-0"', false)
            ->assertDontSee('.methodology-document [style*="background: #050819"]')
            ->assertDontSee('.methodology-document h1 {')
            ->assertSee('Early Monitoring for Burning Environment Response.')
            ->assertDontSee('Additional methodology notes')
            ->assertDontSee('Prior LCS')
            ->assertDontSee('Hotspot context and relative fire susceptibility');

        $this->get('/methodology?lang=en')
            ->assertOk()
            ->assertSee('Approved methodology EN')
            ->assertSee('provisional pre-2024 reconstruction')
            ->assertDontSee('Catatan metodologi tambahan')
            ->assertDontSee('Prior LCS');

        $editor = $this->actingAs(User::factory()->create())
            ->get(route('cms.methodology.edit'))
            ->assertOk()
            ->assertSee('id="content_id"', false)
            ->assertSee('id="content_en"', false)
            ->assertSee('Approved methodology ID')
            ->assertSee('Approved methodology EN');

        $this->assertSame(1, substr_count($editor->getContent(), 'Approved methodology ID'));
        $this->assertSame(1, substr_count($editor->getContent(), 'Approved methodology EN'));
    }
}
