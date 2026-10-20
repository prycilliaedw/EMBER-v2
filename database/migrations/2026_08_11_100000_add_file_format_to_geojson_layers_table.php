<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geojson_layers', function (Blueprint $table) {
            $table->string('file_format', 20)->default('geojson')->after('file_size');
        });
    }

    public function down(): void
    {
        Schema::table('geojson_layers', function (Blueprint $table) {
            $table->dropColumn('file_format');
        });
    }
};
