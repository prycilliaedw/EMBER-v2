<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StatsNullCoordsTempTest extends TestCase
{
    use RefreshDatabase;

    public function test_statistics_totals_and_dashboard_use_only_georeferenced_rows(): void
    {
        Schema::table('titik_lokasi', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->change();
            $table->decimal('longitude', 11, 8)->nullable()->change();
        });

        DB::table('titik_lokasi')->insert([
            ['latitude' => null, 'longitude' => null, 'date' => '2024-01-02', 'confidence' => 55, 'land_cover_id' => 3],
        ]);

        $response = $this->get('/statistics?lang=en')->assertOk();

        $spatial = $response->viewData('spatialDashboard');
        $this->assertSame(33830, $response->viewData('summary')['total']);
        $this->assertSame(33830, $spatial['total']);
        $this->assertSame(33830, $spatial['mapped_total']);
        $this->assertSame(0, $spatial['unclassified_total']);
        $this->assertSame(33830, $spatial['classes']->sum('count'));
        $this->assertSame(0, $response->viewData('dashboard')['total']);

        $this->get('/map?lang=en')
            ->assertOk()
            ->assertSee('data-spatial-total="33830"', false);

        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('33,830');
    }
}
