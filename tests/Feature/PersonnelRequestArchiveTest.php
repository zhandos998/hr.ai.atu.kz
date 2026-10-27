<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PersonnelRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonnelRequestArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function item(): PersonnelRequest
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $item = PersonnelRequest::create([
            'employee_name' => 'Test Employee', 'action_type' => 'dismissal',
            'department_id' => Department::create(['name' => 'HR'])->id,
            'created_by' => $admin->id, 'status' => 'pending',
        ]);
        $item->approvals()->create([
            'user_id' => User::factory()->create()->id, 'approval_order' => 1, 'decision' => 'pending',
        ]);

        return $item;
    }

    public function test_archive_and_restore_preserve_decisions_and_documents_and_are_idempotent(): void
    {
        $item = $this->item();
        $item->approvals()->update(['decision' => 'approved', 'comment' => 'Signed', 'decided_at' => now()]);
        $item->refreshStatus();
        $item->documents()->create(['original_name' => 'contract.pdf', 'path' => 'test.pdf']);
        $this->postJson("/api/admin/personnel/requests/{$item->id}/archive")->assertOk()->assertJsonPath('request.status', 'approved');
        $archivedAt = $item->fresh()->archived_at;
        $this->assertNotNull($archivedAt);
        $this->assertSame($item->created_by, $item->fresh()->archived_by);
        $this->travel(1)->days();
        $this->postJson("/api/admin/personnel/requests/{$item->id}/archive")->assertOk();
        $this->assertEquals($archivedAt, $item->fresh()->archived_at);
        $this->getJson('/api/personnel/requests')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/personnel/requests?archived=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/personnel/requests/{$item->id}")->assertOk()->assertJsonPath('approvals.0.comment', 'Signed')->assertJsonCount(1, 'documents');
        $this->postJson("/api/admin/personnel/requests/{$item->id}/restore")->assertOk()->assertJsonPath('request.archived_at', null)->assertJsonPath('request.archived_by', null);
        $this->getJson('/api/personnel/requests')->assertOk()->assertJsonCount(1, 'data');
        $this->assertDatabaseCount('personnel_request_approvals', 1);
        $this->assertDatabaseCount('personnel_request_documents', 1);
    }

    public function test_archived_requests_cannot_be_modified_or_signed(): void
    {
        $item = $this->item();
        $approval = $item->approvals()->first();
        $item->update(['archived_at' => now()]);
        $this->postJson("/api/admin/personnel/requests/{$item->id}/update", [])->assertUnprocessable();
        $this->postJson("/api/admin/personnel/requests/{$item->id}/documents", [])->assertUnprocessable();
        $this->deleteJson("/api/admin/personnel/requests/{$item->id}/documents/1")->assertUnprocessable();
        $this->postJson("/api/admin/personnel/requests/{$item->id}/approvers", ['user_id' => User::factory()->create()->id])->assertUnprocessable();
        $this->deleteJson("/api/admin/personnel/requests/{$item->id}/approvers/{$approval->id}")->assertUnprocessable();
        Sanctum::actingAs($approval->user);
        $this->postJson("/api/personnel/requests/{$item->id}/decision", ['decision' => 'approved'])->assertUnprocessable();
        $this->assertSame('pending', $approval->fresh()->decision);
        Sanctum::actingAs($item->creator);
        $this->postJson("/api/admin/personnel/requests/{$item->id}/restore")->assertOk();
        Sanctum::actingAs($approval->user);
        $this->postJson("/api/personnel/requests/{$item->id}/decision", ['decision' => 'approved'])->assertOk()->assertJsonPath('request.status', 'approved');
    }

    public function test_only_admin_can_archive_or_restore_and_reviewers_only_see_assigned_archive(): void
    {
        $item = $this->item();
        $item->update(['archived_at' => now()]);
        Sanctum::actingAs($item->approvals()->first()->user);
        $this->postJson("/api/admin/personnel/requests/{$item->id}/archive")->assertForbidden();
        $this->postJson("/api/admin/personnel/requests/{$item->id}/restore")->assertForbidden();
        $this->getJson('/api/personnel/requests?archived=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.salary');
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/personnel/requests?archived=1')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/personnel/requests/{$item->id}")->assertNotFound();
    }

    public function test_public_search_excludes_archived_processes(): void
    {
        $item = $this->item();
        $item->update(['archived_at' => now()]);
        $this->postJson('/api/personnel/status', ['employee_name' => 'Test'])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_archive_filters_paginate_before_returning_results(): void
    {
        $item = $this->item();
        for ($index = 0; $index < 17; $index++) {
            $item->replicate()->fill(['archived_at' => now(), 'employee_name' => "Archived {$index}"])->save();
        }
        $this->getJson('/api/personnel/requests?archived=1&page=2&action_type=dismissal&q=Archived')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 17)->assertJsonPath('meta.current_page', 2);
        $this->getJson('/api/personnel/requests')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
    }

    public function test_commission_can_change_after_voting_without_erasing_signed_decisions(): void
    {
        $item = $this->item();
        $signed = $item->approvals()->first();
        $signed->update(['decision' => 'approved', 'comment' => 'Keep signature', 'decided_at' => now()]);
        $item->refreshStatus();
        $newApprover = User::factory()->create();
        $this->postJson("/api/admin/personnel/requests/{$item->id}/approvers", ['user_id' => $newApprover->id])->assertCreated()->assertJsonPath('request.status', 'pending');
        $pending = $item->approvals()->where('user_id', $newApprover->id)->first();
        $this->deleteJson("/api/admin/personnel/requests/{$item->id}/approvers/{$signed->id}")->assertUnprocessable();
        $this->deleteJson("/api/admin/personnel/requests/{$item->id}/approvers/{$pending->id}")->assertOk()->assertJsonPath('request.status', 'approved');
        $this->assertSame('Keep signature', $signed->fresh()->comment);
        $this->assertNotNull($signed->fresh()->decided_at);
        Sanctum::actingAs($newApprover);
        $this->getJson("/api/personnel/requests/{$item->id}")->assertNotFound();
    }

    public function test_commission_rejects_duplicate_ineligible_and_last_member_removal(): void
    {
        $item = $this->item();
        $approval = $item->approvals()->first();
        $endpoint = "/api/admin/personnel/requests/{$item->id}/approvers";
        $this->deleteJson("{$endpoint}/{$approval->id}")->assertUnprocessable();
        $this->postJson($endpoint, ['user_id' => $approval->user_id])->assertUnprocessable();
        $this->postJson($endpoint, ['user_id' => User::factory()->create(['role' => 'admin'])->id])->assertUnprocessable();
        $this->postJson($endpoint, ['user_id' => User::factory()->create(['email' => 'manual-candidate-test@hr-ai.invalid'])->id])->assertUnprocessable();
        Sanctum::actingAs($approval->user);
        $this->postJson($endpoint, ['user_id' => User::factory()->create()->id])->assertForbidden();
        $this->deleteJson("{$endpoint}/{$approval->id}")->assertForbidden();
    }
}
