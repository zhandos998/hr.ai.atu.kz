<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_pps_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('full_name')->nullable();
            $table->string('desired_position')->nullable();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->text('basic_education')->nullable();
            $table->string('basic_education_document_path')->nullable();
            $table->text('magistracy')->nullable();
            $table->string('magistracy_document_path')->nullable();
            $table->text('scientific_degree')->nullable();
            $table->string('scientific_degree_document_path')->nullable();
            $table->text('academic_title')->nullable();
            $table->string('academic_title_document_path')->nullable();
            $table->text('work_experience')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_pps_profiles');
    }
};
