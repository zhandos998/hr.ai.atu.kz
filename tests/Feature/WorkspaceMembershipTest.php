<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_endpoint_reports_commission_membership_independently_of_primary_role(): void
    {
        $vacancy = Vacancy::create(['title' => 'Test vacancy', 'description' => 'Test', 'type' => 'pps']);
        foreach (['user', 'strategy_director'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $vacancy->commissionMembers()->attach($user);
            Sanctum::actingAs($user);
            $this->getJson('/api/user')->assertOk()->assertJsonPath('role', $role)->assertJsonPath('is_commission_member', true);
        }
        $reviewer = User::factory()->create(['role' => 'strategy_director']);
        Sanctum::actingAs($reviewer);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('is_commission_member', false);
    }
}
