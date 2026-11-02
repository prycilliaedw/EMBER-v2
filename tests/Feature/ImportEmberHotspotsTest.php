<?php

namespace Tests\Feature;

use App\Services\FireSusceptibilityService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportEmberHotspotsTest extends TestCase
{
    private string $csvPath;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::connection('sqlite')->create('titik_lokasi', function (Blueprint $table): void {
            $table->id();
            $table->string('external_id', 191)->nullable()->unique();
            $table->string('data_source', 32)->nullable();
            $table->string('source_product', 64)->nullable();
            $table->boolean('is_nrt')->default(false);
            $table->string('provinsi')->nullable();
            $table->string('kabupaten_kota')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('desa')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->date('date')->nullable();
            $table->string('confidence')->nullable();
            $table->string('satellite', 50)->nullable();
            $table->string('instrument', 50)->nullable();
            $table->string('daynight', 1)->nullable();
            $table->unsignedSmallInteger('acq_time')->nullable();
            $table->unsignedSmallInteger('land_cover_id')->nullable();
            $table->string('land_cover', 120)->nullable();
            $table->unsignedSmallInteger('raw_mapbiomas_class_id')->nullable();
            $table->string('classification_status', 20)->default('pending');
            $table->text('classification_error')->nullable();
            $table->unsignedSmallInteger('classification_attempts')->default(0);
            $table->timestamp('classified_at')->nullable();
            $table->decimal('prior_lcs', 6, 2)->nullable();
            $table->decimal('empirical_evidence', 6, 2)->nullable();
            $table->decimal('hotspot_evidence', 6, 2)->nullable();
            $table->decimal('historical_burn_evidence', 6, 2)->nullable();
            $table->string('model_version')->nullable();
            $table->decimal('fsi_score', 6, 2)->nullable();
            $table->string('fsi_class')->nullable();
            $table->string('context_flag')->nullable();
            $table->longText('source_payload')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        if (isset($this->csvPath) && is_file($this->csvPath)) {
            unlink($this->csvPath);
        }

        parent::tearDown();
    }

    public function test_dry_run_reports_counts_distribution_and_writes_nothing(): void
    {
        $rows = $this->allClassRows();
        $rows[] = $rows[0]; // repeated source identity
        $invalid = $this->row(3, 99);
        $invalid['CONFIDENCE'] = 'not-a-number';
        $rows[] = $invalid;
        $path = $this->writeCsv($rows);

        $exit = Artisan::call('ember:import-hotspots', ['path' => $path, '--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(ImportEmberHotspotsExit::FAILURE, $exit);
        $this->assertStringContainsString('Source rows: 15', $output);
        $this->assertStringContainsString('Valid rows: 14', $output);
        $this->assertStringContainsString('Invalid rows: 1', $output);
        $this->assertStringContainsString('Duplicate source records: 1', $output);
        $this->assertStringContainsString('New records to insert: 13', $output);
        $this->assertStringContainsString('  76 Hutan Rawa Gambut: 1', $output);
        $this->assertSame(0, DB::table('titik_lokasi')->count());
    }

    public function test_existing_nrt_row_is_found_by_attributes_even_with_null_external_id(): void
    {
        $source = $this->row(3, 0);
        DB::table('titik_lokasi')->insert([
            'external_id' => null,
            'source_product' => 'MODIS_NRT',
            'is_nrt' => true,
            'date' => '2021-01-01',
            'acq_time' => 650,
            'latitude' => $source['LATITUDE'],
            'longitude' => $source['LONGITUDE'],
            'satellite' => 'Aqua',
            'instrument' => 'MODIS',
        ]);
        $path = $this->writeCsv([$source]);

        $exit = Artisan::call('ember:import-hotspots', ['path' => $path, '--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(ImportEmberHotspotsExit::SUCCESS, $exit);
        $this->assertStringContainsString('Existing matching records: 1', $output);
        $this->assertStringContainsString('New records to insert: 0', $output);
        $this->assertSame(1, DB::table('titik_lokasi')->count());
    }

    public function test_import_sets_classification_and_approved_evidence_for_all_thirteen_classes(): void
    {
        $path = $this->writeCsv($this->allClassRows());

        $exit = Artisan::call('ember:import-hotspots', ['path' => $path]);

        $this->assertSame(ImportEmberHotspotsExit::SUCCESS, $exit, Artisan::output());
        $locations = DB::table('titik_lokasi')->orderBy('land_cover_id')->get();
        $this->assertCount(13, $locations);
        $expectedIds = array_keys(FireSusceptibilityService::LAND_COVER_NAMES);
        sort($expectedIds);
        $this->assertSame($expectedIds, $locations->pluck('land_cover_id')->map(fn ($id) => (int) $id)->all());

        foreach ($locations as $location) {
            $classId = (int) $location->land_cover_id;
            $evidence = app(FireSusceptibilityService::class)->classEvidence($classId);
            $this->assertSame($classId, (int) $location->raw_mapbiomas_class_id);
            $this->assertSame(FireSusceptibilityService::LAND_COVER_NAMES[$classId], $location->land_cover);
            $this->assertSame('classified', $location->classification_status);
            $this->assertNull($location->classification_error);
            $this->assertSame(80.0, (float) $location->confidence);
            $this->assertSame((float) $evidence['hotspot_evidence'], (float) $location->hotspot_evidence);
            $this->assertSame((float) $evidence['historical_burn_evidence'], (float) $location->historical_burn_evidence);
            $this->assertNotNull($location->fsi_score);
            $this->assertNotNull($location->fsi_class);
            $this->assertSame(FireSusceptibilityService::MODEL_VERSION, $location->model_version);
        }

        $first = DB::table('titik_lokasi')->orderBy('id')->first();
        $this->assertSame(
            hash('sha256', 'MODIS_NRT|2021-01-01|650|-3.000000|100.000000'),
            $first->external_id,
            'Imported identity must match the NRT upsert identity recipe.',
        );
    }

    public function test_repeated_import_is_idempotent(): void
    {
        $path = $this->writeCsv($this->allClassRows());

        $firstExit = Artisan::call('ember:import-hotspots', ['path' => $path]);
        $firstOutput = Artisan::output();
        $secondExit = Artisan::call('ember:import-hotspots', ['path' => $path]);
        $secondOutput = Artisan::output();

        $this->assertSame(ImportEmberHotspotsExit::SUCCESS, $firstExit, $firstOutput);
        $this->assertStringContainsString('Records inserted: 13', $firstOutput);
        $this->assertSame(ImportEmberHotspotsExit::SUCCESS, $secondExit, $secondOutput);
        $this->assertStringContainsString('New records to insert: 0', $secondOutput);
        $this->assertStringContainsString('Records inserted: 0', $secondOutput);
        $this->assertSame(13, DB::table('titik_lokasi')->count());
    }

    public function test_invalid_non_dry_run_aborts_without_partial_inserts(): void
    {
        $rows = $this->allClassRows();
        $invalid = $this->row(76, 20);
        $invalid['LAND_COVER_ID'] = '999';
        $invalid['MAPBIOMAS_ID'] = '999';
        $rows[] = $invalid;
        $path = $this->writeCsv($rows);

        $exit = Artisan::call('ember:import-hotspots', ['path' => $path]);

        $this->assertSame(ImportEmberHotspotsExit::FAILURE, $exit);
        $this->assertStringContainsString('No rows were written', Artisan::output());
        $this->assertSame(0, DB::table('titik_lokasi')->count());
    }

    private function allClassRows(): array
    {
        $rows = [];
        foreach (array_keys(FireSusceptibilityService::LAND_COVER_NAMES) as $index => $classId) {
            $rows[] = $this->row((int) $classId, $index);
        }

        return $rows;
    }

    private function row(int $classId, int $index): array
    {
        return [
            'LATITUDE' => number_format(-3.0 - ($index / 10000), 6, '.', ''),
            'LONGITUDE' => number_format(100.0 + ($index / 10000), 6, '.', ''),
            'ACQ_DATE' => '2021/01/01',
            'ACQ_TIME' => '0650',
            'SATELLITE' => 'Aqua',
            'INSTRUMENT' => 'MODIS',
            'CONFIDENCE' => '80',
            'VERSION' => '6.03',
            'DAYNIGHT' => 'D',
            'LEVEL_1' => 'Indonesia',
            'LEVEL_2' => 'Sumatera',
            'LEVEL_3' => 'Aceh',
            'LEVEL_4' => 'Aceh Barat',
            'LEVEL_5' => 'Kecamatan',
            'LEVEL_6' => 'Desa',
            'MAPBIOMAS_ID' => (string) $classId,
            'MAPBIOMAS_CLASS' => 'Source class '.$classId,
            'LAND_COVER_ID' => (string) $classId,
            'LAND_COVER' => FireSusceptibilityService::LAND_COVER_NAMES[$classId],
            'emp_conf_ev' => '50.0',
        ];
    }

    private function writeCsv(array $rows): string
    {
        $this->csvPath = tempnam(sys_get_temp_dir(), 'ember-import-');
        $handle = fopen($this->csvPath, 'wb');
        $header = array_keys($rows[0]);
        fputcsv($handle, $header, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row), ',', '"', '');
        }
        fclose($handle);

        return $this->csvPath;
    }
}

/** Exit code constants avoid coupling assertions to console implementation values. */
final class ImportEmberHotspotsExit
{
    public const SUCCESS = 0;
    public const FAILURE = 1;
}
