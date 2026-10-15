<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $groups = DB::table('positions')
                ->select('department_id', 'academic_unit_id', 'name')
                ->groupBy('department_id', 'academic_unit_id', 'name')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($groups as $group) {
                $positions = DB::table('positions')
                    ->where('department_id', $group->department_id)
                    ->where('academic_unit_id', $group->academic_unit_id)
                    ->where('name', $group->name)
                    ->orderBy('id')->lockForUpdate()->get();
                $primary = $positions->first();
                $duplicates = $positions->slice(1)->pluck('id');
                $updates = [];

                foreach (['duties', 'qualification'] as $field) {
                    $values = $positions->pluck($field)
                        ->filter(fn ($value) => trim((string) $value) !== '')
                        ->unique(fn ($value) => trim($value));
                    if ($values->count() > 1) {
                        throw new RuntimeException("Conflicting {$field} for position IDs ".$positions->pluck('id')->implode(', ').'. No positions merged.');
                    }
                    if (trim((string) $primary->{$field}) === '' && $values->isNotEmpty()) {
                        $updates[$field] = $values->first();
                    }
                }
                if ($updates !== []) {
                    DB::table('positions')->where('id', $primary->id)->update($updates);
                }

                // Keep assignments even when a user was attached to both copies.
                foreach (DB::table('position_user')->whereIn('position_id', $duplicates)->orderBy('id')->get() as $assignment) {
                    if (DB::table('position_user')->where('position_id', $primary->id)->where('user_id', $assignment->user_id)->exists()) {
                        DB::table('position_user')->where('id', $assignment->id)->delete();
                    } else {
                        DB::table('position_user')->where('id', $assignment->id)->update(['position_id' => $primary->id]);
                    }
                }
                foreach ([
                    'vacancies' => ['position_id'],
                    'personnel_requests' => ['position_id', 'target_position_id'],
                    'candidate_ai_results' => ['position_id'],
                ] as $table => $columns) {
                    foreach ($columns as $column) {
                        DB::table($table)->whereIn($column, $duplicates)->update([$column => $primary->id]);
                    }
                }
                DB::table('positions')->whereIn('id', $duplicates)->delete();
            }
        });

        foreach (['department_id', 'academic_unit_id'] as $column) {
            $index = "positions_{$column}_name_unique";
            if (! Schema::hasIndex('positions', $index)) {
                Schema::table('positions', fn (Blueprint $table) => $table->unique([$column, 'name'], $index));
            }
        }
    }

    public function down(): void
    {
        // Restoring duplicate records requires the pre-migration database backup.
        foreach (['department_id', 'academic_unit_id'] as $column) {
            $index = "positions_{$column}_name_unique";
            if (Schema::hasIndex('positions', $index)) {
                // InnoDB may replace its implicit FK index with our unique index.
                if (DB::getDriverName() === 'mysql' && ! collect(Schema::getIndexes('positions'))->contains(
                    fn ($existing) => $existing['columns'] === [$column] && ! $existing['unique']
                )) {
                    Schema::table('positions', fn (Blueprint $table) => $table->index($column, "positions_{$column}_reference_index"));
                }
                Schema::table('positions', fn (Blueprint $table) => $table->dropUnique($index));
            }
        }
    }
};
