<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('titik_lokasi', function (Blueprint $table) {
            $table->unsignedSmallInteger('raw_mapbiomas_class_id')->nullable()->after('land_cover');
            $table->string('classification_status', 20)->default('pending')->after('raw_mapbiomas_class_id');
            $table->text('classification_error')->nullable()->after('classification_status');
            $table->unsignedSmallInteger('classification_attempts')->default(0)->after('classification_error');
            $table->timestamp('classified_at')->nullable()->after('classification_attempts');

            $table->index(['is_nrt', 'classification_status'], 'titik_lokasi_nrt_class_status_idx');
            $table->index('raw_mapbiomas_class_id');
        });
    }

    public function down(): void
    {
        Schema::table('titik_lokasi', function (Blueprint $table) {
            $table->dropIndex('titik_lokasi_nrt_class_status_idx');
            $table->dropIndex(['raw_mapbiomas_class_id']);
            $table->dropColumn([
                'raw_mapbiomas_class_id',
                'classification_status',
                'classification_error',
                'classification_attempts',
                'classified_at',
            ]);
        });
    }
};
