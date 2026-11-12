<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->enum('type', ['staff', 'pps', 'uvp'])->change();
            $table->foreignId('uvp_position_id')->nullable()->constrained('uvp_positions')->restrictOnDelete();
        });
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('uvp_faculty_id')->nullable()->constrained('academic_units')->restrictOnDelete();
            $table->foreignId('uvp_academic_unit_id')->nullable()->constrained('academic_units')->restrictOnDelete();
        });
        Schema::table('commission_members', fn (Blueprint $table) => $table->boolean('is_uvp')->default(false));
    }

    public function down(): void
    {
        if (DB::table('vacancies')->where('type', 'uvp')->orWhereNotNull('uvp_position_id')->exists() ||
            DB::table('competitions')->where('type', 'uvp')->exists() ||
            DB::table('applications')->whereNotNull('uvp_faculty_id')->orWhereNotNull('uvp_academic_unit_id')->exists() ||
            DB::table('commission_members')->where('is_uvp', true)->exists()) {
            throw new RuntimeException('Cannot roll back while UVP vacancies, competitions or assignments exist.');
        }
        Schema::table('commission_members', fn (Blueprint $table) => $table->dropColumn('is_uvp'));
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uvp_faculty_id');
            $table->dropConstrainedForeignId('uvp_academic_unit_id');
        });
        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uvp_position_id');
            $table->enum('type', ['staff', 'pps'])->change();
        });
    }
};
