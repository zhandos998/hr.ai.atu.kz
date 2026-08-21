<?php

namespace Tests\Feature;

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
}
