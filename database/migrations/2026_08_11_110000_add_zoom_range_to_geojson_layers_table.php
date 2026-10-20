<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geojson_layers', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_zoom')->default(6)->after('feature_count');
            $table->unsignedTinyInteger('max_zoom')->default(13)->after('min_zoom');
        });

        DB::table('geojson_layers')
            ->where('file_format', 'geojson')
            ->update(['is_active' => false]);

        DB::table('geojson_layers')
            ->where('file_format', 'pmtiles')
            ->whereRaw('LOWER(name) LIKE ?', ['%kecamatan%'])
            ->update(['min_zoom' => 6, 'max_zoom' => 10]);

        DB::table('geojson_layers')
            ->where('file_format', 'pmtiles')
            ->whereRaw('LOWER(name) LIKE ?', ['%desa%'])
            ->update(['min_zoom' => 11, 'max_zoom' => 13]);
    }

    public function down(): void
    {
        Schema::table('geojson_layers', function (Blueprint $table) {
            $table->dropColumn(['min_zoom', 'max_zoom']);
        });
    }
};
