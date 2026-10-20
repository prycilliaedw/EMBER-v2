<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->text('question_id');
            $table->text('question_en');
            $table->longText('answer_id');
            $table->longText('answer_en');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('image_id')->nullable();
            $table->string('image_en')->nullable();
            $table->text('description_id');
            $table->text('description_en');
            $table->longText('content_id');
            $table->longText('content_en');
            $table->date('date');
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['status', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('faqs');
    }
};
