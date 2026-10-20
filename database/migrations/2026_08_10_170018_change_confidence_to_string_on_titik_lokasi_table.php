<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('titik_lokasi', function (Blueprint $table) {
            $table->string('confidence', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('titik_lokasi', function (Blueprint $table) {
            $table->decimal('confidence', 5, 2)->nullable()->change();
        });
    }
};
