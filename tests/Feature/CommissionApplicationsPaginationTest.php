<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\Competition;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\ApplicationStatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionApplicationsPaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $candidate;

    private Vacancy $vacancy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ApplicationStatusSeeder::class);
        $this->member = User::factory()->create();
        $this->candidate = User::factory()->create(['name' => 'Candidate']);
        $this->vacancy = Vacancy::create(['title' => 'Lecturer', 'description' => 'Teaching role', 'type' => 'pps']);
        $this->vacancy->commissionMembers()->attach($this->member);
        Sanctum::actingAs($this->member);
    }

    public function test_queue_paginates_in_voting_order_with_complete_authorized_counts(): void
    {
        $competition = Competition::create(['type' => 'pps', 'number' => 1, 'title' => 'Available competition']);
        $created = collect();
        for ($index = 0; $index < 32; $index++) {
            $created->push($this->application(['competition_id' => $competition->id, 'voting_order' => match ($index) {
                0 => 2, 30, 31 => 1, default => null
            }]));
        }
        $this->application(['compliance_status' => 'pending']);
        $this->application(['archived_at' => now()]);
        $this->application(['hiring_status' => 'hired']);
        $hidden = Vacancy::create(['title' => 'Hidden vacancy', 'description' => 'Hidden role', 'type' => 'pps']);
        $privateCompetition = Competition::create(['type' => 'pps', 'number' => 2, 'title' => 'Hidden competition']);
        $this->application(['vacancy_id' => $hidden->id, 'competition_id' => $privateCompetition->id]);

        $ids = [];
        for ($page = 1; $page <= 4; $page++) {
            $response = $this->getJson('/api/commission/applications?type=pps&competition_id=all&per_page=10&page='.$page)
                ->assertOk()->assertJsonPath('meta.total', 32)->assertJsonPath('meta.last_page', 4)
                ->assertJsonPath('counts.pps', 32)->assertJsonCount(1, 'competitions')
                ->assertJsonPath('competitions.0.count', 32)
                ->assertJsonCount($page === 4 ? 2 : 10, 'data');
            array_push($ids, ...collect($response->json('data'))->pluck('id')->all());
        }
        $this->assertSame(collect([$created[30]->id, $created[31]->id, $created[0]->id])->concat($created->slice(1, 29)->pluck('id'))->all(), $ids);
        $this->assertCount(32, array_unique($ids));
    }

    public function test_competition_type_and_search_filters_apply_before_pagination(): void
    {
        $older = Competition::create(['type' => 'pps', 'number' => 1, 'title' => 'Older']);
        $latest = Competition::create(['type' => 'pps', 'number' => 2, 'title' => 'Latest']);
        $this->application(['competition_id' => $older->id]);
        $one = $this->application(['competition_id' => $latest->id]);
        $one->ppsProfile()->create(['desired_position' => 'Unique desired position', 'faculty_name' => 'Test faculty', 'department_name' => 'Test chair']);
        $this->application(['competition_id' => $latest->id]);
        $without = $this->application();
        $staff = Vacancy::create(['title' => 'Staff member', 'description' => 'Staff role', 'type' => 'staff']);
        $staff->commissionMembers()->attach($this->member);
        $this->application(['vacancy_id' => $staff->id]);

        $this->getJson('/api/commission/applications?type=pps&per_page=1')->assertOk()
            ->assertJsonPath('filters.competition_id', (string) $latest->id)
            ->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data')
            ->assertJsonPath('counts.pps', 4)->assertJsonPath('counts.staff', 1)->assertJsonCount(4, 'competitions');
        $this->getJson('/api/commission/applications?type=pps&competition_id=all&q=Unique')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $one->id)
            ->assertJsonPath('counts.pps', 4)->assertJsonCount(4, 'competitions');
        $this->getJson('/api/commission/applications?type=pps&competition_id=none')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $without->id);
        $this->getJson('/api/commission/applications?type=staff')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.vacancy.type', 'staff');
        $this->getJson('/api/commission/applications?type=pps&competition_id=999999')->assertOk()
            ->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
        $this->getJson('/api/commission/applications?type=pps&competition_id=all&q=nothing-matches')->assertOk()
            ->assertJsonPath('meta.total', 0)->assertJsonPath('counts.pps', 4);
    }

    public function test_page_size_validation_and_empty_pages_are_safe(): void
    {
        $this->application();
        foreach (['per_page=0', 'per_page=101', 'page=-1', 'type=unknown', 'competition_id=invalid'] as $query) {
            $this->getJson('/api/commission/applications?'.$query)->assertUnprocessable();
        }
        $this->getJson('/api/commission/applications?type=pps&competition_id=all&page=50')->assertOk()
            ->assertJsonPath('meta.last_page', 1)->assertJsonPath('meta.total', 1)->assertJsonCount(0, 'data');
    }

    public function test_search_preserves_unicode_case_insensitivity_and_literal_wildcards(): void
    {
        $this->candidate->update(['name' => 'Әбілов Жандос']);
        $application = $this->application();
        $application->ppsProfile()->create(['department_name' => 'Кафедра Информатики', 'desired_position' => 'Профессор 100%']);
        foreach (['әбілов', 'ЖАНДОС', 'кафедра информатики', '100%'] as $search) {
            $this->getJson('/api/commission/applications?'.http_build_query(['type' => 'pps', 'competition_id' => 'all', 'q' => $search]))
                ->assertOk()->assertJsonPath('meta.total', 1);
        }
        $this->getJson('/api/commission/applications?type=pps&competition_id=all&q=_')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_list_does_not_return_other_members_votes_and_query_count_is_bounded(): void
    {
        $other = User::factory()->create();
        $this->vacancy->commissionMembers()->attach($other);
        for ($index = 0; $index < 30; $index++) {
            $application = $this->application();
            $application->commissionVotes()->create(['user_id' => $other->id, 'decision' => 'reject', 'comment' => 'Private comment']);
        }
        DB::enableQueryLog();
        $response = $this->getJson('/api/commission/applications?type=pps&competition_id=all&per_page=25')->assertOk()->assertJsonCount(25, 'data');
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThan(35, $count);
        foreach ($response->json('data') as $row) {
            $this->assertNull($row['vote_summary']['my_vote']);
            $this->assertArrayNotHasKey('commission_votes', $row);
            $this->assertArrayNotHasKey('vote_details', $row);
        }
    }

    private function application(array $attributes = []): Application
    {
        return Application::create(array_merge([
            'user_id' => $this->candidate->id, 'vacancy_id' => $this->vacancy->id,
            'status_id' => ApplicationStatus::where('code', 'corruption_not_found')->value('id'),
            'resume_status' => 'accepted', 'documents_status' => 'accepted',
            'compliance_status' => 'clear', 'hiring_status' => 'not_started',
        ], $attributes));
    }
}
