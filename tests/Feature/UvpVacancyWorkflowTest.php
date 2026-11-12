<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Application;
use App\Models\CommissionMember;
use App\Models\User;
use App\Models\UvpPosition;
use App\Models\Vacancy;
use Database\Seeders\ApplicationStatusSeeder;
use Database\Seeders\UvpPositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UvpVacancyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AcademicUnit $faculty;

    private AcademicUnit $chair;

    private UvpPosition $position;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ApplicationStatusSeeder::class, UvpPositionSeeder::class]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($this->admin);
        $this->faculty = AcademicUnit::create(['name' => 'Test Faculty', 'kind' => 'faculty']);
        $this->chair = AcademicUnit::create(['name' => 'Test Chair', 'kind' => 'chair', 'parent_id' => $this->faculty->id]);
        $this->position = UvpPosition::where('name', 'Лаборант')->sole();
    }

    private function payload(array $extra = []): array
    {
        return array_replace(['full_name' => 'UVP Candidate', 'vacancy_type' => 'uvp', 'uvp_faculty_id' => $this->faculty->id, 'uvp_academic_unit_id' => $this->chair->id, 'uvp_position_id' => $this->position->id], $extra);
    }

    private function create(array $extra = []): array
    {
        return $this->postJson('/api/admin/applications', $this->payload($extra))->assertCreated()->json('application');
    }

    public function test_uvp_directory_is_editable_admin_only_unique_and_usable_by_requests(): void
    {
        $this->getJson('/api/admin/uvp-positions')->assertOk()->assertJsonCount(7);
        $id = $this->postJson('/api/admin/uvp-positions', ['name' => '  Техник  '])->assertCreated()->assertJsonPath('name', 'Техник')->json('id');
        $this->postJson('/api/admin/uvp-positions', ['name' => 'Техник'])->assertUnprocessable();
        $this->putJson('/api/admin/uvp-positions/'.$id, ['name' => 'Техник лаборатории'])->assertOk();
        $this->deleteJson('/api/admin/uvp-positions/'.$id)->assertNoContent();
        $this->create();
        $this->deleteJson('/api/admin/uvp-positions/'.$this->position->id)->assertUnprocessable();
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/admin/uvp-positions')->assertForbidden();
        $this->postJson('/api/admin/uvp-positions', ['name' => 'Forbidden'])->assertForbidden();
        $this->putJson('/api/admin/uvp-positions/'.$this->position->id, ['name' => 'Forbidden'])->assertForbidden();
        $this->deleteJson('/api/admin/uvp-positions/'.$this->position->id)->assertForbidden();
    }

    public function test_uvp_creation_is_separate_from_pps_and_staff_and_visible_in_all_lists(): void
    {
        $item = $this->create();
        $this->assertSame('uvp', $item['vacancy']['type']);
        $this->assertSame('Лаборант', $item['vacancy']['uvp_position']['name']);
        $this->assertNull($item['vacancy']['position_id']);
        $this->assertNull($item['pps_profile']);
        $this->assertSame('Test Faculty', $item['uvp_faculty']['name']);
        $this->getJson('/api/admin/applications?type=all')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('counts.uvp', 1)->assertJsonPath('counts.pps', 0)->assertJsonPath('counts.staff', 0);
        $this->getJson('/api/admin/applications?type=uvp&q=Test%20Chair')->assertOk()->assertJsonPath('data.0.id', $item['id']);
        $this->getJson('/api/admin/applications?type=pps')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/applications?type=staff')->assertOk()->assertJsonCount(0, 'data');
        $this->putJson('/api/admin/applications/'.$item['id'].'/archive')->assertOk();
        $this->getJson('/api/admin/applications?type=uvp')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/applications?type=uvp&archived=1')->assertOk()->assertJsonPath('data.0.id', $item['id']);
        $this->putJson('/api/admin/applications/'.$item['id'].'/unarchive')->assertOk();
    }

    public function test_uvp_requires_the_correct_catalog_and_matching_academic_structure(): void
    {
        $other = AcademicUnit::create(['name' => 'Other Faculty', 'kind' => 'faculty']);
        foreach ([['uvp_position_id' => null], ['uvp_position_id' => 99999], ['uvp_faculty_id' => null], ['uvp_faculty_id' => $other->id], ['uvp_academic_unit_id' => $this->faculty->id], ['department_id' => 1]] as $extra) {
            $this->postJson('/api/admin/applications', $this->payload($extra))->assertUnprocessable();
        }
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('vacancies', 0);
    }

    public function test_uvp_details_can_be_changed_without_changing_other_applications(): void
    {
        $one = $this->create();
        $two = $this->create(['full_name' => 'Other Candidate']);
        $job = UvpPosition::where('name', 'Инженер ВКК')->sole();
        $this->putJson('/api/admin/applications/'.$one['id'].'/uvp-details', $this->payload(['uvp_position_id' => $job->id]))->assertOk()->assertJsonPath('application.vacancy.uvp_position.name', 'Инженер ВКК');
        $this->getJson('/api/admin/applications/'.$two['id'])->assertOk()->assertJsonPath('vacancy.uvp_position.name', 'Лаборант');
        $this->deleteJson('/api/admin/academic-units/'.$this->chair->id)->assertUnprocessable();
        $this->assertDatabaseHas('academic_units', ['id' => $this->chair->id]);
    }

    public function test_uvp_competitions_have_independent_titles_and_cannot_mix_types(): void
    {
        $id = $this->postJson('/api/admin/competitions', ['type' => 'uvp'])->assertCreated()->assertJsonPath('title', 'Конкурс УВП #1')->json('id');
        $this->getJson('/api/admin/competitions/defaults')->assertOk()->assertJsonPath('uvp', 'Конкурс УВП #2')->assertJsonPath('pps', 'Конкурс ППС #1');
        $one = $this->create(['competition_id' => $id]);
        $this->getJson('/api/admin/applications?competition_id='.$id)->assertOk()->assertJsonPath('meta.total', 1);
        $pps = $this->postJson('/api/admin/competitions', ['type' => 'pps'])->assertCreated()->json('id');
        $this->postJson('/api/admin/competitions/'.$pps.'/applications', ['application_ids' => [$one['id']]])->assertUnprocessable();
        $this->postJson('/api/admin/applications', $this->payload(['competition_id' => $pps]))->assertUnprocessable();
        $this->getJson('/api/admin/competitions?type=uvp')->assertOk()->assertJsonCount(1, 'data');
        $two = $this->create(['full_name' => 'Attach Candidate']);
        $this->postJson('/api/admin/competitions/'.$id.'/applications', ['application_ids' => [$two['id']]])->assertOk();
        $this->getJson('/api/admin/applications?competition_id='.$id)->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_changing_uvp_job_does_not_copy_manual_commission_access_to_another_vacancy(): void
    {
        $one = $this->create();
        $job = UvpPosition::where('name', 'Инженер')->sole();
        $two = $this->create(['uvp_position_id' => $job->id]);
        $manual = User::factory()->create();
        Vacancy::findOrFail($one['vacancy_id'])->commissionMembers()->attach($manual);
        $this->putJson('/api/admin/applications/'.$one['id'].'/uvp-details', $this->payload(['uvp_position_id' => $job->id]))->assertOk();
        $this->assertFalse(Vacancy::findOrFail($two['vacancy_id'])->commissionMembers()->whereKey($manual->id)->exists());
    }

    public function test_uvp_commission_is_explicitly_assigned_and_votes_without_pps_terms(): void
    {
        $uvp = User::factory()->create();
        $old = User::factory()->create();
        CommissionMember::create(['user_id' => $old->id, 'is_pps' => true, 'is_staff' => true]);
        $this->postJson('/api/admin/commission-members', ['type' => 'uvp', 'user_id' => $uvp->id])->assertCreated()->assertJsonPath('member.is_uvp', true)->assertJsonPath('member.is_pps', false)->assertJsonPath('member.is_staff', false);
        $item = $this->create();
        $this->assertSame([$uvp->id], Vacancy::findOrFail($item['vacancy_id'])->commissionMembers()->pluck('users.id')->all());
        Application::findOrFail($item['id'])->update(['compliance_status' => 'clear', 'hiring_status' => 'voting']);
        Sanctum::actingAs($old);
        $this->getJson('/api/commission/applications?type=uvp')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/commission/applications/'.$item['id'])->assertNotFound();
        Sanctum::actingAs($uvp);
        $this->getJson('/api/commission/applications?type=uvp')->assertOk()->assertJsonPath('counts.uvp', 1)->assertJsonPath('data.0.vacancy.type', 'uvp');
        $this->postJson('/api/commission/applications/'.$item['id'].'/vote', ['decision' => 'hire'])->assertOk();
        $this->assertDatabaseHas('application_commission_votes', ['application_id' => $item['id'], 'user_id' => $uvp->id, 'hire_term_years' => null]);
        Sanctum::actingAs($this->admin);
        $this->deleteJson('/api/admin/commission-members/'.$uvp->id.'?type=uvp')->assertOk();
    }

    public function test_uvp_vacancy_type_is_allowed_but_cannot_change_after_applications_exist(): void
    {
        $this->postJson('/api/admin/vacancies', ['title' => 'UVP', 'description' => 'Description', 'type' => 'uvp', 'uvp_position_id' => $this->position->id])->assertOk();
        $item = $this->create();
        $this->putJson('/api/admin/vacancies/'.$item['vacancy_id'], ['type' => 'staff'])->assertUnprocessable();
        $migration = require database_path('migrations/2026_10_30_140000_add_uvp_vacancies.php');
        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    public function test_candidate_apply_requires_uvp_structure_and_keeps_the_existing_user(): void
    {
        \Illuminate\Support\Facades\Storage::fake('documents');
        $item = $this->create();
        $candidate = User::factory()->create();
        $userCount = User::count();
        Sanctum::actingAs($candidate);
        $file = fn () => \Illuminate\Http\UploadedFile::fake()->create('resume.pdf', 10, 'application/pdf');
        $this->postJson('/api/applications', ['vacancy_id' => $item['vacancy_id'], 'resume' => $file()])->assertUnprocessable();
        $this->postJson('/api/applications', [
            'vacancy_id' => $item['vacancy_id'], 'resume' => $file(),
            'uvp_position_id' => $this->position->id, 'uvp_faculty_id' => $this->faculty->id, 'uvp_academic_unit_id' => $this->chair->id,
        ])->assertCreated()->assertJsonPath('application.user_id', $candidate->id)->assertJsonPath('application.uvp_faculty_id', $this->faculty->id);
        $this->assertDatabaseCount('users', $userCount);
    }

    public function test_uvp_vacancy_creation_requires_and_persists_a_catalog_position(): void
    {
        $payload = ['type' => 'uvp', 'title' => 'Laboratory Vacancy', 'description' => 'Test'];
        foreach ([null, '', 999999] as $id) {
            $this->postJson('/api/admin/vacancies', [...$payload, 'uvp_position_id' => $id])->assertUnprocessable();
        }
        $this->postJson('/api/admin/vacancies', [...$payload, 'uvp_position_id' => $this->position->id, 'position_id' => 1])->assertUnprocessable();
        $this->postJson('/api/admin/vacancies', [...$payload, 'type' => 'staff', 'uvp_position_id' => $this->position->id])->assertUnprocessable();
        $this->assertDatabaseCount('vacancies', 0);
        $id = $this->postJson('/api/admin/vacancies', [...$payload, 'uvp_position_id' => $this->position->id])->assertOk()
            ->assertJsonPath('vacancy.uvp_position_id', $this->position->id)->json('vacancy.id');
        $this->getJson('/api/vacancies/'.$id)->assertOk()->assertJsonPath('uvp_position.name', 'Лаборант');

        \Illuminate\Support\Facades\Storage::fake('documents');
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/applications', [
            'vacancy_id' => $id, 'uvp_position_id' => $this->position->id,
            'uvp_faculty_id' => $this->faculty->id, 'uvp_academic_unit_id' => $this->chair->id,
            'resume' => \Illuminate\Http\UploadedFile::fake()->create('resume.pdf', 10, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('application.vacancy_id', $id);
    }

    public function test_uvp_vacancy_edit_preserves_position_and_can_repair_legacy_empty_position(): void
    {
        $vacancy = Vacancy::create(['type' => 'uvp', 'title' => 'Legacy', 'description' => 'Test']);
        $this->putJson('/api/admin/vacancies/'.$vacancy->id, ['description' => 'Update'])->assertUnprocessable();
        $this->putJson('/api/admin/vacancies/'.$vacancy->id, ['uvp_position_id' => $this->position->id])->assertOk()
            ->assertJsonPath('vacancy.uvp_position_id', $this->position->id);
        $this->putJson('/api/admin/vacancies/'.$vacancy->id, ['description' => 'Updated'])->assertOk()
            ->assertJsonPath('vacancy.uvp_position_id', $this->position->id);
        $this->putJson('/api/admin/vacancies/'.$vacancy->id, ['uvp_position_id' => null])->assertUnprocessable();
        $other = UvpPosition::where('name', 'Инженер')->sole();
        $this->putJson('/api/admin/vacancies/'.$vacancy->id, ['uvp_position_id' => $other->id])->assertOk()->assertJsonPath('vacancy.uvp_position_id', $other->id);
        $this->putJson('/api/admin/vacancies/'.$vacancy->id, ['type' => 'staff'])->assertOk()->assertJsonPath('vacancy.uvp_position_id', null);
        $this->putJson('/api/admin/vacancies/'.$vacancy->id, ['type' => 'uvp'])->assertUnprocessable();
        $this->putJson('/api/admin/vacancies/'.$vacancy->id, ['type' => 'uvp', 'uvp_position_id' => $other->id])->assertOk();
    }
}
