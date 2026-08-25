<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\ApplicationStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminHiringStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_application_can_be_hired_without_term(): void
    {
        $this->seed(ApplicationStatusSeeder::class);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $application = $this->createTestApplication('staff');

        $this->putJson("/api/admin/applications/{$application->id}", [
            'stage' => 'hiring',
            'stage_status' => 'hired',
        ])
            ->assertOk()
            ->assertJsonPath('application.hiring_status', 'hired')
            ->assertJsonPath('application.hiring_term_years', null);
    }

    public function test_staff_application_rejects_hiring_term_status(): void
    {
        $this->seed(ApplicationStatusSeeder::class);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $application = $this->createTestApplication('staff');

        $this->putJson("/api/admin/applications/{$application->id}", [
            'stage' => 'hiring',
            'stage_status' => 'hired_1_year',
        ])->assertUnprocessable();
    }

    public function test_pps_application_rejects_hired_without_term(): void
    {
        $this->seed(ApplicationStatusSeeder::class);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $application = $this->createTestApplication('pps');

        $this->putJson("/api/admin/applications/{$application->id}", [
            'stage' => 'hiring',
            'stage_status' => 'hired',
        ])->assertUnprocessable();
    }

    private function createTestApplication(string $type): Application
    {
        $candidate = User::factory()->create();
        $vacancy = $type === 'staff'
            ? $this->createStaffVacancy()
            : Vacancy::query()->create([
                'title' => 'Ассистент',
                'description' => 'Конкурс на должность Ассистент.',
                'type' => 'pps',
            ]);

        return Application::query()->create([
            'user_id' => $candidate->id,
            'vacancy_id' => $vacancy->id,
            'status_id' => ApplicationStatus::query()->where('code', 'corruption_not_found')->value('id'),
            'resume_status' => 'accepted',
            'documents_status' => 'accepted',
            'compliance_status' => 'clear',
            'hiring_status' => 'not_started',
        ]);
    }

    private function createStaffVacancy(): Vacancy
    {
        $department = Department::query()->create([
            'name' => 'Тестовое подразделение',
            'description' => 'Подразделение',
        ]);
        $position = Position::query()->create([
            'department_id' => $department->id,
            'name' => 'Тестовая должность',
        ]);

        return Vacancy::query()->create([
            'title' => 'Тестовая должность',
            'description' => 'Конкурс на должность Тестовая должность.',
            'type' => 'staff',
            'position_id' => $position->id,
        ]);
    }
}
