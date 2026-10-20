<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('about_pages', function (Blueprint $table) {
            $table->string('image_id')->nullable();
            $table->string('image_en')->nullable();
            $table->longText('content_id')->nullable();
            $table->longText('content_en')->nullable();
        });

        Schema::table('methodology_pages', function (Blueprint $table) {
            $table->string('image_id')->nullable();
            $table->string('image_en')->nullable();
            $table->longText('content_id')->nullable();
            $table->longText('content_en')->nullable();
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->string('photo')->nullable();
            $table->string('nama')->nullable();
            $table->string('npm', 100)->nullable();
        });

        DB::table('about_pages')->orderBy('id')->each(function ($about) {
            DB::table('about_pages')->where('id', $about->id)->update([
                'content_id' => $about->description_id ?: $about->description,
                'content_en' => $about->description_en,
            ]);
        });

        DB::table('methodology_pages')->orderBy('id')->each(function ($methodology) {
            DB::table('methodology_pages')->where('id', $methodology->id)->update([
                'content_id' => $methodology->introduction_id,
                'content_en' => $methodology->introduction_en,
            ]);
        });

        DB::table('team_members')->orderBy('id')->each(function ($member) {
            DB::table('team_members')->where('id', $member->id)->update([
                'nama' => $member->name_id ?: $member->name,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn(['photo', 'nama', 'npm']);
        });

        Schema::table('methodology_pages', function (Blueprint $table) {
            $table->dropColumn(['image_id', 'image_en', 'content_id', 'content_en']);
        });

        Schema::table('about_pages', function (Blueprint $table) {
            $table->dropColumn(['image_id', 'image_en', 'content_id', 'content_en']);
        });
    }
};
