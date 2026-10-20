<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('titik_lokasi', function (Blueprint $table) {
            $table->string('provinsi')->nullable()->change();
            $table->string('kabupaten_kota')->nullable()->change();
            $table->string('kecamatan')->nullable()->change();
            $table->string('desa')->nullable()->change();
            $table->date('date')->nullable()->change();
            $table->decimal('confidence', 5, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('titik_lokasi', function (Blueprint $table) {
            $table->string('provinsi')->nullable(false)->change();
            $table->string('kabupaten_kota')->nullable(false)->change();
            $table->string('kecamatan')->nullable(false)->change();
            $table->string('desa')->nullable(false)->change();
            $table->date('date')->nullable(false)->change();
            $table->decimal('confidence', 5, 2)->nullable(false)->change();
        });
    }
};
