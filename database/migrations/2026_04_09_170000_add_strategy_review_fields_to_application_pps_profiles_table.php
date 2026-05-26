<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_pps_profiles', function (Blueprint $table) {
            $table->string('final_rating_score')->nullable()->after('digital_mooc');
            $table->text('individual_plan_nonfulfillment')->nullable()->after('final_rating_score');
            $table->text('krk')->nullable()->after('individual_plan_nonfulfillment');
        });
    }

    public function down(): void
    {
        Schema::table('application_pps_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'final_rating_score',
                'individual_plan_nonfulfillment',
                'krk',
            ]);
        });
    }
};
