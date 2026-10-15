<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('departments', 'academic_unit_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $column = $table->foreignId('academic_unit_id')->nullable();
                if (Schema::hasTable('academic_units')) {
                    $column->constrained('academic_units')->restrictOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        $hasForeignKey = collect(Schema::getForeignKeys('departments'))->contains(
            fn ($key) => in_array('academic_unit_id', $key['columns'], true)
        );
        if ($hasForeignKey) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropForeign(['academic_unit_id']);
            });
        }
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('academic_unit_id');
        });
    }
};
