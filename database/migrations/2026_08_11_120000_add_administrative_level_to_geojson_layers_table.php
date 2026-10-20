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
            $table->string('administrative_level', 30)->default('other')->after('file_format');
        });

        foreach ([
            'province' => '%provinsi%',
            'regency' => '%kota%',
            'district' => '%kecamatan%',
            'village' => '%desa%',
        ] as $level => $namePattern) {
            DB::table('geojson_layers')
                ->whereRaw('LOWER(name) LIKE ?', [$namePattern])
                ->update(['administrative_level' => $level]);
        }
    }

    public function down(): void
    {
        Schema::table('geojson_layers', function (Blueprint $table) {
            $table->dropColumn('administrative_level');
        });
    }
};
