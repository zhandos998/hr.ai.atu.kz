<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_pps_profiles', function (Blueprint $table) {
            $table->text('digital_mooc')->nullable()->after('scientific_works_document_path');
        });
    }

    public function down(): void
    {
        Schema::table('application_pps_profiles', function (Blueprint $table) {
            $table->dropColumn('digital_mooc');
        });
    }
};
