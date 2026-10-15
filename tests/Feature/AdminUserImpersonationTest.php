<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_impersonate_another_user(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $user = User::factory()->create([
            'role' => 'science_director',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/users/{$user->id}/impersonate");

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'science_director')
            ->assertJsonPath('impersonator.id', $admin->id)
            ->assertJsonStructure([
                'token',
                'user',
                'impersonator',
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => "impersonated_by_{$admin->id}",
        ]);
    }

    public function test_admin_cannot_impersonate_self(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $this
            ->postJson("/api/admin/users/{$admin->id}/impersonate")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Нельзя войти от имени текущего пользователя.');
    }

    public function test_admin_can_assign_economist_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/users/{$user->id}/role", [
            'role' => 'economist',
        ])->assertOk()->assertJsonPath('user.role', 'economist');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'economist',
        ]);
    }

    public function test_admin_can_assign_and_detach_position_from_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'economist']);
        $department = Department::query()->create(['name' => 'Финансовый департамент']);
        $position = Position::query()->create([
            'department_id' => $department->id,
            'name' => 'Экономист',
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/user-position-options')
            ->assertOk()
            ->assertJsonPath('0.name', 'Экономист')
            ->assertJsonPath('0.unit_name', 'Финансовый департамент');

        $this->postJson("/api/admin/users/{$user->id}/positions", [
            'position_id' => $position->id,
        ])->assertOk()
            ->assertJsonPath('user.positions.0.id', $position->id)
            ->assertJsonPath('user.positions.0.department.name', 'Финансовый департамент');

        $this->assertDatabaseHas('position_user', [
            'position_id' => $position->id,
            'user_id' => $user->id,
        ]);

        $this->deleteJson("/api/admin/users/{$user->id}/positions/{$position->id}")
            ->assertOk()
            ->assertJsonCount(0, 'user.positions');

        $this->assertDatabaseMissing('position_user', [
            'position_id' => $position->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_admin_account_cannot_receive_position(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $department = Department::query()->create(['name' => 'ИТ']);
        $position = Position::query()->create([
            'department_id' => $department->id,
            'name' => 'Администратор системы',
        ]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/users/{$admin->id}/positions", [
            'position_id' => $position->id,
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('position_user', [
            'position_id' => $position->id,
            'user_id' => $admin->id,
        ]);
    }
}
