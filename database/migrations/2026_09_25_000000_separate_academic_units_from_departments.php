<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $units = DB::table('departments')->orderBy('id')->get()->filter(function ($unit) {
            return in_array(trim((string) $unit->description), ['Факультет', 'Кафедра'], true)
                || str_starts_with(trim($unit->name), 'Факультет')
                || str_starts_with(trim($unit->name), 'Кафедра')
                || in_array(trim($unit->name), ['Другое', 'КШИТ'], true);
        });
        $ids = $units->pluck('id')->all();

        if ($units->pluck('name')->duplicates()->isNotEmpty()) {
            throw new RuntimeException('Duplicate academic unit names found. Resolve duplicates before migrating.');
        }
        $facultyIds = $units->filter(fn ($unit) => trim((string) $unit->description) === 'Факультет'
            || str_starts_with(trim($unit->name), 'Факультет') || trim($unit->name) === 'Другое')->pluck('id')->all();
        foreach ($units as $unit) {
            if (in_array($unit->id, $facultyIds) ? $unit->parent_id !== null : ! in_array($unit->parent_id, $facultyIds)) {
                throw new RuntimeException("Academic unit {$unit->id} has an invalid faculty/chair hierarchy. Resolve it before migrating.");
            }
        }

        // Check before DDL: MySQL cannot roll back schema changes.
        foreach (['personnel_requests' => ['department_id', 'target_department_id'], 'personnel_approval_routes' => ['department_id']] as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasTable($table) && DB::table($table)->whereIn($column, $ids)->exists()) {
                    throw new RuntimeException("Academic units are referenced by {$table}.{$column}. Resolve these personnel references before migrating; no data has been moved.");
                }
            }
        }
        $administrativeChildren = DB::table('departments')->whereIn('parent_id', $ids)->whereNotIn('id', $ids)->get(['id', 'parent_id']);
        foreach ($units as $unit) {
            if ($unit->parent_id && ! in_array($unit->parent_id, $ids)) {
                throw new RuntimeException("Academic unit {$unit->id} has a non-academic parent. Resolve its parent before migrating.");
            }
        }

        if (! Schema::hasTable('academic_units')) {
            Schema::create('academic_units', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('kind', 20);
                $table->text('description')->nullable();
                $table->foreignId('parent_id')->nullable()->constrained('academic_units')->restrictOnDelete();
                $table->timestamps();
            });
        }
        if (! Schema::hasColumn('positions', 'academic_unit_id')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->foreignId('academic_unit_id')->nullable()->constrained('academic_units')->restrictOnDelete();
            });
        }
        if (! collect(Schema::getForeignKeys('departments'))->contains(fn ($key) => in_array('academic_unit_id', $key['columns'], true))) {
            Schema::table('departments', function (Blueprint $table) {
                $table->foreign('academic_unit_id')->references('id')->on('academic_units')->restrictOnDelete();
            });
        }
        Schema::table('positions', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->change();
        });

        DB::transaction(function () use ($units, $ids, $administrativeChildren) {
            foreach ($units as $unit) {
                DB::table('academic_units')->insert([
                    'id' => $unit->id, 'name' => $unit->name,
                    'kind' => $unit->parent_id ? 'chair' : 'faculty',
                    'description' => $unit->description, 'parent_id' => null,
                    'created_at' => $unit->created_at, 'updated_at' => $unit->updated_at,
                ]);
            }
            foreach ($units as $unit) {
                DB::table('academic_units')->where('id', $unit->id)->update(['parent_id' => $unit->parent_id]);
                DB::table('positions')->where('department_id', $unit->id)->update([
                    'academic_unit_id' => $unit->id, 'department_id' => null,
                ]);
            }
            foreach ($administrativeChildren as $child) {
                DB::table('departments')->where('id', $child->id)->update([
                    'parent_id' => null, 'academic_unit_id' => $child->parent_id,
                ]);
            }
            DB::table('departments')->whereIn('id', $ids)->update(['parent_id' => null]);
            DB::table('departments')->whereIn('id', $ids)->delete();
        });
    }

    public function down(): void
    {
        $units = DB::table('academic_units')->get();
        if (DB::table('departments')->whereIn('name', $units->pluck('name'))->exists()) {
            throw new RuntimeException('Department names conflict with academic units. Resolve duplicates before rollback.');
        }
        DB::transaction(function () use ($units) {
            $ids = [];
            foreach ($units as $unit) {
                $ids[$unit->id] = DB::table('departments')->insertGetId([
                    'name' => $unit->name, 'description' => $unit->description,
                    'parent_id' => null, 'created_at' => $unit->created_at, 'updated_at' => $unit->updated_at,
                ]);
            }
            foreach ($units as $unit) {
                DB::table('departments')->where('id', $ids[$unit->id])->update([
                    'parent_id' => $unit->parent_id ? $ids[$unit->parent_id] : null,
                ]);
                DB::table('positions')->where('academic_unit_id', $unit->id)->update([
                    'department_id' => $ids[$unit->id], 'academic_unit_id' => null,
                ]);
                DB::table('departments')->where('academic_unit_id', $unit->id)->update([
                    'parent_id' => $ids[$unit->id], 'academic_unit_id' => null,
                ]);
            }
        });
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['academic_unit_id']);
        });
        Schema::table('positions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_unit_id');
            $table->unsignedBigInteger('department_id')->nullable(false)->change();
        });
        DB::table('academic_units')->update(['parent_id' => null]);
        Schema::dropIfExists('academic_units');
    }
};
