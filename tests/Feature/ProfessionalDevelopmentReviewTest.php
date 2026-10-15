<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\ApplicationStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfessionalDevelopmentReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_development_director_can_save_professional_development(): void
    {
        Storage::fake('documents');
        $this->seed(ApplicationStatusSeeder::class);

        $director = User::factory()->create([
            'role' => 'professional_development_director',
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

        $this->post("/api/professional-development/applications/{$application->id}/professional-development", [
            'professional_development' => 'Курсы повышения квалификации подтверждены.',
            'documents' => [
                UploadedFile::fake()->create('certificate.pdf', 120, 'application/pdf'),
            ],
        ])
            ->assertOk()
            ->assertJsonPath('application.pps_profile.professional_development', 'Курсы повышения квалификации подтверждены.')
            ->assertJsonPath('application.pps_profile.professional_development_documents.0.name', 'certificate.pdf');

        $this->assertDatabaseHas('application_pps_profiles', [
            'application_id' => $application->id,
            'professional_development' => 'Курсы повышения квалификации подтверждены.',
        ]);
        $this->assertDatabaseHas('application_pps_profile_documents', [
            'category' => 'professional_development',
            'original_name' => 'certificate.pdf',
        ]);
    }
}
