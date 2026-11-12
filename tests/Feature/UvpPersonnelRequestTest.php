<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Department;
use App\Models\PersonnelActionApprovalRoute;
use App\Models\Position;
use App\Models\User;
use App\Models\UvpPosition;
use Database\Seeders\UvpPositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UvpPersonnelRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $approver;

    private AcademicUnit $faculty;

    private AcademicUnit $chair;

    private UvpPosition $position;

    private Position $ppsPosition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->approver = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($this->admin);
        $this->faculty = AcademicUnit::create(['name' => 'Факультет', 'kind' => 'faculty']);
        $this->chair = AcademicUnit::create(['name' => 'Кафедра', 'kind' => 'chair', 'parent_id' => $this->faculty->id]);
        $this->ppsPosition = Position::create(['name' => 'Ассистент', 'academic_unit_id' => $this->chair->id]);
        $this->seed(UvpPositionSeeder::class);
        $this->position = UvpPosition::where('name', 'Лаборант')->firstOrFail();
        foreach (['hire', 'dismissal', 'transfer', 'maternity_leave'] as $action) {
            PersonnelActionApprovalRoute::create(['action_type' => $action, 'approver_user_id' => $this->approver->id, 'approval_order' => 1]);
        }
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'employee_type' => 'uvp', 'employee_name' => 'Тестовый Лаборант', 'action_type' => 'hire',
            'faculty_id' => $this->faculty->id, 'academic_unit_id' => $this->chair->id,
            'uvp_position_id' => $this->position->id, 'salary' => 200000,
        ], $overrides);
    }

    private function create(array $overrides = []): int
    {
        return $this->postJson('/api/admin/personnel/requests', $this->payload($overrides))->assertCreated()->json('request.id');
    }

    public function test_uvp_uses_academic_structure_and_is_isolated_from_both_existing_categories(): void
    {
        $id = $this->create();
        $this->create(['employee_type' => 'pps', 'employee_name' => 'Преподаватель', 'uvp_position_id' => null, 'position_id' => $this->ppsPosition->id]);
        $department = Department::create(['name' => 'Департамент']);
        $this->postJson('/api/admin/personnel/requests', ['employee_name' => 'Сотрудник', 'action_type' => 'dismissal', 'department_id' => $department->id])->assertCreated();
        $this->getJson('/api/personnel/requests?employee_type=uvp')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.employee_type', 'uvp');
        $this->getJson('/api/personnel/requests?employee_type=pps')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.employee_type', 'pps');
        $this->getJson('/api/personnel/requests?employee_type=staff')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.employee_type', 'staff');
        $this->getJson('/api/personnel/requests/'.$id)->assertOk()->assertJsonPath('department_id', null)->assertJsonPath('faculty.name', 'Факультет')->assertJsonPath('academic_unit.name', 'Кафедра')->assertJsonPath('position_id', null)->assertJsonPath('position', null)->assertJsonPath('uvp_position.name', 'Лаборант')->assertJsonCount(1, 'approvals');
        $this->assertDatabaseCount('users', 2);
    }

    public function test_uvp_requires_a_matching_faculty_chair_and_position_and_rejects_departments(): void
    {
        $faculty = AcademicUnit::create(['name' => 'Другой факультет', 'kind' => 'faculty']);
        $chair = AcademicUnit::create(['name' => 'Другая кафедра', 'kind' => 'chair', 'parent_id' => $faculty->id]);
        $position = Position::create(['name' => 'Техник', 'academic_unit_id' => $chair->id]);
        $department = Department::create(['name' => 'Департамент']);
        $staffPosition = Position::create(['name' => 'Специалист', 'department_id' => $department->id]);
        foreach ([
            ['faculty_id' => null], ['academic_unit_id' => null], ['uvp_position_id' => null],
            ['faculty_id' => $faculty->id], ['academic_unit_id' => $this->faculty->id],
            ['position_id' => $position->id], ['position_id' => $staffPosition->id], ['department_id' => $department->id],
            ['uvp_position_id' => 999999],
        ] as $overrides) {
            $this->postJson('/api/admin/personnel/requests', $this->payload($overrides))->assertUnprocessable();
        }
        $this->assertDatabaseCount('personnel_requests', 0);
    }

    public function test_uvp_transfer_edit_and_category_lock_work_for_both_academic_structures(): void
    {
        $faculty = AcademicUnit::create(['name' => 'Новый факультет', 'kind' => 'faculty']);
        $chair = AcademicUnit::create(['name' => 'Новая кафедра', 'kind' => 'chair', 'parent_id' => $faculty->id]);
        $position = UvpPosition::where('name', 'Старший лаборант')->firstOrFail();
        $this->postJson('/api/admin/personnel/requests', $this->payload(['action_type' => 'transfer']))->assertUnprocessable()->assertJsonValidationErrors(['target_faculty_id', 'target_academic_unit_id', 'target_uvp_position_id']);
        $target = ['action_type' => 'transfer', 'target_faculty_id' => $faculty->id, 'target_academic_unit_id' => $chair->id, 'target_uvp_position_id' => $position->id];
        $id = $this->create($target);
        $this->getJson('/api/personnel/requests/'.$id)->assertOk()->assertJsonPath('target_academic_unit.name', 'Новая кафедра')->assertJsonPath('target_position_id', null)->assertJsonPath('target_uvp_position.name', 'Старший лаборант')->assertJsonPath('salary', null);
        $endpoint = '/api/admin/personnel/requests/'.$id.'/update';
        foreach (['staff', 'pps'] as $type) {
            $this->postJson($endpoint, $this->payload([...$target, 'employee_type' => $type]))->assertUnprocessable()->assertJsonValidationErrors('employee_type');
        }
        $this->postJson($endpoint, $this->payload([...$target, 'target_uvp_position_id' => 999999]))->assertUnprocessable();
        $this->postJson($endpoint, $this->payload([...$target, 'target_position_id' => $this->ppsPosition->id]))->assertUnprocessable();
        $this->postJson($endpoint, $this->payload(['action_type' => 'dismissal']))->assertOk()->assertJsonPath('request.employee_type', 'uvp')->assertJsonPath('request.target_faculty_id', null)->assertJsonPath('request.target_academic_unit_id', null)->assertJsonPath('request.target_uvp_position_id', null)->assertJsonCount(1, 'request.approvals');
    }

    public function test_uvp_assigned_only_access_financial_visibility_and_voting_are_preserved(): void
    {
        $id = $this->create();
        Sanctum::actingAs($this->approver);
        $this->getJson('/api/personnel/requests?employee_type=uvp')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.salary');
        $this->getJson('/api/personnel/requests/'.$id)->assertOk()->assertJsonMissingPath('salary')->assertJsonMissingPath('documents');
        $this->postJson('/api/personnel/requests/'.$id.'/decision', ['decision' => 'approved'])->assertOk()->assertJsonPath('request.status', 'approved');
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/personnel/requests?employee_type=uvp')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/personnel/requests/'.$id)->assertNotFound();
        $this->postJson('/api/admin/personnel/requests', $this->payload())->assertForbidden();
    }

    public function test_uvp_archiving_search_and_public_status_keep_the_category_and_private_data_rules(): void
    {
        $id = $this->create();
        $this->postJson('/api/personnel/status', ['employee_name' => 'Тестовый'])->assertOk()->assertJsonPath('data.0.employee_type', 'uvp')->assertJsonPath('data.0.faculty.name', 'Факультет')->assertJsonPath('data.0.academic_unit.name', 'Кафедра')->assertJsonPath('data.0.position.name', 'Лаборант')->assertJsonMissingPath('data.0.salary')->assertJsonMissingPath('data.0.documents');
        $this->getJson('/api/personnel/requests?employee_type=uvp&q='.urlencode('Лаборант'))->assertOk()->assertJsonPath('data.0.uvp_position.name', 'Лаборант');
        $this->getJson('/api/personnel/requests?employee_type=uvp&q='.urlencode('Кафедра'))->assertOk()->assertJsonPath('data.0.id', $id);
        $this->postJson('/api/admin/personnel/requests/'.$id.'/archive')->assertOk();
        $this->getJson('/api/personnel/requests?employee_type=uvp')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/personnel/requests?employee_type=uvp&archived=1')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->postJson('/api/admin/personnel/requests/'.$id.'/restore')->assertOk()->assertJsonPath('request.employee_type', 'uvp');
        $this->assertDatabaseCount('personnel_requests', 1);
    }

    public function test_rollback_guard_also_protects_uvp_records(): void
    {
        $this->create();
        $migration = require database_path('migrations/2026_10_29_160000_add_employee_type_to_personnel_requests.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_reference_data_has_all_seven_global_uvp_jobs_and_they_work_for_any_chair(): void
    {
        $this->getJson('/api/admin/personnel/reference-data')->assertOk()->assertJsonCount(7, 'uvp_positions')
            ->assertJsonPath('uvp_positions.0.name', 'Заведующий лабораторией')
            ->assertJsonPath('uvp_positions.6.name', 'Инженер ВКК');
        $chair = AcademicUnit::create(['name' => 'Вторая кафедра', 'kind' => 'chair', 'parent_id' => $this->faculty->id]);
        $this->create();
        $this->create(['academic_unit_id' => $chair->id]);
        $this->assertDatabaseCount('uvp_positions', 7);
        $this->assertDatabaseCount('positions', 1);
    }

    public function test_uvp_ids_cannot_be_submitted_for_pps_or_staff_even_if_ids_overlap(): void
    {
        $this->postJson('/api/admin/personnel/requests', $this->payload(['employee_type' => 'pps', 'position_id' => $this->ppsPosition->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('uvp_position_id');
        $department = Department::create(['name' => 'Департамент']);
        $this->postJson('/api/admin/personnel/requests', [
            'employee_type' => 'staff', 'employee_name' => 'Сотрудник', 'action_type' => 'dismissal',
            'department_id' => $department->id, 'uvp_position_id' => $this->position->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('uvp_position_id');
    }

    public function test_new_reference_migration_cannot_be_rolled_back_with_uvp_requests(): void
    {
        $this->create();
        $migration = require database_path('migrations/2026_10_30_130000_add_uvp_positions_to_personnel_requests.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_used_uvp_positions_cannot_be_deleted(): void
    {
        $this->create();
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->position->delete();
    }

    public function test_legacy_uvp_position_references_remain_readable_without_automatic_reassignment(): void
    {
        $item = \App\Models\PersonnelRequest::create([
            'employee_type' => 'uvp', 'employee_name' => 'Старый сотрудник', 'action_type' => 'dismissal',
            'faculty_id' => $this->faculty->id, 'academic_unit_id' => $this->chair->id,
            'position_id' => $this->ppsPosition->id, 'status' => 'pending', 'created_by' => $this->admin->id,
        ]);
        $this->getJson('/api/personnel/requests/'.$item->id)->assertOk()
            ->assertJsonPath('position.name', 'Ассистент')->assertJsonPath('uvp_position', null);
        $this->postJson('/api/personnel/status', ['employee_name' => 'Старый'])->assertOk()
            ->assertJsonPath('data.0.position.name', 'Ассистент');
        $this->assertNull($item->fresh()->uvp_position_id);
    }
}
