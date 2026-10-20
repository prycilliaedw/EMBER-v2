<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('titik_lokasi', function (Blueprint $table) {
            $table->decimal('hotspot_evidence', 6, 3)->nullable()->after('empirical_evidence');
            $table->decimal('historical_burn_evidence', 6, 3)->nullable()->after('hotspot_evidence');
            $table->string('model_version', 64)->nullable()->after('historical_burn_evidence');
            $table->index(['land_cover_id', 'model_version'], 'titik_lokasi_landcover_model_idx');
        });
    }

    public function down(): void
    {
        Schema::table('titik_lokasi', function (Blueprint $table) {
            $table->dropIndex('titik_lokasi_landcover_model_idx');
            $table->dropColumn([
                'hotspot_evidence',
                'historical_burn_evidence',
                'model_version',
            ]);
        });
    }
};
