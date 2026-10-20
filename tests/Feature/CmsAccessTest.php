<?php

namespace Tests\Feature;

use App\Livewire\Cms\LocationCsvImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CmsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_cms_login(): void
    {
        $this->get('/cms/titik-lokasi')
            ->assertRedirect(route('cms.login'));

        $this->get('/cms/geojson')
            ->assertRedirect(route('cms.login'));

        $this->get('/cms/faq')
            ->assertRedirect(route('cms.login'));

        $this->get('/cms/activities')
            ->assertRedirect(route('cms.login'));
    }

    public function test_admin_can_login_and_view_location_detail(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@ember.test',
            'password' => 'password',
        ]);

        $locationId = DB::table('titik_lokasi')->insertGetId([
            'latitude' => -2.56422,
            'longitude' => 102.77008,
            'confidence' => 'high',
        ]);

        $this->post('/cms/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('cms.locations.index'));

        $this->get(route('cms.locations.show', $locationId))
            ->assertOk()
            ->assertSee('Detail lokasi')
            ->assertSee('Tinggi')
            ->assertSee('-2.56422');
    }

    public function test_authenticated_admin_can_manage_about_and_team(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('cms.about.edit'))
            ->assertOk()
            ->assertDontSee('name="image_id"', false)
            ->assertDontSee('name="image_en"', false);

        $this->actingAs($admin)
            ->put(route('cms.about.update'), [
                'content_id' => 'Sistem pemantauan dini.',
                'content_en' => 'An early monitoring system.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('about_pages', [
            'content_id' => 'Sistem pemantauan dini.',
            'content_en' => 'An early monitoring system.',
        ]);

        $this->get(route('user.about', ['lang' => 'id']))
            ->assertOk()
            ->assertSee('Sistem pemantauan dini.')
            ->assertDontSee('Tentang EMBER')
            ->assertDontSee('Gambar belum tersedia');

        $this->actingAs($admin)
            ->post(route('cms.team.store'), [
                'photo' => 'references/team.jpg',
                'nama' => 'Tim Monitoring',
                'npm' => '2312345678',
                'github_url' => 'https://github.com/ember-team/monitoring',
                'description_id' => 'Mengelola data dan pengembangan sistem EMBER.',
                'description_en' => 'Manages EMBER data and system development.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('team_members', [
            'nama' => 'Tim Monitoring',
            'npm' => '2312345678',
            'github_url' => 'https://github.com/ember-team/monitoring',
            'bio_id' => 'Mengelola data dan pengembangan sistem EMBER.',
            'bio_en' => 'Manages EMBER data and system development.',
            'is_active' => true,
        ]);

        $this->get(route('user.team', ['lang' => 'id']))
            ->assertOk()
            ->assertSee('Mengelola data dan pengembangan sistem EMBER.')
            ->assertSee('Repository GitHub')
            ->assertSee('https://github.com/ember-team/monitoring');

        $this->get(route('user.team', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Manages EMBER data and system development.')
            ->assertSee('GitHub Repository');
    }

    public function test_admin_can_manage_bilingual_methodology_and_public_can_switch_language(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('cms.methodology.update'), [
                'content_id' => 'Penjelasan dalam bahasa Indonesia.',
                'content_en' => 'Explanation in English.',
            ])
            ->assertSessionHas('success');

        $this->get(route('user.methodology', ['lang' => 'id']))
            ->assertOk()
            ->assertSee('Penjelasan dalam bahasa Indonesia.')
            ->assertDontSee('Metodologi EMBER');

        $this->get(route('user.methodology', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('Explanation in English.')
            ->assertDontSee('EMBER Methodology');
    }

    public function test_public_navigation_labels_follow_selected_language(): void
    {
        $this->get(route('user.dashboard', ['lang' => 'id']))
            ->assertOk()
            ->assertSeeInOrder(['Beranda', 'Peta &amp; Data', 'Statistik', 'Tentang', 'Metodologi', 'Tim'], false)
            ->assertSee('Unduh Data')
            ->assertSee(route('user.data.index', ['lang' => 'id']), false);

        $this->get(route('user.dashboard', ['lang' => 'en']))
            ->assertOk()
            ->assertSeeInOrder(['Home', 'Map &amp; Data', 'Statistics', 'About', 'Methodology', 'Team'], false)
            ->assertSee('Data Download');
    }

    public function test_authenticated_admin_can_edit_location_details(): void
    {
        $admin = User::factory()->create();
        $locationId = DB::table('titik_lokasi')->insertGetId([
            'latitude' => -2.92494,
            'longitude' => 104.68752,
        ]);

        $this->actingAs($admin)
            ->get(route('cms.locations.edit', $locationId))
            ->assertOk()
            ->assertSee('Edit Detail Lokasi');

        $this->actingAs($admin)
            ->put(route('cms.locations.update', $locationId), [
                'provinsi' => 'Sumatera Selatan',
                'kabupaten_kota' => 'Palembang',
                'kecamatan' => 'Ilir Timur',
                'desa' => 'Contoh Desa',
                'latitude' => -2.92494,
                'longitude' => 104.68752,
                'date' => '2026-08-10',
                'confidence' => 'high',
            ])
            ->assertRedirect(route('cms.locations.show', $locationId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('titik_lokasi', [
            'id' => $locationId,
            'provinsi' => 'Sumatera Selatan',
            'kabupaten_kota' => 'Palembang',
            'confidence' => 'high',
        ]);
    }

    public function test_authenticated_admin_can_add_location(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('cms.locations.create'))
            ->assertOk()
            ->assertSee('Tambah Titik Lokasi');

        $response = $this->actingAs($admin)
            ->post(route('cms.locations.store'), [
                'provinsi' => 'Aceh',
                'kabupaten_kota' => 'Aceh Utara',
                'kecamatan' => 'Muara Batu',
                'desa' => 'Contoh Desa Baru',
                'latitude' => 4.90892,
                'longitude' => 97.47369,
                'date' => '2026-08-10',
                'confidence' => 'high',
            ]);

        $locationId = DB::table('titik_lokasi')->where('desa', 'Contoh Desa Baru')->value('id');

        $response
            ->assertRedirect(route('cms.locations.show', $locationId))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('titik_lokasi', [
            'id' => $locationId,
            'provinsi' => 'Aceh',
            'desa' => 'Contoh Desa Baru',
            'confidence' => 'high',
        ]);
    }

    public function test_authenticated_admin_can_download_location_csv_template(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('cms.locations.index'))
            ->assertOk()
            ->assertSee('Import titik lokasi dari CSV')
            ->assertSee(route('cms.locations.template'));

        $this->actingAs($admin)
            ->get(route('cms.locations.template'))
            ->assertOk()
            ->assertDownload('template-import-titik-lokasi.csv');
    }

    public function test_authenticated_admin_can_import_locations_from_csv(): void
    {
        $admin = User::factory()->create();
        $csv = implode("\n", [
            'provinsi,kabupaten_kota,kecamatan,desa,latitude,longitude,date,confidence',
            'Sumatera Selatan,Palembang,Ilir Timur I,20 Ilir D III,-2.97607300,104.77543100,2026-08-11,high',
            'Kalimantan Tengah,Kotawaringin Timur,Mentaya Hilir Selatan,Sebamban,-2.81430000,112.95370000,2026-08-11,85',
        ]);

        Livewire::actingAs($admin)
            ->test(LocationCsvImport::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('titik-lokasi.csv', $csv))
            ->call('importCsv')
            ->assertRedirect(route('cms.locations.index'))
            ->assertSessionHas('success', '2 titik lokasi berhasil diimpor dari CSV.');

        $this->assertDatabaseHas('titik_lokasi', [
            'desa' => '20 Ilir D III',
            'confidence' => 'high',
        ]);
        $this->assertDatabaseHas('titik_lokasi', [
            'desa' => 'Sebamban',
            'confidence' => '85',
        ]);
    }

    public function test_invalid_csv_row_cancels_the_whole_location_import(): void
    {
        $admin = User::factory()->create();
        $csv = implode("\n", [
            'provinsi,kabupaten_kota,kecamatan,desa,latitude,longitude,date,confidence',
            'Aceh,Aceh Utara,Muara Batu,Contoh Desa,4.90892,97.47369,2026-08-11,high',
            'Aceh,Aceh Utara,Muara Batu,Koordinat Salah,-95,200,11-08-2026,high',
        ]);

        Livewire::actingAs($admin)
            ->test(LocationCsvImport::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('titik-lokasi.csv', $csv))
            ->call('importCsv')
            ->assertHasErrors('csvFile')
            ->assertSet('importErrors', fn (array $errors) => count($errors) === 3);

        $this->assertDatabaseCount('titik_lokasi', 0);
    }

    public function test_location_import_accepts_excel_semicolon_csv_with_decimal_commas(): void
    {
        $admin = User::factory()->create();
        $csv = implode("\n", [
            'provinsi;kabupaten_kota;kecamatan;desa;latitude;longitude;date;confidence',
            'Sumatera Selatan;;;;-2,56422;102,77008;;',
            'Aceh;;;;4,90892;97,47369;;',
        ]);

        Livewire::actingAs($admin)
            ->test(LocationCsvImport::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('titik-lokasi.csv', $csv))
            ->call('importCsv')
            ->assertRedirect(route('cms.locations.index'))
            ->assertSessionHas('success', '2 titik lokasi berhasil diimpor dari CSV.');

        $this->assertDatabaseHas('titik_lokasi', [
            'latitude' => -2.56422,
            'longitude' => 102.77008,
        ]);
    }

    public function test_location_import_accepts_tab_separated_csv_coordinates(): void
    {
        $admin = User::factory()->create();
        $csv = implode("\n", [
            "provinsi\tkabupaten_kota\tkecamatan\tdesa\tlatitude\tlongitude\tdate\tconfidence",
            "Aceh\t\t\t\t4.80683\t97.66273\t\t",
        ]);

        Livewire::actingAs($admin)
            ->test(LocationCsvImport::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('titik-lokasi.csv', $csv))
            ->call('importCsv')
            ->assertRedirect(route('cms.locations.index'));

        $this->assertDatabaseHas('titik_lokasi', [
            'latitude' => 4.80683,
            'longitude' => 97.66273,
        ]);
    }

    public function test_location_import_accepts_day_month_two_digit_year_dates(): void
    {
        $admin = User::factory()->create();
        $csv = implode("\n", [
            'provinsi,kabupaten_kota,kecamatan,desa,latitude,longitude,date,confidence',
            'Aceh,Aceh Utara,Muara Batu,Contoh Desa,4.90892,97.47369,01/05/26,high',
            'Aceh,Aceh Utara,Muara Batu,Contoh Desa,4.80683,97.66273,08/05/26,high',
        ]);

        Livewire::actingAs($admin)
            ->test(LocationCsvImport::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('titik-lokasi.csv', $csv))
            ->call('importCsv')
            ->assertRedirect(route('cms.locations.index'));

        $this->assertDatabaseHas('titik_lokasi', ['date' => '2026-05-01']);
        $this->assertDatabaseHas('titik_lokasi', ['date' => '2026-05-08']);
    }

    public function test_location_import_skips_rows_without_both_coordinates(): void
    {
        $admin = User::factory()->create();
        $csv = implode("\n", [
            'provinsi,kabupaten_kota,kecamatan,desa,latitude,longitude,date,confidence',
            'Aceh,Aceh Utara,Muara Batu,Data Valid,4.90892,97.47369,01/05/26,high',
            'Aceh,Aceh Utara,Muara Batu,Baris Tanpa Koordinat,,,02/05/26,high',
        ]);

        Livewire::actingAs($admin)
            ->test(LocationCsvImport::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('titik-lokasi.csv', $csv))
            ->call('importCsv')
            ->assertRedirect(route('cms.locations.index'))
            ->assertSessionHas('success', '1 titik lokasi berhasil diimpor dari CSV.');

        $this->assertDatabaseCount('titik_lokasi', 1);
        $this->assertDatabaseHas('titik_lokasi', ['desa' => 'Data Valid']);
    }

    public function test_location_import_rejects_row_with_only_one_coordinate(): void
    {
        $admin = User::factory()->create();
        $csv = implode("\n", [
            'provinsi,kabupaten_kota,kecamatan,desa,latitude,longitude,date,confidence',
            'Aceh,Aceh Utara,Muara Batu,Koordinat Tidak Lengkap,4.90892,,01/05/26,high',
        ]);

        Livewire::actingAs($admin)
            ->test(LocationCsvImport::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('titik-lokasi.csv', $csv))
            ->call('importCsv')
            ->assertHasErrors('csvFile')
            ->assertSet('importErrors', fn (array $errors) => count($errors) === 1);

        $this->assertDatabaseCount('titik_lokasi', 0);
    }

    public function test_location_import_repairs_spreadsheet_formatted_indonesian_coordinates_and_dates(): void
    {
        $admin = User::factory()->create();
        $csv = implode("\n", [
            'provinsi,kabupaten_kota,kecamatan,desa,latitude,longitude,date,confidence',
            'Sumatera Utara,Kota Gunungsitoli,Gunungsitoli,Ilir,1.2832,976.189,1/1/2021,0',
            'Sumatera Barat,Pesisir Selatan,Ranah Ampek Hulu Tapan,Kubu Tapan,-22.008,1.010.151,1/5/2021,39',
            'Sumatera Barat,Pasaman,Lubuk Sikaping,Pauah,143,1.001.839,1/5/2021,12',
            'Sumatera Utara,Deli Serdang,Pantai Labu,Denai Sarang Burung,3.6635,989.176,1/12/2021,41',
            'Sumatera Barat,Pesisir Selatan,Ranah Ampek Hulu Tapan,Kubu Tapan,-22.109,1.010.689,1/16/2021,54',
        ]);

        Livewire::actingAs($admin)
            ->test(LocationCsvImport::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('titik-lokasi.csv', $csv))
            ->call('importCsv')
            ->assertRedirect(route('cms.locations.index'))
            ->assertSessionHas('success', '5 titik lokasi berhasil diimpor dari CSV.');

        $this->assertDatabaseHas('titik_lokasi', [
            'latitude' => -2.2008,
            'longitude' => 101.0151,
            'date' => '2021-01-05',
        ]);
        $this->assertDatabaseHas('titik_lokasi', [
            'latitude' => 1.43,
            'longitude' => 100.1839,
        ]);
        $this->assertDatabaseHas('titik_lokasi', [
            'longitude' => 101.0689,
            'date' => '2021-01-16',
        ]);
    }

    public function test_location_import_repairs_spreadsheet_coordinates_without_province(): void
    {
        $admin = User::factory()->create();
        $csv = implode("\n", [
            'provinsi,kabupaten_kota,kecamatan,desa,latitude,longitude,date,confidence',
            ',,,,1.2832,976.189,,',
            ',,,,-22.008,1.010.151,,',
            ',,,,143,1.001.839,,',
            ',,,,1.907,1.027.631,,',
        ]);

        Livewire::actingAs($admin)
            ->test(LocationCsvImport::class)
            ->set('csvFile', UploadedFile::fake()->createWithContent('titik-lokasi.csv', $csv))
            ->call('importCsv')
            ->assertRedirect(route('cms.locations.index'));

        $this->assertDatabaseHas('titik_lokasi', ['latitude' => 1.2832, 'longitude' => 97.6189]);
        $this->assertDatabaseHas('titik_lokasi', ['latitude' => -2.2008, 'longitude' => 101.0151]);
        $this->assertDatabaseHas('titik_lokasi', ['latitude' => 1.43, 'longitude' => 100.1839]);
        $this->assertDatabaseHas('titik_lokasi', ['latitude' => 1.907, 'longitude' => 102.7631]);
    }

    public function test_authenticated_admin_can_upload_and_delete_reference_photo(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('cms.references.store'), [
                'title' => 'Foto Tim',
                'alt_text' => 'Tim EMBER',
                'photo' => UploadedFile::fake()->image('tim-ember.jpg', 800, 600),
            ])
            ->assertSessionHas('success');

        $photo = DB::table('photo_references')->where('title', 'Foto Tim')->first();

        $this->assertNotNull($photo);
        Storage::disk('public')->assertExists($photo->photo_path);

        $this->actingAs($admin)
            ->delete(route('cms.references.destroy', $photo->id))
            ->assertSessionHas('success');

        Storage::disk('public')->assertMissing($photo->photo_path);
        $this->assertDatabaseMissing('photo_references', ['id' => $photo->id]);
    }

    public function test_authenticated_admin_can_upload_and_delete_geojson_layer(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $geojson = json_encode([
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => ['name' => 'Sumatera'],
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [[[95, -6], [106, -6], [106, 6], [95, -6]]],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->actingAs($admin)
            ->get(route('cms.geojson.index'))
            ->assertOk()
            ->assertSee('Mengunggah bagian');

        $this->actingAs($admin)
            ->post(route('cms.geojson.store'), [
                'name' => 'Batas Sumatera',
                'description' => 'Batas wilayah Pulau Sumatera.',
                'geojson' => UploadedFile::fake()->createWithContent('sumatera.geojson', $geojson),
                'administrative_level' => 'province',
                'is_active' => '1',
            ])
            ->assertSessionHas('success');

        $layer = DB::table('geojson_layers')->where('name', 'Batas Sumatera')->first();

        $this->assertNotNull($layer);
        $this->assertSame('FeatureCollection', $layer->geojson_type);
        $this->assertSame(1, $layer->feature_count);
        Storage::disk('public')->assertExists($layer->file_path);

        $this->actingAs($admin)
            ->delete(route('cms.geojson.destroy', $layer->id))
            ->assertSessionHas('success');

        Storage::disk('public')->assertMissing($layer->file_path);
        $this->assertDatabaseMissing('geojson_layers', ['id' => $layer->id]);
    }

    public function test_authenticated_admin_can_upload_pmtiles_and_public_can_request_a_byte_range(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $contents = "PMTiles\x03".str_repeat("\0", 248);

        $this->actingAs($admin)
            ->post(route('cms.geojson.store'), [
                'name' => 'Batas Kecamatan Sumatera',
                'description' => 'Layer kecamatan ringkas.',
                'geojson' => UploadedFile::fake()->createWithContent('kecamatan_sumatera_ringkas.pmtiles', $contents),
                'administrative_level' => 'district',
                'is_active' => '1',
            ])
            ->assertSessionHas('success');

        $layer = DB::table('geojson_layers')->where('name', 'Batas Kecamatan Sumatera')->first();

        $this->assertNotNull($layer);
        $this->assertSame('pmtiles', $layer->file_format);
        $this->assertSame('PMTiles v3', $layer->geojson_type);

        $this->actingAs($admin)
            ->patch(route('cms.geojson.update', $layer->id), [
                'is_active' => '1',
                'administrative_level' => 'district',
                'min_zoom' => 6,
                'max_zoom' => 10,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('geojson_layers', [
            'id' => $layer->id,
            'is_active' => true,
            'min_zoom' => 6,
            'max_zoom' => 10,
        ]);

        $response = $this->withHeader('Range', 'bytes=0-7')
            ->get(route('map-layers.show', $layer->id));

        $response
            ->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Range', 'bytes 0-7/256')
            ->assertHeader('Content-Type', 'application/vnd.pmtiles');

        $this->assertSame("PMTiles\x03", $response->streamedContent());

        $this->get(route('user.map'))
            ->assertOk()
            ->assertSee('Batas Kecamatan Sumatera')
            ->assertSee('map-layers\\/'.$layer->id, false)
            ->assertSee('"minZoom":6', false)
            ->assertSee('"maxZoom":10', false);
    }

    public function test_authenticated_admin_can_upload_geojson_in_small_chunks(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $uploadId = 'ember-chunked-upload-1234567890';
        $geojson = json_encode([
            'type' => 'FeatureCollection',
            'features' => [],
        ], JSON_THROW_ON_ERROR);
        $splitAt = (int) ceil(strlen($geojson) / 2);
        $chunks = [substr($geojson, 0, $splitAt), substr($geojson, $splitAt)];

        foreach ($chunks as $index => $contents) {
            $this->actingAs($admin)
                ->postJson(route('cms.geojson.upload-chunk'), [
                    'upload_id' => $uploadId,
                    'chunk_index' => $index,
                    'total_chunks' => count($chunks),
                    'original_name' => 'desa-sumatera.geojson',
                    'chunk' => UploadedFile::fake()->createWithContent('chunk.part', $contents),
                ])
                ->assertOk()
                ->assertJsonPath('received_chunks', $index + 1);
        }

        $this->actingAs($admin)
            ->post(route('cms.geojson.store'), [
                'name' => 'Desa Sumatera Chunked',
                'upload_token' => $uploadId,
                'administrative_level' => 'village',
                'min_zoom' => 6,
                'max_zoom' => 13,
            ])
            ->assertSessionHas('success');

        $layer = DB::table('geojson_layers')->where('name', 'Desa Sumatera Chunked')->first();
        $this->assertNotNull($layer);
        $this->assertSame('FeatureCollection', $layer->geojson_type);
        Storage::disk('public')->assertExists($layer->file_path);
    }

    public function test_geojson_chunk_upload_accepts_out_of_order_parts_and_retries(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $uploadId = 'ember-out-of-order-upload-123456';
        $geojson = json_encode([
            'type' => 'FeatureCollection',
            'features' => [],
        ], JSON_THROW_ON_ERROR);
        $splitAt = (int) ceil(strlen($geojson) / 2);
        $chunks = [substr($geojson, 0, $splitAt), substr($geojson, $splitAt)];

        foreach ([1, 0, 1] as $index) {
            $this->actingAs($admin)
                ->postJson(route('cms.geojson.upload-chunk'), [
                    'upload_id' => $uploadId,
                    'chunk_index' => $index,
                    'total_chunks' => count($chunks),
                    'original_name' => 'kecamatan.geojson',
                    'chunk' => UploadedFile::fake()->createWithContent('chunk.part', $chunks[$index]),
                ])
                ->assertOk();
        }

        $this->actingAs($admin)
            ->post(route('cms.geojson.store'), [
                'name' => 'Kecamatan Upload Acak',
                'upload_token' => $uploadId,
                'administrative_level' => 'district',
                'min_zoom' => 6,
                'max_zoom' => 13,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('geojson_layers', [
            'name' => 'Kecamatan Upload Acak',
            'geojson_type' => 'FeatureCollection',
        ]);
    }

    public function test_admin_can_manage_bilingual_faq_and_public_can_switch_language(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('cms.faq.store'), [
                'question_id' => 'Apa itu EMBER?',
                'question_en' => 'What is EMBER?',
                'answer_id' => 'Platform pemantauan lingkungan.',
                'answer_en' => 'An environmental monitoring platform.',
            ])
            ->assertSessionHas('success');

        $faqId = DB::table('faqs')->value('id');

        $this->get(route('user.faq', ['lang' => 'id']))
            ->assertOk()
            ->assertSee('Apa itu EMBER?')
            ->assertSee('Platform pemantauan lingkungan.');

        $this->get(route('user.faq', ['lang' => 'en']))
            ->assertOk()
            ->assertSee('What is EMBER?')
            ->assertSee('An environmental monitoring platform.');

        $this->actingAs($admin)
            ->put(route('cms.faq.update', $faqId), [
                'question_id' => 'Bagaimana data diproses?',
                'question_en' => 'How is the data processed?',
                'answer_id' => 'Data diverifikasi sebelum ditampilkan.',
                'answer_en' => 'Data is verified before publication.',
            ])
            ->assertRedirect(route('cms.faq.index'));

        $this->assertDatabaseHas('faqs', ['id' => $faqId, 'question_id' => 'Bagaimana data diproses?']);
    }

    public function test_admin_can_manage_activity_and_only_published_activity_is_public(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('cms.activities.store'), [
                'image_id' => UploadedFile::fake()->image('aktivitas-id.jpg', 1200, 800),
                'image_en' => UploadedFile::fake()->image('activity-en.jpg', 1200, 800),
                'description_id' => 'Pemantauan lapangan di Sumatera.',
                'description_en' => 'Field monitoring in Sumatra.',
                'content_id' => '<p>Tim melakukan verifikasi data lapangan.</p>',
                'content_en' => '<p>The team verified field data.</p>',
                'date' => '2026-08-12',
                'status' => 'draft',
            ])
            ->assertRedirect(route('cms.activities.index'));

        $activity = DB::table('activities')->first();
        $this->assertNotNull($activity);
        Storage::disk('public')->assertExists($activity->image_id);
        Storage::disk('public')->assertExists($activity->image_en);

        $this->get(route('user.activities', ['lang' => 'id']))
            ->assertOk()
            ->assertDontSee('Pemantauan lapangan di Sumatera.');

        $this->get(route('user.activities.show', ['id' => $activity->id, 'lang' => 'id']))
            ->assertNotFound();

        $this->actingAs($admin)
            ->put(route('cms.activities.update', $activity->id), [
                'description_id' => 'Pemantauan lapangan di Sumatera.',
                'description_en' => 'Field monitoring in Sumatra.',
                'content_id' => '<p>Tim melakukan verifikasi data lapangan.</p>',
                'content_en' => '<p>The team verified field data.</p>',
                'date' => '2026-08-12',
                'status' => 'publish',
            ])
            ->assertRedirect(route('cms.activities.index'));

        $this->get(route('user.activities.show', ['id' => $activity->id, 'lang' => 'id']))
            ->assertOk()
            ->assertSee('Pemantauan lapangan di Sumatera.')
            ->assertSee('Tim melakukan verifikasi data lapangan.', false);

        $this->get(route('user.activities.show', ['id' => $activity->id, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('Field monitoring in Sumatra.')
            ->assertSee('The team verified field data.', false);
    }
}
