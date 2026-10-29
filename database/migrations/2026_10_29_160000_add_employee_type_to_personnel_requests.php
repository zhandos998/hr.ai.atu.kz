<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personnel_requests', function (Blueprint $table) {
            $table->string('employee_type', 10)->default('staff')->index();
            $table->foreignId('department_id')->nullable()->change();
            $table->foreignId('faculty_id')->nullable()->constrained('academic_units')->restrictOnDelete();
            $table->foreignId('academic_unit_id')->nullable()->constrained('academic_units')->restrictOnDelete();
            $table->foreignId('target_faculty_id')->nullable()->constrained('academic_units')->restrictOnDelete();
            $table->foreignId('target_academic_unit_id')->nullable()->constrained('academic_units')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('personnel_requests')->where('employee_type', 'pps')->orWhereNull('department_id')->exists()) {
            throw new RuntimeException('Cannot roll back while teacher personnel requests exist.');
        }
        Schema::table('personnel_requests', function (Blueprint $table) {
            foreach (['faculty_id', 'academic_unit_id', 'target_faculty_id', 'target_academic_unit_id'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
            $table->dropIndex(['employee_type']);
            $table->dropColumn('employee_type');
            $table->foreignId('department_id')->nullable(false)->change();
        });
    }
};
