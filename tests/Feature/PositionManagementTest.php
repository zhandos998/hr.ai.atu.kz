<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\CandidateAIResult;
use App\Models\Department;
use App\Models\PersonnelRequest;
use App\Models\Position;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PositionManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($this->admin);
    }

    private function ppsGroup(): array
    {
        $faculty = AcademicUnit::create(['name' => 'Faculty', 'kind' => 'faculty']);
        $positions = [];
        foreach (['First chair', 'Second chair'] as $name) {
            $chair = AcademicUnit::create(['name' => $name, 'kind' => 'chair', 'parent_id' => $faculty->id]);
            $positions[] = Position::create(['name' => 'Assistant', 'academic_unit_id' => $chair->id, 'duties' => $name]);
        }

        return $positions;
    }

    private function staffPosition(string $name = 'Specialist'): Position
    {
        return Position::create(['name' => $name, 'department_id' => Department::create(['name' => 'Department'])->id]);
    }

    public function test_pps_group_rename_preserves_ids_assignments_vacancies_and_individual_duties(): void
    {
        [$first, $second] = $this->ppsGroup();
        $staff = $this->staffPosition('Assistant');
        $second->users()->attach($this->admin);
        $vacancy = Vacancy::create(['title' => 'Assistant', 'description' => 'Test', 'type' => 'pps', 'position_id' => $second->id]);

        $this->getJson("/api/admin/pps-positions/{$first->id}")->assertOk()->assertJsonPath('name', 'Assistant');
        $this->get("/admin/pps-positions/{$first->id}/edit")->assertOk();
        $this->putJson("/api/admin/pps-positions/{$first->id}", ['name' => '  Lecturer  '])->assertOk();

        foreach ([$first, $second] as $position) {
            $this->assertDatabaseHas('positions', ['id' => $position->id, 'name' => 'Lecturer', 'academic_unit_id' => $position->academic_unit_id, 'duties' => $position->duties]);
        }
        $this->assertSame('Assistant', $staff->fresh()->name);
        $this->assertSame($second->id, $vacancy->fresh()->position_id);
        $this->assertTrue($second->users()->whereKey($this->admin->id)->exists());
        $this->getJson('/api/admin/pps-positions')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Lecturer')->assertJsonPath('0.chairs_count', 2);
    }

    public function test_pps_rename_rejects_existing_names_and_invalid_names_without_partial_changes(): void
    {
        [$first, $second] = $this->ppsGroup();
        Position::create(['name' => 'Professor', 'academic_unit_id' => $second->academic_unit_id]);
        foreach (['Professor', '  ', str_repeat('a', 256)] as $name) {
            $this->putJson("/api/admin/pps-positions/{$first->id}", ['name' => $name])->assertUnprocessable()->assertJsonValidationErrors('name');
            $this->assertSame('Assistant', $first->fresh()->name);
            $this->assertSame('Assistant', $second->fresh()->name);
        }
        $this->putJson("/api/admin/pps-positions/{$first->id}", ['name' => 'Assistant'])->assertOk();
        $this->postJson('/api/admin/pps-positions', ['name' => '  '])->assertUnprocessable();
    }

    public function test_pps_group_delete_removes_all_chair_copies_but_not_staff_namesakes(): void
    {
        [$first, $second] = $this->ppsGroup();
        $staff = $this->staffPosition('Assistant');
        $this->deleteJson("/api/admin/pps-positions/{$first->id}")->assertNoContent();
        $this->assertModelMissing($first);
        $this->assertModelMissing($second);
        $this->assertModelExists($staff);
    }

    public function test_staff_position_delete_keeps_other_department_and_academic_records(): void
    {
        $position = $this->staffPosition('Assistant');
        [$first, $second] = $this->ppsGroup();
        $this->deleteJson("/api/admin/positions/{$position->id}")->assertOk();
        $this->assertModelMissing($position);
        $this->assertModelExists($first);
        $this->assertModelExists($second);
    }

    public static function references(): array
    {
        $cases = [];
        foreach (['pps', 'staff'] as $type) {
            foreach (['vacancy', 'archived_vacancy', 'assignment', 'personnel_source', 'personnel_target', 'ai'] as $reference) {
                $cases[$type.' '.$reference] = [$type, $reference];
            }
        }

        return $cases;
    }

    #[DataProvider('references')]
    public function test_referenced_positions_cannot_be_deleted_and_pps_groups_are_atomic(string $type, string $reference): void
    {
        $group = $type === 'pps' ? $this->ppsGroup() : [$this->staffPosition()];
        $used = $group[count($group) - 1];
        if (in_array($reference, ['vacancy', 'archived_vacancy'], true)) {
            $record = Vacancy::create(['title' => 'Test', 'description' => 'Test', 'type' => $type, 'position_id' => $used->id]);
            if ($reference === 'archived_vacancy') {
                $record->delete();
            }
        } elseif ($reference === 'assignment') {
            $used->users()->attach($this->admin);
        } elseif ($reference === 'ai') {
            $record = CandidateAIResult::create(['worker_id' => 1, 'position_id' => $used->id, 'lang' => 'ru']);
        } else {
            $record = PersonnelRequest::create([
                'employee_name' => 'Employee', 'action_type' => 'transfer', 'status' => 'pending', 'created_by' => $this->admin->id,
                $reference === 'personnel_source' ? 'position_id' : 'target_position_id' => $used->id,
            ]);
        }

        $endpoint = $type === 'pps' ? 'pps-positions' : 'positions';
        $this->deleteJson("/api/admin/{$endpoint}/{$group[0]->id}")->assertUnprocessable()->assertJsonValidationErrors('name');
        foreach ($group as $position) {
            $this->assertModelExists($position);
        }
        if ($reference === 'assignment') {
            $this->assertTrue($used->users()->whereKey($this->admin->id)->exists());
        } else {
            $this->assertSame($used->id, $record->fresh()->{$reference === 'personnel_target' ? 'target_position_id' : 'position_id'});
        }
    }

    public function test_pps_endpoints_do_not_accept_staff_ids_or_unknown_ids(): void
    {
        $staff = $this->staffPosition();
        foreach ([$staff->id, 999999] as $id) {
            $this->getJson("/api/admin/pps-positions/{$id}")->assertNotFound();
            $this->putJson("/api/admin/pps-positions/{$id}", ['name' => 'Invalid'])->assertNotFound();
            $this->deleteJson("/api/admin/pps-positions/{$id}")->assertNotFound();
        }
        $this->assertModelExists($staff);
    }

    public function test_non_admin_cannot_manage_positions(): void
    {
        [$position] = $this->ppsGroup();
        $staff = $this->staffPosition();
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson("/api/admin/pps-positions/{$position->id}")->assertForbidden();
        $this->putJson("/api/admin/pps-positions/{$position->id}", ['name' => 'Invalid'])->assertForbidden();
        $this->deleteJson("/api/admin/pps-positions/{$position->id}")->assertForbidden();
        $this->deleteJson("/api/admin/positions/{$staff->id}")->assertForbidden();
        $this->assertSame('Assistant', $position->fresh()->name);
        $this->assertModelExists($staff);
    }

    public function test_protected_delete_message_is_localized(): void
    {
        $position = $this->staffPosition();
        $position->users()->attach($this->admin);
        $this->withHeader('X-Locale', 'kk')->deleteJson("/api/admin/positions/{$position->id}")
            ->assertUnprocessable()->assertJsonPath('errors.name.0', 'Лауазым бос орындарда, кадрлық процестерде, қызметкерлерді тағайындауда немесе AI бағалауларында қолданылады. Оны жою мүмкін емес.');
    }
}
