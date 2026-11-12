<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CommissionMember;
use App\Models\Competition;
use App\Models\PpsFacultyCommissionMember;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\ApplicationStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApplicationCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ApplicationStatusSeeder::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($this->admin);
    }

    private function application(string $type = 'pps', string $faculty = 'Faculty One'): Application
    {
        $application = Application::create([
            'user_id' => User::factory()->create(['name' => 'Original Candidate'])->id,
            'vacancy_id' => Vacancy::create(['type' => $type, 'title' => 'Test Job', 'description' => 'Test'])->id,
            'resume_status' => 'accepted', 'documents_status' => 'accepted',
            'compliance_status' => 'clear', 'hiring_status' => 'voting',
        ]);
        if ($type === 'pps') {
            $application->ppsProfile()->create(['full_name' => 'Original Candidate', 'faculty_name' => $faculty]);
        }

        return $application;
    }

    private function globalMember(string $type): User
    {
        $user = User::factory()->create();
        CommissionMember::create([
            'user_id' => $user->id, 'is_pps' => $type === 'pps',
            'is_staff' => $type === 'staff', 'is_uvp' => $type === 'uvp',
        ]);

        return $user;
    }

    public function test_admin_list_and_detail_include_global_votes_for_each_category(): void
    {
        foreach (Vacancy::TYPES as $type) {
            $application = $this->application($type);
            $member = $this->globalMember($type);
            Sanctum::actingAs($member);
            $this->postJson('/api/commission/applications/'.$application->id.'/vote', [
                'decision' => 'hire', 'hire_term_years' => $type === 'pps' ? 3 : null, 'comment' => 'Approved by global member',
            ])->assertOk()->assertJsonPath('application.vote_summary.voted', 1);

            Sanctum::actingAs($this->admin);
            $this->getJson('/api/admin/applications/'.$application->id)->assertOk()
                ->assertJsonPath('vote_summary.total_members', 1)->assertJsonPath('vote_summary.voted', 1)
                ->assertJsonPath('vote_details.0.user_id', $member->id)
                ->assertJsonPath('vote_details.0.comment', 'Approved by global member');
            $this->getJson('/api/admin/applications?type='.$type)->assertOk()
                ->assertJsonPath('data.0.vote_summary.total_members', 1)->assertJsonPath('data.0.vote_summary.voted', 1)
                ->assertJsonMissingPath('data.0.vote_details')->assertJsonMissingPath('data.0.commission_votes');
        }
    }

    public function test_faculty_member_is_counted_once_and_unrelated_members_and_old_votes_are_excluded(): void
    {
        $application = $this->application();
        $member = $this->globalMember('pps');
        $application->vacancy->commissionMembers()->attach($member);
        PpsFacultyCommissionMember::create(['faculty_name' => 'Faculty One', 'user_id' => $member->id]);
        $facultyOnly = User::factory()->create();
        PpsFacultyCommissionMember::create(['faculty_name' => 'Faculty One', 'user_id' => $facultyOnly->id]);
        $otherFaculty = User::factory()->create();
        PpsFacultyCommissionMember::create(['faculty_name' => 'Faculty Two', 'user_id' => $otherFaculty->id]);
        $unrelated = $this->globalMember('uvp');
        foreach ([$member, $facultyOnly, $otherFaculty, $unrelated] as $user) {
            $application->commissionVotes()->create(['user_id' => $user->id, 'decision' => 'reject']);
        }
        $detail = $this->getJson('/api/admin/applications/'.$application->id)->assertOk()
            ->assertJsonPath('vote_summary.total_members', 2)->assertJsonPath('vote_summary.voted', 2);
        $this->assertEqualsCanonicalizing([$member->id, $facultyOnly->id], array_column($detail->json('vote_details'), 'user_id'));
        $this->getJson('/api/admin/applications?type=pps')->assertOk()
            ->assertJsonPath('data.0.vote_summary.total_members', 2)->assertJsonPath('data.0.vote_summary.voted', 2);
        Sanctum::actingAs($facultyOnly);
        $this->getJson('/api/commission/applications/'.$application->id)->assertOk()
            ->assertJsonPath('vote_summary.total_members', 2)->assertJsonPath('vote_summary.voted', 2)
            ->assertJsonMissingPath('vote_details')->assertJsonMissingPath('commission_votes');
    }

    public function test_member_counts_are_application_scoped_in_a_shared_vacancy_and_competition(): void
    {
        $first = $this->application('pps', 'Faculty One');
        $second = $this->application('pps', 'Faculty Two');
        $competition = Competition::create(['type' => 'pps', 'number' => 1, 'title' => 'Test Competition']);
        $first->update(['competition_id' => $competition->id]);
        $second->update(['vacancy_id' => $first->vacancy_id, 'competition_id' => $competition->id]);
        PpsFacultyCommissionMember::create(['faculty_name' => 'Faculty One', 'user_id' => User::factory()->create()->id]);
        foreach (User::factory()->count(2)->create() as $member) {
            PpsFacultyCommissionMember::create(['faculty_name' => 'Faculty Two', 'user_id' => $member->id]);
        }
        $response = $this->getJson('/api/admin/applications?competition_id='.$competition->id)->assertOk();
        $byId = collect($response->json('data'))->keyBy('id');
        $this->assertSame(1, $byId[$first->id]['vote_summary']['total_members']);
        $this->assertSame(2, $byId[$second->id]['vote_summary']['total_members']);
    }

    public function test_commission_counts_remain_bounded_for_many_faculties_and_empty_lists(): void
    {
        $this->getJson('/api/admin/applications?type=all')->assertOk()->assertJsonCount(0, 'data');
        for ($i = 0; $i < 25; $i++) {
            $application = $this->application('pps', 'Faculty '.$i);
            PpsFacultyCommissionMember::create(['faculty_name' => 'Faculty '.$i, 'user_id' => User::factory()->create()->id]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->getJson('/api/admin/applications?type=pps&per_page=25')->assertOk()
                ->assertJsonCount(25, 'data')->assertJsonPath('data.0.vote_summary.total_members', 1);
            $this->assertLessThan(30, count(DB::getQueryLog()));
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_missing_vacancy_does_not_fall_back_to_all_global_commission_types(): void
    {
        $application = $this->application();
        $member = $this->globalMember('pps');
        $application->vacancy->commissionMembers()->attach($member);
        $application->update(['vacancy_id' => null]);
        $this->getJson('/api/admin/applications/'.$application->id)->assertOk()
            ->assertJsonPath('vote_summary.total_members', 0);
        $this->getJson('/api/admin/applications?type=all')->assertOk()
            ->assertJsonPath('data.0.vote_summary.total_members', 0);
    }

    public function test_pps_name_edit_updates_candidate_lists_search_and_competition_without_changing_other_users(): void
    {
        $application = $this->application();
        $other = $this->application();
        $userBefore = $application->user->getRawOriginal();
        $competition = Competition::create(['type' => 'pps', 'number' => 1, 'title' => 'Test Competition']);
        $application->update(['competition_id' => $competition->id]);
        $this->postJson('/api/admin/applications/'.$application->id.'/pps-profile', ['full_name' => '  Updated Candidate  '])
            ->assertOk()->assertJsonPath('application.user.name', 'Updated Candidate')
            ->assertJsonPath('application.pps_profile.full_name', 'Updated Candidate');
        $this->getJson('/api/admin/applications?type=pps&q=Updated')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.user.name', 'Updated Candidate');
        $this->getJson('/api/admin/applications?competition_id='.$competition->id)->assertOk()->assertJsonPath('data.0.user.name', 'Updated Candidate');
        $this->assertSame('Original Candidate', $other->user->fresh()->name);
        foreach (['email', 'phone', 'role', 'password'] as $field) {
            $this->assertSame($userBefore[$field] ?? null, $application->user->fresh()->getRawOriginal($field));
        }
    }

    public function test_partial_or_empty_pps_profile_update_does_not_erase_candidate_name(): void
    {
        $application = $this->application();
        foreach ([['basic_education' => 'Education'], ['full_name' => ''], ['full_name' => null], ['full_name' => '   ']] as $payload) {
            $this->postJson('/api/admin/applications/'.$application->id.'/pps-profile', $payload)
                ->assertOk()->assertJsonPath('application.user.name', 'Original Candidate');
        }
        $this->postJson('/api/admin/applications/'.$application->id.'/pps-profile', ['full_name' => str_repeat('a', 256)])->assertUnprocessable();
        $this->assertSame('Original Candidate', $application->user->fresh()->name);
    }
}
