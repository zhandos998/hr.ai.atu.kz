<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Department;
use App\Models\PersonnelActionApprovalRoute;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherPersonnelRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $approver;

    private AcademicUnit $faculty;

    private AcademicUnit $chair;

    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->approver = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($this->admin);
        $this->faculty = AcademicUnit::create(['name' => 'Факультет тестовый', 'kind' => 'faculty']);
        $this->chair = AcademicUnit::create(['name' => 'Кафедра тестовая', 'kind' => 'chair', 'parent_id' => $this->faculty->id]);
        $this->position = Position::create(['name' => 'Ассистент', 'academic_unit_id' => $this->chair->id]);
        foreach (['hire', 'transfer', 'dismissal', 'maternity_leave'] as $action) {
            PersonnelActionApprovalRoute::create(['action_type' => $action, 'approver_user_id' => $this->approver->id, 'approval_order' => 1]);
        }
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'employee_type' => 'pps', 'employee_name' => 'Тестовый Преподаватель', 'action_type' => 'hire',
            'faculty_id' => $this->faculty->id, 'academic_unit_id' => $this->chair->id,
            'position_id' => $this->position->id, 'salary' => 300000,
        ], $overrides);
    }

    private function create(array $overrides = []): int
    {
        return $this->postJson('/api/admin/personnel/requests', $this->payload($overrides))->assertCreated()->json('request.id');
    }

    public function test_teacher_process_keeps_academic_ownership_documents_and_approvals(): void
    {
        $id = $this->create(['documents' => [UploadedFile::fake()->create('document.pdf', 1)]]);
        $this->getJson('/api/personnel/requests/'.$id)->assertOk()
            ->assertJsonPath('employee_type', 'pps')->assertJsonPath('department_id', null)
            ->assertJsonPath('faculty.name', $this->faculty->name)->assertJsonPath('academic_unit.name', $this->chair->name)
            ->assertJsonPath('position.name', 'Ассистент')->assertJsonCount(1, 'documents')->assertJsonCount(1, 'approvals');
        $this->assertDatabaseCount('users', 2);
    }

    public function test_staff_backwards_compatibility_and_type_filtered_lists(): void
    {
        $teacher = $this->create();
        $department = Department::create(['name' => 'Департамент']);
        $staff = $this->postJson('/api/admin/personnel/requests', ['employee_name' => 'Сотрудник', 'action_type' => 'dismissal', 'department_id' => $department->id])->assertCreated()->json('request.id');
        $this->getJson('/api/personnel/requests?employee_type=staff')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $staff)->assertJsonPath('data.0.employee_type', 'staff');
        $this->getJson('/api/personnel/requests?employee_type=pps')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $teacher);
        $this->getJson('/api/personnel/requests?employee_type=wrong')->assertUnprocessable();
    }

    public function test_invalid_or_mixed_faculty_chair_position_combinations_are_rejected(): void
    {
        $otherFaculty = AcademicUnit::create(['name' => 'Другой факультет', 'kind' => 'faculty']);
        $otherChair = AcademicUnit::create(['name' => 'Другая кафедра', 'kind' => 'chair', 'parent_id' => $otherFaculty->id]);
        $otherPosition = Position::create(['name' => 'Профессор', 'academic_unit_id' => $otherChair->id]);
        $department = Department::create(['name' => 'Департамент']);
        $staffPosition = Position::create(['name' => 'Специалист', 'department_id' => $department->id]);
        foreach ([
            ['faculty_id' => $otherFaculty->id], ['academic_unit_id' => $this->faculty->id],
            ['position_id' => $otherPosition->id], ['position_id' => $staffPosition->id],
            ['department_id' => $department->id], ['faculty_id' => null], ['academic_unit_id' => null], ['position_id' => null],
        ] as $overrides) {
            $this->postJson('/api/admin/personnel/requests', $this->payload($overrides))->assertUnprocessable();
        }
        $this->assertDatabaseCount('personnel_requests', 0);
    }

    public function test_teacher_transfers_require_and_validate_the_new_academic_structure(): void
    {
        $faculty = AcademicUnit::create(['name' => 'Новый факультет', 'kind' => 'faculty']);
        $chair = AcademicUnit::create(['name' => 'Новая кафедра', 'kind' => 'chair', 'parent_id' => $faculty->id]);
        $position = Position::create(['name' => 'Профессор', 'academic_unit_id' => $chair->id]);
        $this->postJson('/api/admin/personnel/requests', $this->payload(['action_type' => 'transfer']))->assertUnprocessable()->assertJsonValidationErrors(['target_faculty_id', 'target_academic_unit_id', 'target_position_id']);
        $target = ['action_type' => 'transfer', 'target_faculty_id' => $faculty->id, 'target_academic_unit_id' => $chair->id, 'target_position_id' => $position->id];
        $id = $this->create($target);
        $this->getJson('/api/personnel/requests/'.$id)->assertOk()->assertJsonPath('target_faculty.name', 'Новый факультет')->assertJsonPath('target_academic_unit.name', 'Новая кафедра')->assertJsonPath('target_position.name', 'Профессор')->assertJsonPath('salary', null);
        $this->postJson('/api/admin/personnel/requests', $this->payload([...$target, 'target_faculty_id' => $this->faculty->id]))->assertUnprocessable();
        $this->postJson('/api/admin/personnel/requests', $this->payload([...$target, 'target_position_id' => $this->position->id]))->assertUnprocessable();
    }

    public function test_edit_preserves_type_and_commission_and_clears_transfer_fields(): void
    {
        $id = $this->create(['action_type' => 'transfer', 'target_faculty_id' => $this->faculty->id, 'target_academic_unit_id' => $this->chair->id, 'target_position_id' => $this->position->id]);
        $this->postJson('/api/admin/personnel/requests/'.$id.'/update', $this->payload(['employee_type' => 'staff']))->assertUnprocessable()->assertJsonValidationErrors('employee_type');
        $this->postJson('/api/admin/personnel/requests/'.$id.'/update', $this->payload(['action_type' => 'dismissal']))->assertOk()
            ->assertJsonPath('request.employee_type', 'pps')->assertJsonPath('request.target_academic_unit_id', null)->assertJsonCount(1, 'request.approvals');
    }

    public function test_approvers_keep_assigned_only_access_and_financial_visibility(): void
    {
        $id = $this->create();
        Sanctum::actingAs($this->approver);
        $this->getJson('/api/personnel/requests?employee_type=pps')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.salary');
        $this->getJson('/api/personnel/requests/'.$id)->assertOk()->assertJsonMissingPath('salary')->assertJsonMissingPath('documents');
        $this->postJson('/api/personnel/requests/'.$id.'/decision', ['decision' => 'approved'])->assertOk()->assertJsonPath('request.status', 'approved');
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/personnel/requests?employee_type=pps')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/personnel/requests/'.$id)->assertNotFound();
    }

    public function test_public_status_and_reference_data_support_academic_units_without_private_fields(): void
    {
        $id = $this->create();
        $this->getJson('/api/admin/personnel/reference-data')->assertOk()->assertJsonCount(2, 'academic_units');
        $this->postJson('/api/personnel/status', ['employee_name' => 'Тестовый'])->assertOk()->assertJsonPath('data.0.employee_type', 'pps')->assertJsonPath('data.0.faculty.name', $this->faculty->name)->assertJsonPath('data.0.academic_unit.name', $this->chair->name)->assertJsonMissingPath('data.0.salary')->assertJsonMissingPath('data.0.documents');
        $this->getJson('/api/personnel/requests?employee_type=pps&q='.urlencode($this->faculty->name))->assertOk()->assertJsonPath('data.0.id', $id);
    }

    public function test_teacher_processes_archive_and_block_deletion_of_referenced_academic_units(): void
    {
        $id = $this->create();
        $this->postJson('/api/admin/personnel/requests/'.$id.'/archive')->assertOk();
        $this->getJson('/api/personnel/requests?employee_type=pps')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/personnel/requests?employee_type=pps&archived=1')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->position->delete();
        $this->deleteJson('/api/admin/academic-units/'.$this->chair->id)->assertUnprocessable();
        $this->postJson('/api/admin/personnel/requests/'.$id.'/restore')->assertOk();
    }
}
