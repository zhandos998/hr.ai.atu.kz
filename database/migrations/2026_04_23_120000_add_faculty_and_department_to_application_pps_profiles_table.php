<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_pps_profiles', function (Blueprint $table) {
            $table->string('faculty_name')->nullable()->after('desired_position');
            $table->string('department_name')->nullable()->after('faculty_name');
        });
    }

    public function down(): void
    {
        Schema::table('application_pps_profiles', function (Blueprint $table) {
            $table->dropColumn(['faculty_name', 'department_name']);
        });
    }
};
