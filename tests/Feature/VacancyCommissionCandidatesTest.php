<?php

namespace Tests\Feature;

use App\Models\CommissionMember;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VacancyCommissionCandidatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_permanent_commission_member_can_be_searched_after_detach_from_vacancy(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $member = User::factory()->create([
            'name' => 'Нурахметов Бауржан',
            'email' => 'b.nurakhmetov@atu.edu.kz',
        ]);

        CommissionMember::query()->create([
            'user_id' => $member->id,
            'is_pps' => false,
            'is_staff' => true,
        ]);

        $vacancy = Vacancy::query()->create([
            'title' => 'Специалист',
            'description' => 'Тестовая вакансия.',
            'type' => 'staff',
        ]);

        $vacancy->commissionMembers()->attach($member->id);
        $vacancy->commissionMembers()->detach($member->id);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/vacancies/{$vacancy->id}/commission-candidates?q=Нурахметов")
            ->assertOk()
            ->assertJsonFragment([
                'id' => $member->id,
                'name' => 'Нурахметов Бауржан',
            ]);
    }

    public function test_permanent_commission_member_can_be_added_back_to_vacancy(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $member = User::factory()->create([
            'name' => 'Нурахметов Бауржан',
            'email' => 'b.nurakhmetov@atu.edu.kz',
        ]);

        CommissionMember::query()->create([
            'user_id' => $member->id,
            'is_pps' => false,
            'is_staff' => true,
        ]);

        $vacancy = Vacancy::query()->create([
            'title' => 'Специалист',
            'description' => 'Тестовая вакансия.',
            'type' => 'staff',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/vacancies/{$vacancy->id}/commission-members", [
            'user_id' => $member->id,
        ])
            ->assertOk()
            ->assertJsonFragment([
                'id' => $member->id,
                'name' => 'Нурахметов Бауржан',
            ]);

        $this->assertDatabaseHas('vacancy_commission_member', [
            'vacancy_id' => $vacancy->id,
            'user_id' => $member->id,
        ]);
    }
}
