<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationPpsProfile;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\CommissionCandidateUserSeeder;
use Database\Seeders\KshitCommissionSeeder;
use Database\Seeders\PpsCommissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeederPasswordPreservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pps_commission_seeder_does_not_reset_existing_user_password(): void
    {
        $user = User::factory()->create([
            'email' => 'b.esembaeva@atu.edu.kz',
            'password' => Hash::make('8363'),
        ]);

        $this->seed(PpsCommissionSeeder::class);

        $user->refresh();

        $this->assertTrue(Hash::check('8363', $user->password));
        $this->assertFalse(Hash::check('1234', $user->password));
    }

    public function test_commission_candidate_user_seeder_does_not_reset_existing_user_password(): void
    {
        $user = User::factory()->create([
            'email' => 'b.tolekova@atu.edu.kz',
            'password' => Hash::make('5677'),
        ]);

        $this->seed(CommissionCandidateUserSeeder::class);

        $user->refresh();

        $this->assertTrue(Hash::check('5677', $user->password));
        $this->assertFalse(Hash::check('1234', $user->password));
    }

    public function test_kshit_faculty_commission_member_uses_seeded_user_password(): void
    {
        $otherUser = User::factory()->create([
            'email' => 'b.esembaeva@atu.edu.kz',
            'name' => 'Existing User',
            'password' => Hash::make('8363'),
        ]);

        $this->seed(KshitCommissionSeeder::class);

        $user = User::query()->where('email', 'b.pazylkhaiyr@atu.edu.kz')->firstOrFail();
        $otherUser->refresh();

        $this->assertSame('Пазылхайыр Бауыржан', $user->name);
        $this->assertTrue(Hash::check('8111', $user->password));
        $this->assertSame('Existing User', $otherUser->name);
        $this->assertTrue(Hash::check('8363', $otherUser->password));
        $this->assertDatabaseHas('pps_faculty_commission_members', [
            'faculty_name' => 'Другое',
            'user_id' => $user->id,
        ]);
    }

    public function test_kshit_commission_seeder_backfills_existing_kshit_vacancies(): void
    {
        $candidate = User::factory()->create();
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
        $application = Application::query()->create([
            'user_id' => $candidate->id,
            'vacancy_id' => $vacancy->id,
            'resume_status' => 'accepted',
            'documents_status' => 'awaiting_upload',
            'compliance_status' => 'not_started',
            'hiring_status' => 'not_started',
        ]);
        ApplicationPpsProfile::query()->create([
            'application_id' => $application->id,
            'faculty_name' => 'Другое',
            'department_name' => 'КШИТ',
        ]);

        $this->seed(KshitCommissionSeeder::class);

        $user = User::query()->where('email', 'b.pazylkhaiyr@atu.edu.kz')->firstOrFail();

        $this->assertTrue(
            $vacancy->commissionMembers()
                ->where('users.id', $user->id)
                ->exists()
        );
    }
}
