<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('titik_lokasi', function (Blueprint $table) {
            $table->string('external_id', 191)->nullable()->after('id');
            $table->string('data_source', 32)->nullable()->after('external_id');
            $table->string('source_product', 64)->nullable()->after('data_source');
            $table->boolean('is_nrt')->default(false)->after('source_product');
            $table->decimal('frp', 10, 3)->nullable()->after('confidence');
            $table->decimal('brightness', 8, 3)->nullable()->after('frp');
            $table->decimal('scan', 8, 3)->nullable()->after('brightness');
            $table->decimal('track', 8, 3)->nullable()->after('scan');
            $table->timestamp('fetched_at')->nullable()->after('track');
            $table->timestamp('synced_at')->nullable()->after('fetched_at');
            $table->longText('source_payload')->nullable()->after('synced_at');

            $table->unique('external_id', 'titik_lokasi_external_id_unique');
            $table->index(['is_nrt', 'date']);
            $table->index(['data_source', 'source_product']);
        });

        Schema::create('firms_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 64);
            $table->string('status', 16);
            $table->integer('requested_days')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->integer('received')->default(0);
            $table->integer('inside_aoi')->default(0);
            $table->integer('inserted')->default(0);
            $table->integer('updated')->default(0);
            $table->integer('failed')->default(0);
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firms_sync_runs');

        Schema::table('titik_lokasi', function (Blueprint $table) {
            $table->dropUnique('titik_lokasi_external_id_unique');
            $table->dropIndex(['is_nrt', 'date']);
            $table->dropIndex(['data_source', 'source_product']);
            $table->dropColumn([
                'external_id', 'data_source', 'source_product', 'is_nrt',
                'frp', 'brightness', 'scan', 'track', 'fetched_at', 'synced_at', 'source_payload',
            ]);
        });
    }
};
