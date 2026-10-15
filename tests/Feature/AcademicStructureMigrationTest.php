<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AcademicStructureMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private function migration()
    {
        (require database_path('migrations/2026_10_15_120000_merge_duplicate_positions.php'))->down();

        return require database_path('migrations/2026_09_25_000000_separate_academic_units_from_departments.php');
    }

    public function test_administrative_child_of_chair_keeps_its_academic_link_and_rolls_back(): void
    {
        $migration = $this->migration();
        $migration->down();
        $faculty = Department::create(['name' => 'Факультет тестовый', 'description' => 'Факультет']);
        $chair = Department::create(['name' => 'Кафедра тестовая', 'description' => 'Кафедра', 'parent_id' => $faculty->id]);
        $staff = Department::create(['name' => 'мастер производственного обучения', 'parent_id' => $chair->id]);
        $position = Position::create(['name' => 'Мастер', 'department_id' => $staff->id]);
        $migration->up();
        $this->assertDatabaseHas('departments', ['id' => $staff->id, 'parent_id' => null, 'academic_unit_id' => $chair->id]);
        $this->assertSame($staff->id, $position->fresh()->department_id);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $migration->down();
        $restoredChair = Department::where('name', 'Кафедра тестовая')->firstOrFail();
        $this->assertDatabaseHas('departments', ['id' => $staff->id, 'parent_id' => $restoredChair->id, 'academic_unit_id' => null]);
        $migration->up();
    }

    public function test_move_and_rollback_preserve_positions_applications_and_commissions(): void
    {
        $migration = $this->migration();
        $migration->down();
        $staff = Department::create(['name' => 'HR', 'description' => 'Подразделение']);
        $faculty = Department::create(['name' => 'Другое', 'description' => 'Факультет']);
        $chair = Department::create(['name' => 'КШИТ', 'description' => 'Кафедра', 'parent_id' => $faculty->id]);
        $position = Position::create(['department_id' => $chair->id, 'name' => 'Ассистент']);
        $staffPosition = Position::create(['department_id' => $staff->id, 'name' => 'Специалист']);
        $candidate = User::factory()->create();
        $commission = User::factory()->create();
        $position->users()->attach($commission);
        $vacancy = Vacancy::create(['title' => 'Ассистент', 'type' => 'pps', 'description' => 'Test', 'position_id' => $position->id]);
        $vacancy->commissionMembers()->attach($commission);
        $application = Application::create(['user_id' => $candidate->id, 'vacancy_id' => $vacancy->id]);
        $application->ppsProfile()->create(['faculty_name' => 'Другое', 'department_name' => 'КШИТ']);

        $migration->up();

        $this->assertDatabaseCount('departments', 1);
        $this->assertDatabaseHas('academic_units', ['id' => $chair->id, 'name' => 'КШИТ', 'kind' => 'chair', 'parent_id' => $faculty->id]);
        $this->assertDatabaseHas('positions', ['id' => $position->id, 'department_id' => null, 'academic_unit_id' => $chair->id]);
        $this->assertSame($staff->id, $staffPosition->fresh()->department_id);
        $this->assertSame($position->id, $vacancy->fresh()->position_id);
        $this->assertSame($vacancy->id, $application->fresh()->vacancy_id);
        $this->assertSame('КШИТ', $application->fresh()->ppsProfile->department_name);
        $this->assertSame(1, $position->users()->count());
        $this->assertSame(1, $vacancy->commissionMembers()->count());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));

        // A new staff unit may reuse an ID; rollback must not overwrite it.
        $newStaff = Department::create(['name' => 'New HR']);
        $migration->down();
        $this->assertDatabaseHas('departments', ['id' => $newStaff->id, 'name' => 'New HR']);
        $this->assertSame('КШИТ', $position->fresh()->department->name);
        $this->assertSame($position->id, $vacancy->fresh()->position_id);
        $this->assertSame(1, $position->users()->count());
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $migration->up();
    }

    public function test_personnel_routes_block_migration_before_schema_or_data_changes(): void
    {
        $migration = $this->migration();
        $migration->down();
        $faculty = Department::create(['name' => 'Факультет', 'description' => 'Факультет']);
        $user = User::factory()->create();
        DB::table('personnel_approval_routes')->insert(['department_id' => $faculty->id, 'approver_user_id' => $user->id]);
        try {
            $migration->up();
            $this->fail('Migration must not delete personnel routes.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('personnel_approval_routes', $exception->getMessage());
        }
        $this->assertFalse(Schema::hasTable('academic_units'));
        $this->assertDatabaseHas('departments', ['id' => $faculty->id]);
        $this->assertDatabaseCount('personnel_approval_routes', 1);
        DB::table('personnel_approval_routes')->delete();
        $migration->up();
    }
}
