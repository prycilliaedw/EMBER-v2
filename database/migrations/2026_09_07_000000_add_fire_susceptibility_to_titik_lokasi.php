<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('titik_lokasi', function (Blueprint $table) {
            $table->string('satellite', 50)->nullable()->after('confidence');
            $table->string('instrument', 50)->nullable()->after('satellite');
            $table->string('daynight', 1)->nullable()->after('instrument');
            $table->unsignedSmallInteger('acq_time')->nullable()->after('daynight');

            $table->unsignedSmallInteger('land_cover_id')->nullable()->after('acq_time');
            $table->string('land_cover', 120)->nullable()->after('land_cover_id');
            $table->decimal('prior_lcs', 6, 2)->nullable()->after('land_cover');
            $table->decimal('empirical_evidence', 6, 2)->nullable()->after('prior_lcs');
            $table->decimal('hybrid_lcs', 6, 2)->nullable()->after('empirical_evidence');
            $table->decimal('fsi_score', 6, 2)->nullable()->after('hybrid_lcs');
            $table->string('fsi_class', 30)->nullable()->after('fsi_score');
            $table->string('context_flag', 30)->nullable()->after('fsi_class');

            $table->index('fsi_class');
            $table->index('land_cover_id');
            $table->index(['date', 'fsi_class']);
        });
    }

    public function down(): void
    {
        Schema::table('titik_lokasi', function (Blueprint $table) {
            $table->dropIndex(['date', 'fsi_class']);
            $table->dropIndex(['land_cover_id']);
            $table->dropIndex(['fsi_class']);
            $table->dropColumn([
                'satellite',
                'instrument',
                'daynight',
                'acq_time',
                'land_cover_id',
                'land_cover',
                'prior_lcs',
                'empirical_evidence',
                'hybrid_lcs',
                'fsi_score',
                'fsi_class',
                'context_flag',
            ]);
        });
    }
};
