<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_pps_profiles', function (Blueprint $table) {
            $table->text('anti_corruption_survey_results')->nullable()->after('educational_publication_metrics');
            $table->text('disciplinary_actions_info')->nullable()->after('anti_corruption_survey_results');
        });
    }

    public function down(): void
    {
        Schema::table('application_pps_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'anti_corruption_survey_results',
                'disciplinary_actions_info',
            ]);
        });
    }
};
