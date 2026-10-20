<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PersonnelActionApprovalRoute;
use App\Models\PersonnelApprovalRoute;
use App\Models\PersonnelRequest;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonnelActionApprovalRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_saves_separate_templates_with_variable_member_counts(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $ids = User::factory()->count(10)->create()->modelKeys();

        foreach ([1, 5, 10] as $count) {
            $this->putJson('/api/admin/personnel/approval-routes/hire', ['approver_user_ids' => array_slice($ids, 0, $count)])->assertCreated();
            $this->assertSame($count, PersonnelActionApprovalRoute::where('action_type', 'hire')->count());
        }
        $this->putJson('/api/admin/personnel/approval-routes/dismissal', ['approver_user_ids' => [$ids[6]]])->assertCreated();
        $this->putJson('/api/admin/personnel/approval-routes/transfer', ['approver_user_ids' => [$ids[1]]])->assertCreated();
        $this->putJson('/api/admin/personnel/approval-routes/maternity_leave', ['approver_user_ids' => [$ids[2]]])->assertCreated();

        foreach ([[$ids[0], $ids[0]], []] as $invalid) {
            $this->putJson('/api/admin/personnel/approval-routes/hire', ['approver_user_ids' => $invalid])->assertUnprocessable();
        }
        $this->assertDatabaseCount('personnel_action_approval_routes', 13);
        $this->getJson('/api/admin/personnel/approval-routes')->assertOk()->assertJsonCount(13);

        $ordered = array_reverse(array_slice($ids, 0, 6));
        $this->putJson('/api/admin/personnel/approval-routes/hire', ['approver_user_ids' => $ordered])->assertCreated();
        $this->assertSame($ordered, PersonnelActionApprovalRoute::where('action_type', 'hire')->orderBy('approval_order')->pluck('approver_user_id')->all());
        $this->assertDatabaseHas('personnel_action_approval_routes', ['action_type' => 'dismissal', 'approver_user_id' => $ids[6]]);
    }

    public function test_only_admin_can_configure_templates_and_unavailable_users_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $candidate = User::factory()->create(['email' => 'manual-candidate-example@hr-ai.invalid']);
        $this->putJson('/api/admin/personnel/approval-routes/dismissal', ['approver_user_ids' => [$user->id]])->assertUnauthorized();
        Sanctum::actingAs($user);
        $this->getJson('/api/admin/personnel/approval-routes')->assertForbidden();
        $this->putJson('/api/admin/personnel/approval-routes/dismissal', ['approver_user_ids' => [$user->id]])->assertForbidden();
        Sanctum::actingAs($admin);
        foreach ([$admin->id, $candidate->id, 99999] as $id) {
            $this->putJson('/api/admin/personnel/approval-routes/dismissal', ['approver_user_ids' => [$id]])->assertUnprocessable();
        }
        $this->putJson('/api/admin/personnel/approval-routes/unknown', ['approver_user_ids' => [$user->id]])->assertNotFound();
        $this->assertDatabaseCount('personnel_action_approval_routes', 0);
    }

    public function test_new_requests_use_action_templates_regardless_of_department(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $ids = User::factory()->count(6)->create()->modelKeys();
        $first = Department::create(['name' => 'Первое подразделение']);
        $second = Department::create(['name' => 'Второе подразделение']);
        foreach (['hire', 'dismissal', 'transfer', 'maternity_leave'] as $action) {
            $expected = $action === 'hire' ? $ids : [$ids[2], $ids[1]];
            $this->putJson('/api/admin/personnel/approval-routes/'.$action, ['approver_user_ids' => $expected])->assertCreated();
            foreach ([$first, $second] as $department) {
                $this->postJson('/api/admin/personnel/requests', [
                    'employee_name' => 'Сотрудник', 'action_type' => $action,
                    'department_id' => $department->id, 'salary' => 1000,
                    'target_department_id' => $second->id,
                ])->assertCreated()->assertJsonCount(count($expected), 'request.approvals')
                    ->assertJsonPath('request.approvals.0.user_id', $expected[0])
                    ->assertJsonPath('request.approvals.1.user_id', $expected[1]);
            }
        }
        $this->assertDatabaseCount('personnel_requests', 8);
    }

    public function test_missing_or_ineligible_defaults_do_not_create_requests(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $department = Department::create(['name' => 'Подразделение']);
        $payload = ['employee_name' => 'Сотрудник', 'action_type' => 'hire', 'department_id' => $department->id, 'salary' => 1000];
        $this->postJson('/api/admin/personnel/requests', $payload)->assertUnprocessable()->assertJsonValidationErrors('approver_user_ids');
        $user = User::factory()->create(['role' => 'admin']);
        PersonnelActionApprovalRoute::create(['action_type' => 'hire', 'approver_user_id' => $user->id, 'approval_order' => 1]);
        $this->postJson('/api/admin/personnel/requests', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('personnel_requests', 0);
    }

    public function test_hire_requests_copy_one_or_ten_default_approvers(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $department = Department::create(['name' => 'Подразделение']);
        $ids = User::factory()->count(10)->create()->modelKeys();
        foreach ([1, 10] as $count) {
            $this->putJson('/api/admin/personnel/approval-routes/hire', ['approver_user_ids' => array_slice($ids, 0, $count)])->assertCreated();
            $this->postJson('/api/admin/personnel/requests', [
                'employee_name' => 'Сотрудник', 'action_type' => 'hire',
                'department_id' => $department->id, 'salary' => 1000,
            ])->assertCreated()->assertJsonCount($count, 'request.approvals');
        }
    }

    public function test_assigned_position_is_visible_in_templates_and_reference_data(): void
    {
        $user = User::factory()->create();
        $department = Department::create(['name' => 'Подразделение']);
        $position = Position::create(['department_id' => $department->id, 'name' => 'Специалист']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson('/api/admin/personnel/approval-routes/hire', ['approver_user_ids' => [$user->id]])->assertCreated();
        $this->postJson("/api/admin/users/{$user->id}/positions", ['position_id' => $position->id])
            ->assertOk()->assertJsonPath('user.positions.0.id', $position->id);
        $this->getJson('/api/admin/personnel/approval-routes')->assertOk()
            ->assertJsonPath('0.approver.positions.0.name', 'Специалист')
            ->assertJsonPath('0.approver.positions.0.department.name', 'Подразделение');
        $this->getJson('/api/admin/personnel/reference-data')->assertOk()
            ->assertJsonPath('users.0.positions.0.id', $position->id);
        $this->assertDatabaseCount('position_user', 1);
    }

    public function test_changing_action_resets_only_pending_request_and_template_edits_do_not_change_copied_approvals(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $department = Department::create(['name' => 'Подразделение']);
        Sanctum::actingAs($admin);
        $this->putJson('/api/admin/personnel/approval-routes/dismissal', ['approver_user_ids' => [$first->id]])->assertCreated();
        $this->putJson('/api/admin/personnel/approval-routes/maternity_leave', ['approver_user_ids' => [$second->id]])->assertCreated();
        $payload = ['employee_name' => 'Сотрудник', 'action_type' => 'dismissal', 'department_id' => $department->id];
        $id = $this->postJson('/api/admin/personnel/requests', $payload)->assertCreated()->json('request.id');
        $this->putJson('/api/admin/personnel/approval-routes/dismissal', ['approver_user_ids' => [$second->id]])->assertCreated();
        $this->assertDatabaseHas('personnel_request_approvals', ['personnel_request_id' => $id, 'user_id' => $first->id]);
        $payload['action_type'] = 'maternity_leave';
        $this->postJson("/api/admin/personnel/requests/{$id}/update", $payload)->assertOk()->assertJsonPath('request.approvals.0.user_id', $second->id);
        $this->assertDatabaseMissing('personnel_request_approvals', ['personnel_request_id' => $id, 'user_id' => $first->id]);
        PersonnelRequest::findOrFail($id)->approvals()->update(['decision' => 'approved', 'decided_at' => now()]);
        $payload['action_type'] = 'dismissal';
        $this->postJson("/api/admin/personnel/requests/{$id}/update", $payload)->assertUnprocessable();
        $this->assertDatabaseHas('personnel_requests', ['id' => $id, 'action_type' => 'maternity_leave']);
    }

    public function test_legacy_department_templates_remain_intact_and_are_not_implicitly_reused(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $department = Department::create(['name' => 'Подразделение']);
        $user = User::factory()->create();
        PersonnelApprovalRoute::create(['department_id' => $department->id, 'approver_user_id' => $user->id]);
        $this->getJson('/api/admin/personnel/approval-routes')->assertOk()->assertExactJson([]);
        $this->putJson('/api/admin/personnel/approval-routes/dismissal', ['approver_user_ids' => [$user->id]])->assertCreated();
        $this->assertDatabaseHas('personnel_approval_routes', ['department_id' => $department->id, 'approver_user_id' => $user->id]);
    }
}
