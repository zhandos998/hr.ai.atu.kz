<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_pps_profiles', function (Blueprint $table) {
            $table->text('educational_publication_metrics')->nullable()->after('educational_methodical_literature');
        });
    }

    public function down(): void
    {
        Schema::table('application_pps_profiles', function (Blueprint $table) {
            $table->dropColumn('educational_publication_metrics');
        });
    }
};
