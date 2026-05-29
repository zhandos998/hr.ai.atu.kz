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

class ApplicationDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_application_accepts_multiple_diploma_files(): void
    {
        Storage::fake('public');
        $this->seed(ApplicationStatusSeeder::class);

        $user = User::factory()->create();
        $application = $this->createDocumentApplication($user, 'staff');

        Sanctum::actingAs($user);

        $this->post('/api/applications/' . $application->id . '/upload-docs', [
            'diploma' => [
                UploadedFile::fake()->create('diploma.pdf', 128, 'application/pdf'),
                UploadedFile::fake()->create('certificate.pdf', 128, 'application/pdf'),
            ],
        ], [
            'Accept' => 'application/json',
        ])->assertOk();

        $this->assertDatabaseHas('application_documents', [
            'application_id' => $application->id,
            'type' => 'diploma',
        ]);
        $this->assertDatabaseHas('application_documents', [
            'application_id' => $application->id,
            'type' => 'diploma_2',
        ]);
        Storage::disk('public')->assertExists("applications/{$application->id}/diploma.pdf");
        Storage::disk('public')->assertExists("applications/{$application->id}/diploma_2.pdf");
    }

    public function test_pps_application_accepts_multiple_recommendation_letters(): void
    {
        Storage::fake('public');
        $this->seed(ApplicationStatusSeeder::class);

        $user = User::factory()->create();
        $application = $this->createDocumentApplication($user, 'pps');

        Sanctum::actingAs($user);

        $this->post('/api/applications/' . $application->id . '/upload-docs', [
            'recommendation_letter' => [
                UploadedFile::fake()->create('letter-one.pdf', 128, 'application/pdf'),
                UploadedFile::fake()->create('letter-two.pdf', 128, 'application/pdf'),
            ],
        ], [
            'Accept' => 'application/json',
        ])->assertOk();

        $this->assertDatabaseHas('application_documents', [
            'application_id' => $application->id,
            'type' => 'recommendation_letter',
        ]);
        $this->assertDatabaseHas('application_documents', [
            'application_id' => $application->id,
            'type' => 'recommendation_letter_2',
        ]);
        Storage::disk('public')->assertExists("applications/{$application->id}/recommendation_letter.pdf");
        Storage::disk('public')->assertExists("applications/{$application->id}/recommendation_letter_2.pdf");
    }

    private function createDocumentApplication(User $user, string $vacancyType): Application
    {
        $vacancy = Vacancy::query()->create([
            'title' => $vacancyType === 'pps' ? 'Ассистент' : 'Специалист',
            'description' => 'Тестовая вакансия.',
            'type' => $vacancyType,
        ]);

        return Application::query()->create([
            'user_id' => $user->id,
            'vacancy_id' => $vacancy->id,
            'status_id' => ApplicationStatus::query()->where('code', 'resume_accepted')->value('id'),
            'resume_status' => 'accepted',
            'documents_status' => 'awaiting_upload',
            'compliance_status' => 'not_started',
            'hiring_status' => 'not_started',
        ]);
    }
}
