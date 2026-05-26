<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_pps_profiles', function (Blueprint $table) {
            $table->text('scientific_works')->nullable()->after('academic_title_document_path');
            $table->string('scientific_works_document_path')->nullable()->after('scientific_works');
        });
    }

    public function down(): void
    {
        Schema::table('application_pps_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'scientific_works',
                'scientific_works_document_path',
            ]);
        });
    }
};
