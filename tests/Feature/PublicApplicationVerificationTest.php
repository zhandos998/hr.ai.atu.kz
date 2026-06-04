<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationPpsProfile;
use App\Models\ApplicationStatus;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\ApplicationStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicApplicationVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_application_verification_page_can_be_opened(): void
    {
        $this->seed(ApplicationStatusSeeder::class);

        $candidate = User::factory()->create([
            'name' => 'Иванов Иван Иванович',
            'email' => 'candidate@example.test',
            'phone' => '+77770000000',
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
            'documents_status' => 'accepted',
            'compliance_status' => 'clear',
            'hiring_status' => 'voting',
        ]);

        ApplicationPpsProfile::query()->create([
            'application_id' => $application->id,
            'desired_position' => 'Ассистент',
            'faculty_name' => 'Факультет тестирования',
            'department_name' => 'Кафедра тестирования',
            'academic_conclusion' => 'Рекомендован к дальнейшему рассмотрению.',
            'magistracy' => 'Магистратура по профильной специальности.',
            'scientific_works' => 'Список научных трудов представлен.',
        ]);

        $url = URL::signedRoute('applications.verify', [
            'application' => $application->id,
        ]);

        $this->assertStringNotContainsString('document=', $url);

        $this->get($url)
            ->assertOk()
            ->assertSee('Проверка заявки')
            ->assertSee('Иванов Иван Иванович')
            ->assertSee('Магистратура по профильной специальности.')
            ->assertSee('Список научных трудов представлен.')
            ->assertSee('Рекомендован к дальнейшему рассмотрению.')
            ->assertDontSee('candidate@example.test')
            ->assertDontSee('+77770000000')
            ->assertDontSee('Статусы заявки')
            ->assertDontSee('Документы');
    }

    public function test_application_verification_page_requires_valid_signature(): void
    {
        $this->seed(ApplicationStatusSeeder::class);

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
        ]);

        $this->get("/verify/applications/{$application->id}?document=academic")
            ->assertForbidden()
            ->assertSee('Ссылка недействительна')
            ->assertDontSee('Invalid signature');
    }

    public function test_missing_signed_application_uses_custom_not_found_page(): void
    {
        $url = URL::signedRoute('applications.verify', [
            'application' => 999,
        ]);

        $this->get($url)
            ->assertNotFound()
            ->assertSee('Страница не найдена');
    }
}
