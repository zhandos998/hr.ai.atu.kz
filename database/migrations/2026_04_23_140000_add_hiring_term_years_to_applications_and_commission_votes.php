<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_commission_votes', function (Blueprint $table) {
            $table->unsignedTinyInteger('hire_term_years')
                ->nullable()
                ->after('decision');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedTinyInteger('hiring_term_years')
                ->nullable()
                ->after('hiring_status');
        });
    }

    public function down(): void
    {
        Schema::table('application_commission_votes', function (Blueprint $table) {
            $table->dropColumn('hire_term_years');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('hiring_term_years');
        });
    }
};
