<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsernameLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_user_can_login_by_username_or_email_with_case_and_whitespace_normalized(): void
    {
        $user = User::factory()->create(['username' => 'test.staff', 'email' => 'Staff@atu.edu.kz', 'password' => 'test-password']);

        foreach (['test.staff', ' TEST.STAFF ', 'Staff@atu.edu.kz', ' STAFF@ATU.EDU.KZ '] as $identifier) {
            $response = $this->postJson('/api/login', ['login' => $identifier, 'password' => 'test-password']);
            $response->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonPath('user.username', 'test.staff')->assertJsonStructure(['token']);
            $response->assertJsonMissingPath('user.password');
        }
        $this->assertSame(4, $user->tokens()->count());
    }

    public function test_legacy_email_login_still_works_without_a_username(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.com', 'password' => 'test-password']);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'test-password'])->assertOk()->assertJsonPath('user.id', $user->id);
        $this->postJson('/api/login', ['login' => 'staff', 'password' => 'test-password'])->assertUnprocessable();
    }

    public function test_invalid_login_or_password_never_creates_a_token(): void
    {
        $user = User::factory()->create(['username' => 'test.staff', 'password' => 'test-password']);
        foreach ([['test.staff', 'wrong'], ['not-found', 'test-password'], ['   ', 'test-password']] as [$identifier, $password]) {
            $this->postJson('/api/login', ['login' => $identifier, 'password' => $password])->assertUnprocessable()->assertJsonValidationErrors('login');
        }
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_malformed_credentials_return_validation_errors_instead_of_server_errors(): void
    {
        foreach ([['login' => ['test'], 'password' => 'password'], ['login' => str_repeat('a', 256), 'password' => 'password'], ['login' => 'test', 'password' => ['password']]] as $payload) {
            $this->postJson('/api/login', $payload)->assertUnprocessable();
        }
    }

    public function test_username_does_not_bypass_email_verification(): void
    {
        Mail::shouldReceive('raw')->once()->with(\Mockery::type('string'), \Mockery::type('Closure'));
        $user = User::factory()->unverified()->create(['username' => 'unverified.staff', 'password' => 'test-password']);
        $this->postJson('/api/login', ['login' => $user->username, 'password' => 'test-password'])->assertUnprocessable()->assertJsonValidationErrors('login');
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('email_verification_tokens', ['email' => $user->email]);
    }

    public function test_username_and_email_share_the_same_login_rate_limit(): void
    {
        $user = User::factory()->create(['username' => 'limited.staff', 'email' => 'limited@example.com']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $identifier = $attempt % 2 === 0 ? ' LIMITED.STAFF ' : ' LIMITED@EXAMPLE.COM ';
            $this->postJson('/api/login', ['login' => $identifier, 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(429);
        $this->withHeader('X-Locale', 'kk')->postJson('/api/login', ['login' => $user->username, 'password' => 'wrong'])->assertStatus(429)->assertJsonPath('message', 'Тым көп әрекет жасалды. Бір минуттан кейін қайталаңыз.');
    }

    public function test_admin_can_create_a_user_with_an_optional_unique_username(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $payload = ['name' => 'Test User', 'email' => 'new@example.com', 'username' => ' New.Staff ', 'role' => 'user', 'password' => '1234'];
        $this->postJson('/api/admin/users', $payload)->assertCreated()->assertJsonPath('user.username', 'new.staff');
        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'username' => 'new.staff']);
        $payload['email'] = 'duplicate@example.com';
        $payload['username'] = 'NEW.STAFF';
        $this->postJson('/api/admin/users', $payload)->assertUnprocessable()->assertJsonValidationErrors('username');
        unset($payload['username']);
        $this->postJson('/api/admin/users', $payload)->assertCreated()->assertJsonPath('user.username', null);
    }

    public function test_admin_can_set_change_and_clear_a_username_without_changing_password_or_email(): void
    {
        $user = User::factory()->create();
        $password = $user->password;
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([' Test.Staff ' => 'test.staff', 'other.staff' => 'other.staff', '' => null] as $input => $expected) {
            $this->putJson("/api/admin/users/{$user->id}/username", ['username' => $input])->assertOk()->assertJsonPath('user.username', $expected);
            $this->assertSame($expected, $user->fresh()->username);
            $this->assertSame($password, $user->fresh()->password);
            $this->assertSame($user->email, $user->fresh()->email);
            $this->assertNotNull($user->fresh()->email_verified_at);
        }
    }

    public function test_username_update_checks_uniqueness_and_format(): void
    {
        $other = User::factory()->create(['username' => 'taken.staff']);
        $user = User::factory()->create(['username' => 'current.staff']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $endpoint = "/api/admin/users/{$user->id}/username";
        $this->putJson($endpoint, ['username' => ' CURRENT.STAFF '])->assertOk();
        $this->putJson($endpoint, ['username' => ' TAKEN.STAFF '])->assertUnprocessable()->assertJsonValidationErrors('username');
        foreach (['ab', '0', str_repeat('a', 65), 'name@example.com', 'some name', 'логин', '_staff', ['name']] as $invalid) {
            $this->putJson($endpoint, ['username' => $invalid])->assertUnprocessable()->assertJsonValidationErrors('username');
        }
        $this->putJson($endpoint, [])->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->assertSame('current.staff', $user->fresh()->username);
        $this->assertSame('taken.staff', $other->fresh()->username);
    }

    public function test_username_errors_are_localized_in_both_languages(): void
    {
        User::factory()->create(['username' => 'taken.staff']);
        $user = User::factory()->create();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['ru' => 'Этот логин уже используется другим пользователем.', 'kk' => 'Бұл логинді басқа пайдаланушы қолданып жатыр.'] as $locale => $message) {
            $this->withHeader('X-Locale', $locale)->putJson("/api/admin/users/{$user->id}/username", ['username' => 'TAKEN.STAFF'])->assertUnprocessable()->assertJsonPath('errors.username.0', $message);
        }
    }

    public function test_login_failure_is_localized_in_both_languages(): void
    {
        foreach (['ru' => 'Неверные учетные данные.', 'kk' => 'Логин, email немесе құпиясөз қате.'] as $locale => $message) {
            $this->withHeader('X-Locale', $locale)->postJson('/api/login', ['login' => 'missing.staff', 'password' => 'wrong'])->assertUnprocessable()->assertJsonPath('errors.login.0', $message);
        }
    }

    public function test_only_admin_can_assign_a_username(): void
    {
        $user = User::factory()->create();
        $endpoint = "/api/admin/users/{$user->id}/username";
        $this->putJson($endpoint, ['username' => 'new.staff'])->assertUnauthorized();
        Sanctum::actingAs($user);
        $this->putJson($endpoint, ['username' => 'new.staff'])->assertForbidden();
        $this->assertNull($user->fresh()->username);
    }

    public function test_admin_list_returns_username_and_can_search_by_it(): void
    {
        $user = User::factory()->create(['username' => 'unique.staff']);
        User::factory()->create();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/admin/users?q=unique.staff')->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $user->id)->assertJsonPath('0.username', $user->username);
    }
}
