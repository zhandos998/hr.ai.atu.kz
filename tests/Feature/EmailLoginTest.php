<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_email_login_normalizes_case_and_whitespace_without_exposing_old_usernames(): void
    {
        $user = User::factory()->create(['username' => 'legacy.staff', 'email' => 'Staff@atu.edu.kz', 'password' => 'test-password']);
        foreach (['staff@atu.edu.kz', ' STAFF@ATU.EDU.KZ '] as $email) {
            $this->postJson('/api/login', ['email' => $email, 'password' => 'test-password'])
                ->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonStructure(['token'])
                ->assertJsonMissingPath('user.username')->assertJsonMissingPath('user.password');
        }
        $this->assertSame('legacy.staff', $user->fresh()->username);
        $this->assertSame(2, $user->tokens()->count());
    }

    public function test_username_login_is_disabled_even_when_the_saved_password_matches(): void
    {
        $user = User::factory()->create(['username' => 'legacy.staff', 'password' => 'test-password']);
        foreach ([['login' => 'legacy.staff'], ['login' => $user->email], ['email' => 'legacy.staff']] as $identifier) {
            $this->postJson('/api/login', [...$identifier, 'password' => 'test-password'])
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_technical_email_accounts_can_login_using_their_email(): void
    {
        $user = User::factory()->create(['email' => 'legacy-worker-test@hr-ai.invalid', 'password' => '1234']);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => '1234'])->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_invalid_email_or_password_never_creates_a_token(): void
    {
        $user = User::factory()->create(['password' => 'test-password']);
        foreach ([[$user->email, 'wrong'], ['not-found@example.com', 'test-password'], ['   ', 'test-password']] as [$email, $password]) {
            $this->postJson('/api/login', compact('email', 'password'))->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_malformed_credentials_return_validation_errors_not_server_errors(): void
    {
        foreach ([['email' => ['test'], 'password' => 'password'], ['email' => str_repeat('a', 256), 'password' => 'password'], ['email' => 'test@example.com', 'password' => ['password']]] as $payload) {
            $this->postJson('/api/login', $payload)->assertUnprocessable();
        }
    }

    public function test_email_login_still_requires_verification(): void
    {
        Mail::shouldReceive('raw')->once()->with(\Mockery::type('string'), \Mockery::type('Closure'));
        $user = User::factory()->unverified()->create(['password' => 'test-password']);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'test-password'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('email_verification_tokens', ['email' => $user->email]);
    }

    public function test_email_case_and_whitespace_do_not_bypass_rate_limiting(): void
    {
        $user = User::factory()->create(['email' => 'limited@example.com']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $email = $attempt % 2 === 0 ? ' LIMITED@EXAMPLE.COM ' : $user->email;
            $this->postJson('/api/login', ['email' => $email, 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->withHeader('X-Locale', 'kk')->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(429)->assertJsonPath('message', 'Тым көп әрекет жасалды. Бір минуттан кейін қайталаңыз.');
    }

    public function test_admin_creates_users_without_a_username_and_cannot_assign_one(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $payload = ['name' => 'Test User', 'email' => 'new@example.com', 'role' => 'user', 'password' => '1234'];
        $this->postJson('/api/admin/users', [...$payload, 'username' => 'new.staff'])->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        $this->postJson('/api/admin/users', $payload)->assertCreated()->assertJsonMissingPath('user.username');
        $this->assertDatabaseHas('users', ['email' => $payload['email'], 'username' => null]);
    }

    public function test_removed_username_endpoint_does_not_change_existing_data(): void
    {
        $user = User::factory()->create(['username' => 'keep.legacy']);
        $before = $user->fresh()->getRawOriginal();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson("/api/admin/users/{$user->id}/username", ['username' => 'replacement'])->assertStatus(405);
        $this->assertSame($before, $user->fresh()->getRawOriginal());
    }

    public function test_admin_user_list_neither_exposes_nor_searches_by_old_usernames(): void
    {
        $user = User::factory()->create(['username' => 'unique.hidden.identifier', 'name' => 'Visible Person']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/admin/users?q=unique.hidden.identifier')->assertOk()->assertJsonCount(0);
        $this->getJson('/api/admin/users?q=Visible%20Person')->assertOk()->assertJsonPath('0.id', $user->id)->assertJsonMissingPath('0.username');
    }

    public function test_existing_sessions_remain_valid_without_returning_old_usernames(): void
    {
        $user = User::factory()->create(['username' => 'legacy.staff']);
        $token = $user->createToken('existing-session')->plainTextToken;
        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('id', $user->id)->assertJsonMissingPath('username');
    }

    public function test_email_login_errors_are_localized_in_both_languages(): void
    {
        foreach (['ru' => 'Неверные учетные данные.', 'kk' => 'Email немесе құпиясөз қате.'] as $locale => $message) {
            $this->withHeader('X-Locale', $locale)->postJson('/api/login', ['email' => 'missing@example.com', 'password' => 'wrong'])
                ->assertUnprocessable()->assertJsonPath('errors.email.0', $message);
        }
    }

    public function test_removed_username_field_is_rejected_with_localized_errors(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['ru' => 'Вход по логину отключён. Используйте email.', 'kk' => 'Логин арқылы кіру өшірілген. Email пайдаланыңыз.'] as $locale => $message) {
            $this->withHeader('X-Locale', $locale)->postJson('/api/admin/users', [
                'name' => 'New Person', 'email' => 'new@example.com', 'role' => 'user', 'password' => '1234', 'username' => 'legacy.login',
            ])->assertUnprocessable()->assertJsonPath('errors.username.0', $message);
        }
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }
}
