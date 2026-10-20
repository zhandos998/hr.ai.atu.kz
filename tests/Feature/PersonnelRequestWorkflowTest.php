<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PersonnelActionApprovalRoute;
use App\Models\PersonnelRequest;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonnelRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_personnel_pages_are_rendered_by_inertia_react(): void
    {
        $this->get('/personnel-status')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Personnel/PublicStatus'));

        $this->get('/admin/personnel-requests')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Personnel/Index')
                ->where('admin', true));

        $this->get('/admin/personnel-requests/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Personnel/RequestForm')
                ->where('mode', 'create')
                ->where('requestId', null));

        $this->get('/admin/personnel-requests/15/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Personnel/RequestForm')
                ->where('mode', 'edit')
                ->where('requestId', 15));

        $this->get('/admin/personnel-approval-routes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Personnel/ApprovalRoutes'));

        $this->get('/personnel/requests/15')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Personnel/Show')
                ->where('admin', false)
                ->where('requestId', 15));
    }

    public function test_guest_can_find_public_personnel_status_without_confidential_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $approver = User::factory()->create([
            'name' => 'Согласующий Сотрудник',
            'email' => 'private-approver@example.com',
            'contact_info' => 'Кабинет 214, внутренний номер 123',
        ]);
        $department = Department::query()->create(['name' => 'Отдел кадров']);
        $position = Position::query()->create([
            'department_id' => $department->id,
            'name' => 'Главный специалист',
        ]);
        $position->users()->attach($approver);
        $personnelRequest = PersonnelRequest::query()->create([
            'employee_name' => 'Иванов Иван Иванович',
            'action_type' => 'hire',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'salary' => 500000,
            'work_experience' => '10 лет',
            'details' => 'Внутренняя информация',
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);
        $personnelRequest->approvals()->create([
            'user_id' => $approver->id,
            'approval_order' => 1,
            'decision' => 'approved',
            'comment' => 'Служебный комментарий',
            'decided_at' => now(),
        ]);

        $this->postJson('/api/personnel/status', [
            'employee_name' => '  Иван   Иванов  ',
        ])->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_name', 'Иванов Иван Иванович')
            ->assertJsonPath('data.0.approvals.0.user.name', 'Согласующий Сотрудник')
            ->assertJsonPath('data.0.approvals.0.user.contact_info', 'Кабинет 214, внутренний номер 123')
            ->assertJsonPath('data.0.approvals.0.user.positions.0.name', 'Главный специалист')
            ->assertJsonPath('data.0.approvals.0.user.positions.0.unit_name', 'Отдел кадров')
            ->assertJsonPath('data.0.approvals.0.decision', 'approved')
            ->assertJsonMissingPath('data.0.salary')
            ->assertJsonMissingPath('data.0.work_experience')
            ->assertJsonMissingPath('data.0.details')
            ->assertJsonMissingPath('data.0.documents')
            ->assertJsonMissingPath('data.0.approvals.0.comment')
            ->assertJsonMissingPath('data.0.approvals.0.user.email');

        $this->postJson('/api/personnel/status', [
            'employee_name' => 'Иванов',
        ])->assertOk()->assertJsonCount(1, 'data');

        $this->postJson('/api/personnel/status', [
            'employee_name' => '%',
        ])->assertUnprocessable()->assertJsonValidationErrors('employee_name');
    }

    public function test_admin_creates_request_with_manually_selected_commission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $defaultApprover = User::factory()->create(['role' => 'user']);
        $firstApprover = User::factory()->create(['role' => 'user']);
        $secondApprover = User::factory()->create(['role' => 'user']);
        $department = Department::query()->create(['name' => 'Финансовый департамент']);
        $position = Position::query()->create([
            'department_id' => $department->id,
            'name' => 'Экономист',
        ]);

        PersonnelActionApprovalRoute::query()->create([
            'action_type' => 'hire',
            'approver_user_id' => $defaultApprover->id,
            'approval_order' => 1,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/personnel/requests', [
            'employee_name' => 'Тестовый Сотрудник',
            'action_type' => 'hire',
            'department_id' => $department->id,
            'position_id' => $position->id,
            'salary' => 350000,
            'work_experience' => '5 лет',
            'approver_user_ids' => [$secondApprover->id, $firstApprover->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('request.status', 'pending')
            ->assertJsonPath('request.approvals.0.user_id', $secondApprover->id)
            ->assertJsonPath('request.approvals.0.approval_order', 1)
            ->assertJsonPath('request.approvals.1.user_id', $firstApprover->id)
            ->assertJsonPath('request.approvals.1.approval_order', 2);

        $this->assertDatabaseHas('personnel_requests', [
            'employee_name' => 'Тестовый Сотрудник',
            'created_by' => $admin->id,
        ]);
        $this->assertDatabaseMissing('personnel_request_approvals', [
            'user_id' => $defaultApprover->id,
        ]);
    }

    public function test_admin_can_create_and_update_hire_request_without_work_experience(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $approver = User::factory()->create(['role' => 'user']);
        $department = Department::query()->create(['name' => 'Отдел кадров']);
        Sanctum::actingAs($admin);

        $payload = [
            'employee_name' => 'Новый сотрудник',
            'action_type' => 'hire',
            'department_id' => $department->id,
            'salary' => 350000,
            'approver_user_ids' => [$approver->id],
        ];
        $id = $this->postJson('/api/admin/personnel/requests', $payload)
            ->assertCreated()->json('request.id');

        $this->assertDatabaseHas('personnel_requests', ['id' => $id, 'work_experience' => null]);

        unset($payload['approver_user_ids']);
        $payload['salary'] = 400000;
        $this->postJson("/api/admin/personnel/requests/{$id}/update", $payload)->assertOk();
        $this->assertDatabaseHas('personnel_requests', ['id' => $id, 'work_experience' => null, 'salary' => 400000]);

        PersonnelRequest::query()->findOrFail($id)->update(['work_experience' => '5 лет']);
        $this->postJson("/api/admin/personnel/requests/{$id}/update", $payload)->assertOk();
        $this->assertDatabaseHas('personnel_requests', ['id' => $id, 'work_experience' => '5 лет']);
    }

    public function test_creating_request_requires_manually_selected_commission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::query()->create(['name' => 'Отдел без комиссии']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/personnel/requests', [
            'employee_name' => 'Тестовый Сотрудник',
            'action_type' => 'dismissal',
            'department_id' => $department->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('approver_user_ids');

        $this->assertDatabaseCount('personnel_requests', 0);
    }

    public function test_admin_cannot_add_an_unavailable_user_to_commission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::query()->create(['name' => 'Отдел кадров']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/personnel/requests', [
            'employee_name' => 'Тестовый Сотрудник',
            'action_type' => 'dismissal',
            'department_id' => $department->id,
            'approver_user_ids' => [$admin->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('approver_user_ids');

        $this->assertDatabaseCount('personnel_requests', 0);
    }

    public function test_all_approvers_must_approve_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $firstApprover = User::factory()->create();
        $secondApprover = User::factory()->create();
        $department = Department::query()->create(['name' => 'Отдел кадров']);

        $personnelRequest = PersonnelRequest::query()->create([
            'employee_name' => 'Тестовый Сотрудник',
            'action_type' => 'dismissal',
            'department_id' => $department->id,
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);
        $personnelRequest->approvals()->createMany([
            ['user_id' => $firstApprover->id, 'approval_order' => 1],
            ['user_id' => $secondApprover->id, 'approval_order' => 2],
        ]);

        Sanctum::actingAs($firstApprover);
        $this->postJson("/api/personnel/requests/{$personnelRequest->id}/decision", [
            'decision' => 'approved',
            'comment' => 'Согласовано',
        ])->assertOk()->assertJsonPath('request.status', 'pending');

        Sanctum::actingAs($secondApprover);
        $this->postJson("/api/personnel/requests/{$personnelRequest->id}/decision", [
            'decision' => 'approved',
        ])->assertOk()->assertJsonPath('request.status', 'approved');
    }

    public function test_admin_can_upload_multiple_documents_and_remove_one(): void
    {
        Storage::fake('documents');
        $admin = User::factory()->create(['role' => 'admin']);
        $approver = User::factory()->create();
        $department = Department::query()->create(['name' => 'HR']);
        PersonnelActionApprovalRoute::query()->create([
            'action_type' => 'dismissal',
            'approver_user_id' => $approver->id,
            'approval_order' => 1,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->post('/api/admin/personnel/requests', [
            'employee_name' => 'Документ Тест',
            'action_type' => 'dismissal',
            'department_id' => $department->id,
            'approver_user_ids' => [$approver->id],
            'documents' => [
                UploadedFile::fake()->create('приказ.pdf', 20, 'application/pdf'),
                UploadedFile::fake()->image('заявление.png'),
            ],
        ]);

        $response->assertCreated()->assertJsonCount(2, 'request.documents');
        $requestId = $response->json('request.id');
        $documentId = $response->json('request.documents.0.id');
        $this->assertDatabaseCount('personnel_request_documents', 2);

        $this->deleteJson("/api/admin/personnel/requests/{$requestId}/documents/{$documentId}")
            ->assertOk();

        $this->assertDatabaseCount('personnel_request_documents', 1);
    }

    public function test_editing_department_preserves_pending_commission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $oldApprover = User::factory()->create();
        $newApprover = User::factory()->create();
        $oldDepartment = Department::query()->create(['name' => 'Старое подразделение']);
        $newDepartment = Department::query()->create(['name' => 'Новое подразделение']);
        PersonnelActionApprovalRoute::query()->create([
            'action_type' => 'dismissal',
            'approver_user_id' => $newApprover->id,
            'approval_order' => 1,
        ]);
        $personnelRequest = PersonnelRequest::query()->create([
            'employee_name' => 'Сотрудник',
            'action_type' => 'dismissal',
            'department_id' => $oldDepartment->id,
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);
        $personnelRequest->approvals()->create(['user_id' => $oldApprover->id, 'approval_order' => 1]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/personnel/requests/{$personnelRequest->id}/update", [
            'employee_name' => 'Сотрудник',
            'action_type' => 'dismissal',
            'department_id' => $newDepartment->id,
        ])->assertOk()->assertJsonPath('request.approvals.0.user_id', $oldApprover->id);

        $this->assertDatabaseHas('personnel_request_approvals', [
            'personnel_request_id' => $personnelRequest->id,
            'user_id' => $oldApprover->id,
        ]);
    }

    public function test_admin_can_customize_request_commission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $firstApprover = User::factory()->create();
        $secondApprover = User::factory()->create();
        $department = Department::query()->create(['name' => 'Комиссия']);
        $personnelRequest = PersonnelRequest::query()->create([
            'employee_name' => 'Сотрудник',
            'action_type' => 'dismissal',
            'department_id' => $department->id,
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);
        $personnelRequest->approvals()->create(['user_id' => $firstApprover->id, 'approval_order' => 1]);
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/personnel/requests/{$personnelRequest->id}/approvers", [
            'user_id' => $secondApprover->id,
            'approval_order' => 2,
        ])->assertCreated()->assertJsonCount(2, 'request.approvals');

        $secondApprovalId = collect($response->json('request.approvals'))
            ->firstWhere('user_id', $secondApprover->id)['id'];

        $this->deleteJson("/api/admin/personnel/requests/{$personnelRequest->id}/approvers/{$secondApprovalId}")
            ->assertOk()
            ->assertJsonCount(1, 'request.approvals');
    }

    public function test_rejection_requires_comment_and_rejects_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $approver = User::factory()->create();
        $department = Department::query()->create(['name' => 'Юридический департамент']);
        $personnelRequest = PersonnelRequest::query()->create([
            'employee_name' => 'Тестовый Сотрудник',
            'action_type' => 'transfer',
            'department_id' => $department->id,
            'target_department_id' => $department->id,
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);
        $personnelRequest->approvals()->create(['user_id' => $approver->id]);

        Sanctum::actingAs($approver);

        $this->postJson("/api/personnel/requests/{$personnelRequest->id}/decision", [
            'decision' => 'rejected',
        ])->assertUnprocessable();

        $this->postJson("/api/personnel/requests/{$personnelRequest->id}/decision", [
            'decision' => 'rejected',
            'comment' => 'Требуется исправить документы',
        ])->assertOk()->assertJsonPath('request.status', 'rejected');
    }

    public function test_only_economists_and_admins_can_view_salary_and_documents(): void
    {
        Storage::fake('documents');
        $admin = User::factory()->create(['role' => 'admin']);
        $economist = User::factory()->create(['role' => 'economist']);
        $ordinaryApprover = User::factory()->create(['role' => 'user']);
        $department = Department::query()->create(['name' => 'Финансовый департамент']);
        $personnelRequest = PersonnelRequest::query()->create([
            'employee_name' => 'Сотрудник с окладом',
            'action_type' => 'hire',
            'department_id' => $department->id,
            'salary' => 450000,
            'work_experience' => '7 лет',
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);
        $personnelRequest->approvals()->createMany([
            ['user_id' => $economist->id, 'approval_order' => 1],
            ['user_id' => $ordinaryApprover->id, 'approval_order' => 2],
        ]);
        $path = "personnel-requests/{$personnelRequest->id}/contract.pdf";
        Storage::disk('documents')->put($path, 'confidential');
        $document = $personnelRequest->documents()->create([
            'original_name' => 'contract.pdf',
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => 12,
        ]);

        Sanctum::actingAs($ordinaryApprover);
        $this->getJson("/api/personnel/requests/{$personnelRequest->id}")
            ->assertOk()
            ->assertJsonPath('can_view_financial_data', false)
            ->assertJsonMissingPath('salary')
            ->assertJsonMissingPath('documents');
        $this->getJson('/api/personnel/requests')
            ->assertOk()
            ->assertJsonMissingPath('data.0.salary')
            ->assertJsonMissingPath('data.0.documents_count');
        $this->get("/api/personnel/requests/{$personnelRequest->id}/documents/{$document->id}")
            ->assertForbidden();

        Sanctum::actingAs($economist);
        $this->getJson("/api/personnel/requests/{$personnelRequest->id}")
            ->assertOk()
            ->assertJsonPath('can_view_financial_data', true)
            ->assertJsonPath('salary', '450000.00')
            ->assertJsonCount(1, 'documents');
        $this->get("/api/personnel/requests/{$personnelRequest->id}/documents/{$document->id}")
            ->assertOk();

        Sanctum::actingAs($admin);
        $this->getJson("/api/personnel/requests/{$personnelRequest->id}")
            ->assertOk()
            ->assertJsonPath('can_view_financial_data', true)
            ->assertJsonCount(1, 'documents');
    }

    public function test_unassigned_user_cannot_open_personnel_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unassignedUser = User::factory()->create();
        $department = Department::query()->create(['name' => 'Департамент закупок']);
        $personnelRequest = PersonnelRequest::query()->create([
            'employee_name' => 'Тестовый Сотрудник',
            'action_type' => 'maternity_leave',
            'department_id' => $department->id,
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        Sanctum::actingAs($unassignedUser);

        $this->getJson("/api/personnel/requests/{$personnelRequest->id}")->assertNotFound();
    }
}
