<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Application;
use App\Models\CandidateAIResult;
use App\Models\Department;
use App\Models\PersonnelRequest;
use App\Models\Position;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PositionDeduplicationTest extends TestCase
{
    use DatabaseMigrations;

    private function migration()
    {
        return require database_path('migrations/2026_10_15_120000_merge_duplicate_positions.php');
    }

    public function test_merging_preserves_every_reference_assignments_and_nonempty_requirements(): void
    {
        $migration = $this->migration();
        $migration->down();
        $department = Department::create(['name' => 'HR']);
        $primary = Position::create(['department_id' => $department->id, 'name' => 'Specialist']);
        $duplicate = Position::create(['department_id' => $department->id, 'name' => 'Specialist', 'duties' => 'Responsibilities', 'qualification' => 'Requirements']);
        $other = Position::create(['department_id' => Department::create(['name' => 'Finance'])->id, 'name' => 'Specialist']);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $primary->users()->attach($first);
        $duplicate->users()->attach([$first->id, $second->id]);
        $vacancy = Vacancy::create(['title' => 'Specialist', 'description' => 'Test', 'type' => 'staff', 'position_id' => $duplicate->id]);
        $application = Application::create(['user_id' => $first->id, 'vacancy_id' => $vacancy->id]);
        $request = PersonnelRequest::create(['employee_name' => 'Candidate', 'action_type' => 'transfer', 'department_id' => $department->id, 'position_id' => $duplicate->id, 'target_position_id' => $duplicate->id, 'status' => 'pending', 'created_by' => $first->id]);
        $ai = CandidateAIResult::create(['application_id' => $application->id, 'worker_id' => 1, 'position_id' => $duplicate->id, 'lang' => 'ru']);

        $migration->up();
        $migration->up();

        $this->assertDatabaseCount('positions', 2);
        $this->assertDatabaseHas('positions', ['id' => $other->id]);
        $this->assertDatabaseMissing('positions', ['id' => $duplicate->id]);
        $this->assertSame('Responsibilities', $primary->fresh()->duties);
        $this->assertSame('Requirements', $primary->fresh()->qualification);
        $this->assertSame($primary->id, $vacancy->fresh()->position_id);
        $this->assertSame($vacancy->id, $application->fresh()->vacancy_id);
        $this->assertSame($primary->id, $request->fresh()->position_id);
        $this->assertSame($primary->id, $request->fresh()->target_position_id);
        $this->assertSame($primary->id, $ai->fresh()->position_id);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $primary->users()->pluck('users.id')->all());
        $this->assertDatabaseCount('position_user', 2);
    }

    public function test_conflicting_requirements_abort_the_whole_merge_without_data_loss(): void
    {
        $migration = $this->migration();
        $migration->down();
        $department = Department::create(['name' => 'HR']);
        foreach (['One', 'One', 'Two', 'Two'] as $index => $name) {
            Position::create(['department_id' => $department->id, 'name' => $name, 'qualification' => $index === 3 ? 'Different' : 'Original']);
        }
        try {
            $migration->up();
            $this->fail('Conflicting requirements must not be discarded.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Conflicting qualification', $exception->getMessage());
        }
        $this->assertDatabaseCount('positions', 4);
        $this->assertDatabaseHas('positions', ['qualification' => 'Different']);
    }

    public function test_academic_duplicates_merge_within_the_same_chair_only(): void
    {
        $migration = $this->migration();
        $migration->down();
        $faculty = AcademicUnit::create(['name' => 'Faculty', 'kind' => 'faculty']);
        $chair = AcademicUnit::create(['name' => 'Chair', 'kind' => 'chair', 'parent_id' => $faculty->id]);
        $otherChair = AcademicUnit::create(['name' => 'Other chair', 'kind' => 'chair', 'parent_id' => $faculty->id]);
        foreach ([$chair, $chair, $otherChair] as $unit) {
            Position::create(['academic_unit_id' => $unit->id, 'name' => 'Assistant']);
        }
        $migration->up();
        $this->assertDatabaseCount('positions', 2);
        $this->assertSame(1, Position::where('academic_unit_id', $chair->id)->count());
        $this->assertSame(1, Position::where('academic_unit_id', $otherChair->id)->count());
        $this->expectException(QueryException::class);
        Position::create(['academic_unit_id' => $chair->id, 'name' => 'Assistant']);
    }

    public function test_database_rejects_a_new_duplicate_in_the_same_department(): void
    {
        $department = Department::create(['name' => 'HR']);
        Position::create(['department_id' => $department->id, 'name' => 'Specialist']);
        $this->expectException(QueryException::class);
        DB::table('positions')->insert(['department_id' => $department->id, 'name' => 'Specialist']);
    }
}
