<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\Competition;
use App\Models\Department;
use App\Models\AcademicUnit;
use App\Models\Position;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\ApplicationStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompetitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ApplicationStatusSeeder::class);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_competitions_have_independent_default_numbers_and_editable_titles(): void
    {
        $first = $this->postJson('/api/admin/competitions', ['type' => 'pps'])->assertCreated()->assertJsonPath('title', 'Конкурс ППС #1')->json('id');
        $this->postJson('/api/admin/competitions', ['type' => 'staff'])->assertCreated()->assertJsonPath('title', 'Конкурс АУП #1');
        $this->postJson('/api/admin/competitions', ['type' => 'pps', 'title' => 'Осенний конкурс'])->assertCreated()->assertJsonPath('number', 2)->assertJsonPath('title', 'Осенний конкурс');
        $this->getJson('/api/admin/competitions/defaults')->assertOk()->assertJsonPath('pps', 'Конкурс ППС #3');
        $this->putJson("/api/admin/competitions/{$first}", ['title' => 'Новое название'])->assertOk()->assertJsonPath('title', 'Новое название');
        $this->putJson("/api/admin/competitions/{$first}", ['title' => 'Новое название', 'type' => 'staff'])->assertUnprocessable();
    }

    public function test_attach_filters_and_detach_keep_the_original_application(): void
    {
        $competition = $this->competition();
        $one = $this->application('pps', 'Первый кандидат');
        $two = $this->application('pps', 'Второй кандидат');
        $staff = $this->application('staff', 'Сотрудник');
        $archived = $this->application('pps', 'Архивный');
        $archived->update(['archived_at' => now()]);

        $this->getJson("/api/admin/competitions/{$competition->id}/candidates")->assertOk()->assertJsonPath('total', 2);
        $this->postJson("/api/admin/competitions/{$competition->id}/applications", ['application_ids' => [$one->id, $two->id]])->assertOk();
        $this->postJson("/api/admin/competitions/{$competition->id}/applications", ['application_ids' => [$one->id]])->assertOk();
        $this->getJson("/api/admin/competitions/{$competition->id}")->assertOk()->assertJsonPath('applications_count', 2);
        $this->getJson("/api/admin/applications?competition_id={$competition->id}&per_page=1")->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data')->assertJsonPath('counts.pps', 2)->assertJsonPath('counts.staff', 0);
        $this->getJson('/api/admin/applications?type=pps')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/admin/applications?type=staff')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $staff->id);
        $this->deleteJson("/api/admin/competitions/{$competition->id}/applications/{$one->id}")->assertOk();
        $this->assertNull($one->fresh()->competition_id);
        $this->assertDatabaseHas('applications', ['id' => $one->id]);
        $this->deleteJson("/api/admin/competitions/{$competition->id}/applications/{$staff->id}")->assertNotFound();
    }

    public function test_cannot_mix_types_archive_members_or_take_members_from_another_competition(): void
    {
        $competition = $this->competition();
        $one = $this->application('pps', 'Valid');
        $wrong = $this->application('staff', 'Wrong type');
        $endpoint = "/api/admin/competitions/{$competition->id}/applications";
        $this->postJson($endpoint, ['application_ids' => [$one->id, $wrong->id]])->assertUnprocessable();
        $this->assertNull($one->fresh()->competition_id);
        $one->update(['archived_at' => now()]);
        $this->postJson($endpoint, ['application_ids' => [$one->id]])->assertUnprocessable();
        $one->update(['archived_at' => null, 'competition_id' => $this->competition()->id]);
        $this->postJson($endpoint, ['application_ids' => [$one->id]])->assertUnprocessable();
        $this->postJson($endpoint, ['application_ids' => [$wrong->id, $wrong->id]])->assertUnprocessable();
    }

    public function test_new_application_can_be_created_in_a_competition_without_changing_legacy_creation(): void
    {
        $competition = $this->competition('staff');
        $department = Department::create(['name' => 'Подразделение']);
        $position = Position::create(['name' => 'Специалист', 'department_id' => $department->id]);
        $payload = ['full_name' => 'Новый сотрудник', 'vacancy_type' => 'staff', 'department_id' => $department->id, 'position_id' => $position->id, 'competition_id' => $competition->id];
        $this->postJson('/api/admin/applications', $payload)->assertCreated()->assertJsonPath('application.competition_id', $competition->id);
        $payload['competition_id'] = $this->competition('pps')->id;
        $this->postJson('/api/admin/applications', $payload)->assertUnprocessable();
        unset($payload['competition_id']);
        $this->postJson('/api/admin/applications', $payload)->assertCreated()->assertJsonPath('application.competition_id', null);
    }

    public function test_competition_archive_is_isolated_and_invalid_filters_do_not_return_everything(): void
    {
        $competition = $this->competition();
        $application = $this->application('pps', 'Archived');
        $application->update(['competition_id' => $competition->id, 'archived_at' => now()]);
        $this->getJson("/api/admin/applications?competition_id={$competition->id}")->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson("/api/admin/applications?competition_id={$competition->id}&archived=1")->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/applications?competition_id=999999')->assertUnprocessable();
        $this->getJson('/api/admin/competitions?type=staff')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/admin/competitions?type=pps')->assertOk()->assertJsonPath('data.0.applications_count', 1)->assertJsonPath('data.0.active_applications_count', 0);
    }

    public function test_new_pps_application_is_linked_to_its_competition(): void
    {
        $competition = $this->competition();
        $faculty = AcademicUnit::create(['name' => 'Факультет', 'kind' => 'faculty']);
        $department = AcademicUnit::create(['name' => 'Кафедра', 'kind' => 'chair', 'parent_id' => $faculty->id]);
        $position = Position::create(['name' => 'Ассистент', 'academic_unit_id' => $department->id]);
        $this->postJson('/api/admin/applications', [
            'full_name' => 'Преподаватель конкурса', 'vacancy_type' => 'pps',
            'faculty_name' => $faculty->name, 'department_name' => $department->name,
            'position_id' => $position->id, 'competition_id' => $competition->id,
        ])->assertCreated()->assertJsonPath('application.competition_id', $competition->id)
            ->assertJsonPath('application.pps_profile.full_name', 'Преподаватель конкурса');
    }

    public function test_only_admin_can_access_competitions(): void
    {
        $competition = $this->competition();
        $application = $this->application('pps', 'Candidate');
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/admin/competitions')->assertForbidden();
        $this->getJson("/api/admin/competitions/{$competition->id}")->assertForbidden();
        $this->getJson("/api/admin/competitions/{$competition->id}/candidates")->assertForbidden();
        $this->postJson('/api/admin/competitions', ['type' => 'pps'])->assertForbidden();
        $this->putJson("/api/admin/competitions/{$competition->id}", ['title' => 'Invalid'])->assertForbidden();
        $this->postJson("/api/admin/competitions/{$competition->id}/applications", ['application_ids' => [$application->id]])->assertForbidden();
        $this->deleteJson("/api/admin/competitions/{$competition->id}/applications/{$application->id}")->assertForbidden();
    }

    private function competition(string $type = 'pps'): Competition
    {
        return Competition::findOrFail($this->postJson('/api/admin/competitions', ['type' => $type])->assertCreated()->json('id'));
    }

    private function application(string $type, string $name): Application
    {
        return Application::create([
            'user_id' => User::factory()->create(['name' => $name])->id,
            'vacancy_id' => Vacancy::create(['title' => 'Должность', 'type' => $type, 'description' => 'Test'])->id,
            'status_id' => ApplicationStatus::where('code', 'pending')->value('id'),
            'resume_status' => 'pending', 'documents_status' => 'awaiting_upload',
            'compliance_status' => 'not_started', 'hiring_status' => 'not_started',
        ]);
    }
}
