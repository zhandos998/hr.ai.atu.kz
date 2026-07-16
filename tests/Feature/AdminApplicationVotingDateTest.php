<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\ApplicationStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApplicationVotingDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_set_application_voting_date(): void
    {
        $this->seed(ApplicationStatusSeeder::class);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $candidate = User::factory()->create();
        $vacancy = Vacancy::query()->create([
            'title' => 'Ассистент',
            'description' => 'Конкурс на должность.',
            'type' => 'pps',
        ]);

        $application = Application::query()->create([
            'user_id' => $candidate->id,
            'vacancy_id' => $vacancy->id,
            'status_id' => ApplicationStatus::query()->where('code', 'corruption_not_found')->value('id'),
            'resume_status' => 'accepted',
            'documents_status' => 'accepted',
            'compliance_status' => 'clear',
            'hiring_status' => 'voting',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/admin/applications/{$application->id}/voting-date", [
            'voting_date' => '2026-06-20',
            'voting_order' => 2,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('application.voting_date', '2026-06-20')
            ->assertJsonPath('application.voting_order', 2);

        $this->assertStringStartsWith(
            '2026-06-20T00:00:00',
            $response->json('application.voting_started_at')
        );

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'voting_date' => '2026-06-20',
            'voting_order' => 2,
        ]);
    }

    public function test_admin_can_clear_application_voting_date(): void
    {
        $this->seed(ApplicationStatusSeeder::class);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $candidate = User::factory()->create();
        $vacancy = Vacancy::query()->create([
            'title' => 'Ассистент',
            'description' => 'Конкурс на должность.',
            'type' => 'pps',
        ]);

        $application = Application::query()->create([
            'user_id' => $candidate->id,
            'vacancy_id' => $vacancy->id,
            'status_id' => ApplicationStatus::query()->where('code', 'corruption_not_found')->value('id'),
            'resume_status' => 'accepted',
            'documents_status' => 'accepted',
            'compliance_status' => 'clear',
            'hiring_status' => 'voting',
            'voting_date' => '2026-06-20',
            'voting_order' => 3,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/admin/applications/{$application->id}/voting-date", [
            'voting_date' => null,
            'voting_order' => null,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('application.voting_date', null)
            ->assertJsonPath('application.voting_order', null)
            ->assertJsonPath('application.voting_started_at', null);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'voting_date' => null,
            'voting_order' => null,
        ]);
    }
}
