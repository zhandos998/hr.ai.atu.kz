<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_pps_profile_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_pps_profile_id');
            $table->string('category', 64);
            $table->string('original_name')->nullable();
            $table->string('file_path');
            $table->timestamps();

            $table->index(['application_pps_profile_id', 'category'], 'pps_profile_docs_category_idx');
            $table->foreign('application_pps_profile_id', 'pps_profile_docs_profile_fk')
                ->references('id')
                ->on('application_pps_profiles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_pps_profile_documents');
    }
};
