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
        Schema::create('candidate_ai_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('worker_id');
            $table->unsignedBigInteger('position_id');
            $table->string('lang', 2)->default('ru');

            // результаты анализа
            $table->float('score')->nullable();
            $table->string('decision')->nullable();
            $table->float('education_match')->nullable();
            $table->float('experience_match')->nullable();
            $table->float('soft_skills_match')->nullable();
            $table->text('summary_kk')->nullable();
            $table->text('summary_ru')->nullable();
            $table->text('summary_en')->nullable();

            $table->timestamps();

            $table->unique(['worker_id', 'position_id', 'lang']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidate_ai_results');
    }
};
