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
            $table->string('title_id')->nullable();
            $table->string('title_en')->nullable();
            $table->text('description_id')->nullable();
            $table->text('description_en')->nullable();
            $table->text('vision_id')->nullable();
            $table->text('vision_en')->nullable();
            $table->text('mission_id')->nullable();
            $table->text('mission_en')->nullable();
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->string('name_id')->nullable();
            $table->string('name_en')->nullable();
            $table->string('position_id')->nullable();
            $table->string('position_en')->nullable();
            $table->text('bio_id')->nullable();
            $table->text('bio_en')->nullable();
        });

        Schema::create('methodology_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title_id');
            $table->string('title_en');
            $table->text('introduction_id')->nullable();
            $table->text('introduction_en')->nullable();
            $table->text('data_source_id')->nullable();
            $table->text('data_source_en')->nullable();
            $table->text('process_id')->nullable();
            $table->text('process_en')->nullable();
            $table->text('classification_id')->nullable();
            $table->text('classification_en')->nullable();
            $table->timestamps();
        });

        DB::table('about_pages')->orderBy('id')->each(function ($about) {
            DB::table('about_pages')->where('id', $about->id)->update([
                'title_id' => $about->title,
                'description_id' => $about->description,
                'vision_id' => $about->vision,
                'mission_id' => $about->mission,
            ]);
        });

        DB::table('team_members')->orderBy('id')->each(function ($member) {
            DB::table('team_members')->where('id', $member->id)->update([
                'name_id' => $member->name,
                'position_id' => $member->position,
                'bio_id' => $member->bio,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('methodology_pages');

        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn(['name_id', 'name_en', 'position_id', 'position_en', 'bio_id', 'bio_en']);
        });

        Schema::table('about_pages', function (Blueprint $table) {
            $table->dropColumn([
                'title_id', 'title_en', 'description_id', 'description_en',
                'vision_id', 'vision_en', 'mission_id', 'mission_en',
            ]);
        });
    }
};
