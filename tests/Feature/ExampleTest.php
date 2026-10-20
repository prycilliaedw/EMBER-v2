<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        $this->get(route('user.statistics', ['lang' => 'id']))
            ->assertOk()
            ->assertSee('Statistik Tahunan')
            ->assertSee('2024')
            ->assertSee('2025')
            ->assertSee('Tinggi')
            ->assertSee('Belum dinilai')
            ->assertSee('Tren per provinsi')
            ->assertSee('Jambi')
            ->assertSee('Sumatera Selatan')
            ->assertSee('data-province-chart', false)
            ->assertSee('data-province-period', false)
            ->assertSee('data-province-year', false)
            ->assertSee('Per bulan')
            ->assertSee('"monthly":{"2024":[0,1,1', false)
            ->assertSee('data-annual-donut', false)
            ->assertSee('data-annual-donut-year', false)
            ->assertSee('Statistik donat tahunan')
            ->assertSeeInOrder([
                'Semua lokasi',
                'Tren lokasi per tahun',
                'Tren per provinsi',
                'Statistik donat tahunan',
                'Statistik hotspot EMBER',
                'A. Distribusi Confidence NASA',
                'B. Distribusi Hotspot per Kelas Tutupan Lahan',
                'C. HSE',
                'D. HBE',
                'E. FSI',
            ])
            ->assertDontSee('Rekap tahunan')
            ->assertDontSee('hover:-translate-y-1', false);

        $this->get(route('user.statistics', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Annual Statistics')
            ->assertSee('Unrated')
            ->assertSeeInOrder([
                'Location trend by year',
                'Trend by province',
                'Annual donut statistics',
                'EMBER hotspot statistics',
                'A. NASA Confidence Distribution',
                'B. Hotspot Distribution by Land-Cover Class',
                'C. HSE',
                'D. HBE',
                'E. FSI',
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
            ],
        ]);

        $query = '?lang=en&year=2024&province=Jambi&land_cover=Sawah&confidence=high%2Cnominal';
        $statistics = $this->get('/statistics'.$query)
            ->assertOk()
            ->assertSee('id="ember-statistics-dashboard"', false)
            ->assertSee('data-hotspot-total="2"', false)
            ->assertSee('data-classified-total="2"', false)
            ->assertSee('A. NASA Confidence Distribution')
            ->assertSee('B. Hotspot Distribution by Land-Cover Class')
            ->assertSee('C. HSE')
            ->assertSee('D. HBE')
            ->assertSee('E. FSI')
            ->assertSee('data-class-row="Sawah"', false)
            ->assertSee('Other Non-Vegetated Areas')
            ->assertSee('Peat Swamp Forest')
            ->assertSee('area is the 2024', false);

        $this->assertSame(13, substr_count($statistics->getContent(), 'data-class-row='));

        $this->get('/map'.$query)
            ->assertOk()
            ->assertSee('id="map-class-statistics"', false)
            ->assertSee('data-hotspot-total="2"', false)
            ->assertSee('data-classified-total="2"', false)
            ->assertSee('data-map-statistics-link', false)
            ->assertSee('data-class-evidence', false);

        $this->get('/statistics?lang=id&year=2024&province=Jambi&land_cover=Sawah&confidence=high%2Cnominal')
            ->assertOk()
            ->assertSee('A. Distribusi Confidence NASA')
            ->assertSee('B. Distribusi Hotspot per Kelas Tutupan Lahan')
            ->assertSee('C. HSE')
            ->assertSee('D. HBE')
            ->assertSee('E. FSI');
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
