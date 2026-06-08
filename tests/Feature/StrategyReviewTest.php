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

class StrategyReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_strategy_director_does_not_clear_individual_plan_nonfulfillment(): void
    {
        $this->seed(ApplicationStatusSeeder::class);

        $director = User::factory()->create([
            'role' => 'strategy_director',
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
        ApplicationPpsProfile::query()->create([
            'application_id' => $application->id,
            'individual_plan_nonfulfillment' => 'Заполнено академическим блоком.',
        ]);

        Sanctum::actingAs($director);

        $this->postJson("/api/strategy/applications/{$application->id}/strategy-review", [
            'final_rating_score' => '95',
            'student_survey_results' => 'Положительные отзывы.',
            'krk' => 'Без замечаний.',
        ])->assertOk();

        $this->assertDatabaseHas('application_pps_profiles', [
            'application_id' => $application->id,
            'individual_plan_nonfulfillment' => 'Заполнено академическим блоком.',
            'final_rating_score' => '95',
            'krk' => 'Без замечаний.',
        ]);
    }
}
