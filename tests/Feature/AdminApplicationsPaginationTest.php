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

class AdminApplicationsPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_query_count_is_bounded_and_does_not_include_detail_history(): void
    {
        $this->seed(ApplicationStatusSeeder::class);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        for ($i = 0; $i < 25; $i++) {
            $application = $this->createAdminListApplication('pps', 'Candidate '.$i);
            $application->vacancy->commissionMembers()->attach(User::factory()->create());
        }
        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();
        $this->getJson('/api/admin/applications?type=pps&per_page=25')->assertOk()
            ->assertJsonCount(25, 'data')->assertJsonMissingPath('data.0.stage_logs')
            ->assertJsonMissingPath('data.0.documents')
            ->assertJsonPath('data.0.vote_summary.total_members', 1);
        $count = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();
        $this->assertLessThan(30, $count);
    }

    public function test_admin_applications_index_uses_server_pagination_and_type_counts(): void
    {
        $this->seed(ApplicationStatusSeeder::class);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->createAdminListApplication('pps', 'PPS Candidate 1');
        $this->createAdminListApplication('pps', 'PPS Candidate 2');
        $this->createAdminListApplication('pps', 'PPS Candidate 3');
        $this->createAdminListApplication('staff', 'Staff Candidate 1');
        $this->createAdminListApplication('staff', 'Staff Candidate 2');

        $this->getJson('/api/admin/applications?type=pps&page=2&per_page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('counts.pps', 3)
            ->assertJsonPath('counts.staff', 2);
    }

    private function createAdminListApplication(string $type, string $candidateName): Application
    {
        $candidate = User::factory()->create(['name' => $candidateName]);
        $vacancy = Vacancy::query()->create([
            'title' => "{$type} vacancy",
            'description' => "{$type} vacancy description",
            'type' => $type,
        ]);

        $application = Application::query()->create([
            'user_id' => $candidate->id,
            'vacancy_id' => $vacancy->id,
            'status_id' => ApplicationStatus::query()->where('code', 'pending')->value('id'),
            'resume_status' => 'pending',
            'documents_status' => 'not_requested',
            'compliance_status' => 'not_started',
            'hiring_status' => 'not_started',
        ]);

        if ($type === 'pps') {
            ApplicationPpsProfile::query()->create([
                'application_id' => $application->id,
                'full_name' => $candidateName,
                'desired_position' => 'Assistant',
                'faculty_name' => 'Faculty',
                'department_name' => 'Department',
            ]);
        }

        return $application;
    }
}
