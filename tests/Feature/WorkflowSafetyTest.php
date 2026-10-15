<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Department;
use App\Models\PersonnelRequest;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_personnel_request_cannot_be_rewritten_or_have_documents_or_approvers_changed(): void
    {
        Storage::fake('documents');
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::create(['name' => 'HR']);
        $item = PersonnelRequest::create(['employee_name' => 'Original', 'action_type' => 'hire', 'salary' => 1000, 'work_experience' => '1', 'department_id' => $department->id, 'status' => 'approved', 'created_by' => $admin->id]);
        $approval = $item->approvals()->create(['user_id' => User::factory()->create()->id, 'approval_order' => 1, 'decision' => 'approved', 'decided_at' => now()]);
        $document = $item->documents()->create(['original_name' => 'a.pdf', 'path' => 'personnel-requests/a.pdf', 'mime_type' => 'application/pdf', 'size' => 1]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/personnel/requests/{$item->id}/update", ['employee_name' => 'Changed', 'action_type' => 'dismissal', 'department_id' => $department->id])->assertUnprocessable();
        $this->postJson("/api/admin/personnel/requests/{$item->id}/documents", ['documents' => [UploadedFile::fake()->create('b.pdf', 1)]])->assertUnprocessable();
        $this->deleteJson("/api/admin/personnel/requests/{$item->id}/documents/{$document->id}")->assertUnprocessable();
        $this->postJson("/api/admin/personnel/requests/{$item->id}/approvers", ['user_id' => $admin->id])->assertUnprocessable();
        $this->deleteJson("/api/admin/personnel/requests/{$item->id}/approvers/{$approval->id}")->assertUnprocessable();
        $this->assertSame('Original', $item->fresh()->employee_name);
        $this->assertSame('approved', $item->fresh()->status);
        $this->assertSame('approved', $approval->fresh()->decision);
    }

    public function test_pending_personnel_request_is_locked_after_the_first_approval(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::create(['name' => 'HR']);
        $item = PersonnelRequest::create(['employee_name' => 'Original', 'action_type' => 'dismissal', 'department_id' => $department->id, 'status' => 'pending', 'created_by' => $admin->id]);
        foreach (['approved', 'pending'] as $decision) {
            $item->approvals()->create(['user_id' => User::factory()->create()->id, 'approval_order' => 1, 'decision' => $decision]);
        }
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/personnel/requests/{$item->id}/update", ['employee_name' => 'Changed', 'action_type' => 'dismissal', 'department_id' => $department->id])->assertUnprocessable();
        $this->assertSame('Original', $item->fresh()->employee_name);
        $this->assertSame('pending', $item->fresh()->status);
    }

    public function test_administrator_cannot_be_added_to_personnel_approval(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = PersonnelRequest::create(['employee_name' => 'Test', 'action_type' => 'dismissal', 'department_id' => Department::create(['name' => 'HR'])->id, 'status' => 'pending', 'created_by' => $admin->id]);
        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/personnel/requests/{$item->id}/approvers", ['user_id' => $admin->id])->assertUnprocessable();
        $this->assertDatabaseCount('personnel_request_approvals', 0);
    }

    public function test_vacancy_type_cannot_change_after_applications_exist(): void
    {
        $vacancy = Vacancy::create(['title' => 'Staff', 'description' => 'Test', 'type' => 'staff']);
        Application::create(['vacancy_id' => $vacancy->id, 'user_id' => User::factory()->create()->id]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson("/api/admin/vacancies/{$vacancy->id}", ['type' => 'pps'])->assertUnprocessable();
        $this->assertSame('staff', $vacancy->fresh()->type);
    }

    public function test_guest_vacancy_response_excludes_commission_contacts(): void
    {
        $vacancy = Vacancy::create(['title' => 'Test', 'description' => 'Test', 'type' => 'staff']);
        $vacancy->commissionMembers()->attach(User::factory()->create());
        $this->getJson("/api/vacancies/{$vacancy->id}")->assertOk()->assertJsonMissingPath('commission_members');
        $this->getJson('/api/vacancies')->assertOk()->assertJsonMissingPath('0.commission_members');
    }

    public function test_lawyer_can_complete_pps_compliance_after_saving_profile_fields(): void
    {
        $this->seed(\Database\Seeders\ApplicationStatusSeeder::class);
        $application = Application::create([
            'user_id' => User::factory()->create()->id,
            'vacancy_id' => Vacancy::create(['title' => 'Assistant', 'description' => 'Test', 'type' => 'pps'])->id,
            'resume_status' => 'accepted', 'documents_status' => 'accepted',
            'compliance_status' => 'not_started', 'hiring_status' => 'not_started',
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'lawyer']));
        $this->postJson("/api/compliance/applications/{$application->id}/department-review", [
            'anti_corruption_survey_results' => 'Completed', 'disciplinary_actions_info' => 'None',
        ])->assertOk();
        $this->putJson("/api/lawyer/applications/{$application->id}/corruption-status", ['status_code' => 'clear'])
            ->assertOk()->assertJsonPath('application.compliance_status', 'clear');
        $this->assertSame('Completed', $application->fresh()->ppsProfile->anti_corruption_survey_results);
    }
}
