<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\CommissionMember;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\ApplicationStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionVoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_pps_vote_requires_term_for_hire_decision(): void
    {
        $this->seed(ApplicationStatusSeeder::class);

        $commissionMember = User::factory()->create();
        CommissionMember::query()->create([
            'user_id' => $commissionMember->id,
        ]);

        $application = $this->createPpsApplication();

        Sanctum::actingAs($commissionMember);

        $response = $this->postJson("/api/commission/applications/{$application->id}/vote", [
            'decision' => 'hire',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Для ППС укажите срок найма: 1 год или 3 года.');

        $this->assertDatabaseCount('application_commission_votes', 0);
    }

    public function test_pps_majority_vote_sets_three_year_term(): void
    {
        $this->seed(ApplicationStatusSeeder::class);

        $members = User::factory()->count(3)->create();
        foreach ($members as $member) {
            CommissionMember::query()->create([
                'user_id' => $member->id,
            ]);
        }

        $application = $this->createPpsApplication();

        Sanctum::actingAs($members[0]);
        $this->postJson("/api/commission/applications/{$application->id}/vote", [
            'decision' => 'hire',
            'hire_term_years' => 3,
            'comment' => 'Поддерживаю на три года.',
        ])->assertOk();

        Sanctum::actingAs($members[1]);
        $response = $this->postJson("/api/commission/applications/{$application->id}/vote", [
            'decision' => 'hire',
            'hire_term_years' => 3,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('application.hiring_status', 'hired')
            ->assertJsonPath('application.hiring_term_years', 3)
            ->assertJsonPath('application.vote_summary.result', 'approved')
            ->assertJsonPath('application.vote_summary.approved_term_years', 3)
            ->assertJsonPath('application.vote_summary.hire_3_year', 2)
            ->assertJsonPath('application.vote_summary.reject', 0);

        $application->refresh();

        $this->assertSame('hired', $application->hiring_status);
        $this->assertSame(3, $application->hiring_term_years);
        $this->assertSame(
            'completed',
            ApplicationStatus::query()->find($application->status_id)?->code
        );

        $this->assertDatabaseHas('application_commission_votes', [
            'application_id' => $application->id,
            'user_id' => $members[0]->id,
            'decision' => 'hire',
            'hire_term_years' => 3,
        ]);

        $this->assertDatabaseHas('application_commission_votes', [
            'application_id' => $application->id,
            'user_id' => $members[1]->id,
            'decision' => 'hire',
            'hire_term_years' => 3,
        ]);
    }

    public function test_permanent_member_can_vote_only_for_matching_vacancy_type(): void
    {
        $this->seed(ApplicationStatusSeeder::class);

        $commissionMember = User::factory()->create();
        CommissionMember::query()->create([
            'user_id' => $commissionMember->id,
            'is_pps' => true,
            'is_staff' => false,
        ]);

        $application = $this->createStaffApplication();

        Sanctum::actingAs($commissionMember);

        $response = $this->postJson("/api/commission/applications/{$application->id}/vote", [
            'decision' => 'hire',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('message', 'Вы не входите в комиссию для этой заявки.');

        $this->assertDatabaseCount('application_commission_votes', 0);
    }

    public function test_commission_member_does_not_receive_other_members_vote_details(): void
    {
        $this->seed(ApplicationStatusSeeder::class);

        $members = User::factory()->count(3)->create();
        foreach ($members as $member) {
            CommissionMember::query()->create([
                'user_id' => $member->id,
            ]);
        }

        $application = $this->createPpsApplication();

        Sanctum::actingAs($members[0]);
        $this->postJson("/api/commission/applications/{$application->id}/vote", [
            'decision' => 'hire',
            'hire_term_years' => 1,
            'comment' => 'Комментарий виден только админу и автору как свой голос.',
        ])->assertOk();

        Sanctum::actingAs($members[1]);
        $response = $this->getJson("/api/commission/applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('vote_summary.voted', 1);

        $payload = $response->json();

        $this->assertArrayNotHasKey('vote_details', $payload);
        $this->assertArrayNotHasKey('commission_votes', $payload);
    }

    private function createPpsApplication(): Application
    {
        return $this->createHiringApplication('pps', 'Ассистент');
    }

    private function createStaffApplication(): Application
    {
        return $this->createHiringApplication('staff', 'HR специалист');
    }

    private function createHiringApplication(string $type, string $title): Application
    {
        $candidate = User::factory()->create();

        $vacancy = Vacancy::query()->create([
            'title' => $title,
            'description' => 'Конкурс на должность.',
            'type' => $type,
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
}
