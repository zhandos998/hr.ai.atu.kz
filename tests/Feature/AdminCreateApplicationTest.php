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

class AdminCreateApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_manual_application_without_resume(): void
    {
        $this->seed(ApplicationStatusSeeder::class);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $faculty = Department::query()->create([
            'name' => 'Факультет информационных технологий',
            'description' => 'Факультет',
        ]);

        $department = Department::query()->create([
            'parent_id' => $faculty->id,
            'name' => 'Кафедра «Информационные системы»',
            'description' => 'Кафедра',
        ]);

        $position = Position::query()->create([
            'department_id' => $department->id,
            'name' => 'Ассистент',
        ]);

        $vacancy = Vacancy::query()->create([
            'title' => 'Ассистент',
            'description' => 'Конкурс на должность ассистента.',
            'type' => 'pps',
            'position_id' => $position->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->post('/api/admin/applications', [
            'full_name' => 'Иванов Иван Иванович',
            'vacancy_id' => $vacancy->id,
            'faculty_name' => 'Факультет информационных технологий',
            'department_name' => 'Кафедра «Информационные системы»',
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('application.vacancy_id', $vacancy->id)
            ->assertJsonPath('application.status.code', 'resume_accepted')
            ->assertJsonPath('application.resume_status', 'accepted')
            ->assertJsonPath('application.documents_status', 'awaiting_upload')
            ->assertJsonPath('application.user.name', 'Иванов Иван Иванович')
            ->assertJsonPath('application.pps_profile.faculty_name', 'Факультет информационных технологий')
            ->assertJsonPath('application.pps_profile.department_name', 'Кафедра «Информационные системы»')
            ->assertJsonPath('application.resume_url', null);

        $application = Application::query()->with(['resume', 'user', 'ppsProfile'])->sole();

        $this->assertSame($vacancy->id, $application->vacancy_id);
        $this->assertSame('accepted', $application->resume_status);
        $this->assertSame('awaiting_upload', $application->documents_status);
        $this->assertNull($application->resume);
        $this->assertSame('Иванов Иван Иванович', $application->user->name);
        $this->assertStringEndsWith('@hr-ai.invalid', $application->user->email);
        $this->assertSame('Факультет информационных технологий', $application->ppsProfile?->faculty_name);
        $this->assertSame('Кафедра «Информационные системы»', $application->ppsProfile?->department_name);
    }

    public function test_kshit_pps_application_adds_pazylkhaiyr_to_vacancy_commission(): void
    {
        $this->seed(ApplicationStatusSeeder::class);

        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $commissionMember = User::factory()->create([
            'name' => 'Пазылхайыр Бауыржан',
            'email' => 'b.pazylkhaiyr@atu.edu.kz',
        ]);

        $faculty = Department::query()->create([
            'name' => 'Другое',
            'description' => 'Факультет',
        ]);

        $department = Department::query()->create([
            'parent_id' => $faculty->id,
            'name' => 'КШИТ',
            'description' => 'Кафедра',
        ]);

        $position = Position::query()->create([
            'department_id' => $department->id,
            'name' => 'Ассистент',
        ]);

        $vacancy = Vacancy::query()->create([
            'title' => 'Ассистент',
            'description' => 'Конкурс на должность ассистента.',
            'type' => 'pps',
            'position_id' => $position->id,
        ]);

        Sanctum::actingAs($admin);

        $this->post('/api/admin/applications', [
            'full_name' => 'Кандидат КШИТ',
            'vacancy_id' => $vacancy->id,
            'faculty_name' => 'Другое',
            'department_name' => 'КШИТ',
        ], [
            'Accept' => 'application/json',
        ])->assertCreated();

        $this->assertTrue(
            $vacancy->commissionMembers()
                ->where('users.id', $commissionMember->id)
                ->exists()
        );
    }
}
