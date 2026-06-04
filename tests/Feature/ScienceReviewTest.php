<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationPpsProfile;
use App\Models\ApplicationStatus;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\ApplicationStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScienceReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_science_director_can_save_science_conclusion(): void
    {
        $this->seed(ApplicationStatusSeeder::class);

        $director = User::factory()->create([
            'role' => 'science_director',
        ]);

        $candidate = User::factory()->create();
        $vacancy = Vacancy::query()->create([
            'title' => 'Ассистент',
            'description' => 'Конкурс на должность ассистента.',
            'type' => 'pps',
        ]);

        $application = Application::query()->create([
            'user_id' => $candidate->id,
            'vacancy_id' => $vacancy->id,
            'status_id' => ApplicationStatus::query()->where('code', 'resume_accepted')->value('id'),
            'resume_status' => 'accepted',
            'documents_status' => 'awaiting_upload',
            'compliance_status' => 'not_started',
            'hiring_status' => 'not_started',
        ]);

        Sanctum::actingAs($director);

        $this->postJson("/api/science/applications/{$application->id}/scientific-works", [
            'scientific_works' => 'Список публикаций представлен.',
            'science_conclusion' => 'Научные труды соответствуют требованиям.',
        ])
            ->assertOk()
            ->assertJsonPath('application.pps_profile.science_conclusion', 'Научные труды соответствуют требованиям.');

        $this->assertDatabaseHas('application_pps_profiles', [
            'application_id' => $application->id,
            'science_conclusion' => 'Научные труды соответствуют требованиям.',
        ]);
    }

    public function test_science_director_can_generate_science_conclusion_pdf(): void
    {
        $this->seed(ApplicationStatusSeeder::class);

        $director = User::factory()->create([
            'role' => 'science_director',
        ]);

        $candidate = User::factory()->create([
            'name' => 'Иванов Иван Иванович',
        ]);
        $vacancy = Vacancy::query()->create([
            'title' => 'Ассистент',
            'description' => 'Конкурс на должность ассистента.',
            'type' => 'pps',
        ]);

        $application = Application::query()->create([
            'user_id' => $candidate->id,
            'vacancy_id' => $vacancy->id,
            'status_id' => ApplicationStatus::query()->where('code', 'resume_accepted')->value('id'),
            'resume_status' => 'accepted',
            'documents_status' => 'awaiting_upload',
            'compliance_status' => 'not_started',
            'hiring_status' => 'not_started',
        ]);

        Sanctum::actingAs($director);

        $this->postJson("/api/science/applications/{$application->id}/scientific-works", [
            'scientific_works' => 'Список публикаций представлен.',
            'science_conclusion' => 'Научные труды соответствуют требованиям.',
        ])->assertOk();

        $response = $this->get("/api/science/applications/{$application->id}/science-response-pdf");

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_admin_can_generate_science_conclusion_pdf(): void
    {
        $this->seed(ApplicationStatusSeeder::class);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $candidate = User::factory()->create([
            'name' => 'Иванов Иван Иванович',
        ]);
        $vacancy = Vacancy::query()->create([
            'title' => 'Ассистент',
            'description' => 'Конкурс на должность ассистента.',
            'type' => 'pps',
        ]);

        $application = Application::query()->create([
            'user_id' => $candidate->id,
            'vacancy_id' => $vacancy->id,
            'status_id' => ApplicationStatus::query()->where('code', 'resume_accepted')->value('id'),
            'resume_status' => 'accepted',
            'documents_status' => 'awaiting_upload',
            'compliance_status' => 'not_started',
            'hiring_status' => 'not_started',
        ]);

        ApplicationPpsProfile::query()->create([
            'application_id' => $application->id,
            'science_conclusion' => 'Научные труды соответствуют требованиям.',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->get("/api/admin/applications/{$application->id}/science-response-pdf");

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
