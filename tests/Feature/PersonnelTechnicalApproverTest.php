<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PersonnelRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonnelTechnicalApproverTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_data_includes_technical_email_users_but_not_admins(): void
    {
        $manual = User::factory()->create(['email' => 'manual-candidate-example@hr-ai.invalid']);
        $legacy = User::factory()->create(['email' => 'legacy-worker-example@hr-ai.invalid']);
        $regular = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@hr-ai.invalid']);
        $this->getJson('/api/admin/personnel/reference-data')->assertUnauthorized();
        Sanctum::actingAs($manual);
        $this->getJson('/api/admin/personnel/reference-data')->assertForbidden();
        Sanctum::actingAs($admin);
        $users = $this->getJson('/api/admin/personnel/reference-data')->assertOk()->json('users');
        $this->assertEqualsCanonicalizing([$manual->id, $legacy->id, $regular->id], array_column($users, 'id'));
    }

    public function test_admin_can_create_a_request_with_technical_email_approvers_without_granting_global_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manual = User::factory()->create(['email' => 'manual-candidate-example@hr-ai.invalid']);
        $legacy = User::factory()->create(['email' => 'legacy-worker-example@hr-ai.invalid']);
        $unassigned = User::factory()->create(['email' => 'unassigned@hr-ai.invalid']);
        $department = Department::create(['name' => 'Personnel']);
        Sanctum::actingAs($admin);
        $id = $this->postJson('/api/admin/personnel/requests', [
            'employee_name' => 'Employee', 'action_type' => 'dismissal', 'department_id' => $department->id,
            'approver_user_ids' => [$manual->id, $legacy->id],
        ])->assertCreated()->assertJsonCount(2, 'request.approvals')->json('request.id');
        $this->assertSame('user', $manual->fresh()->role);
        Sanctum::actingAs($manual);
        $this->getJson("/api/personnel/requests/{$id}")->assertOk();
        Sanctum::actingAs($unassigned);
        $this->getJson("/api/personnel/requests/{$id}")->assertNotFound();
    }

    public function test_technical_email_approvers_work_in_default_templates_and_existing_requests(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $manual = User::factory()->create(['email' => 'manual-candidate-example@hr-ai.invalid']);
        $legacy = User::factory()->create(['email' => 'legacy-worker-example@hr-ai.invalid']);
        $department = Department::create(['name' => 'Personnel']);
        $this->putJson('/api/admin/personnel/approval-routes/dismissal', ['approver_user_ids' => [$manual->id]])->assertCreated();
        $id = $this->postJson('/api/admin/personnel/requests', [
            'employee_name' => 'Employee', 'action_type' => 'dismissal', 'department_id' => $department->id,
        ])->assertCreated()->assertJsonPath('request.approvals.0.user_id', $manual->id)->json('request.id');
        $this->postJson("/api/admin/personnel/requests/{$id}/approvers", ['user_id' => $legacy->id])->assertCreated();
        $this->assertSame(2, PersonnelRequest::findOrFail($id)->approvals()->count());
        $this->postJson("/api/admin/personnel/requests/{$id}/approvers", ['user_id' => $legacy->id])->assertUnprocessable();
    }
}
