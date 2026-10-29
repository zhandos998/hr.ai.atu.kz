<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleIdentityVerifier;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private static string $privateKey;

    private static array $jwk;

    private int $googleStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        Cache::flush();
        config(['services.google.client_id' => 'test.apps.googleusercontent.com']);
        if (! isset(self::$privateKey)) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $privateKey);
            self::$privateKey = $privateKey;
            $rsa = openssl_pkey_get_details($key)['rsa'];
            self::$jwk = ['kty' => 'RSA', 'alg' => 'RS256', 'kid' => 'test-key', 'n' => JWT::urlsafeB64Encode($rsa['n']), 'e' => JWT::urlsafeB64Encode($rsa['e'])];
        }
        Http::preventStrayRequests();
        Http::fake([GoogleIdentityVerifier::CERTS_URL => fn () => Http::response(['keys' => [self::$jwk]], $this->googleStatus)]);
    }

    private function challenge(): array
    {
        $response = $this->getJson('/api/auth/google/challenge')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'google_signin_challenge');
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());

        return $response->json();
    }

    private function credential(string $nonce, array $overrides = []): string
    {
        return JWT::encode(array_replace([
            'sub' => 'google-person-1', 'email' => 'teacher@atu.edu.kz', 'email_verified' => true,
            'hd' => 'atu.edu.kz', 'aud' => config('services.google.client_id'),
            'iss' => 'https://accounts.google.com', 'iat' => time(), 'exp' => time() + 3600, 'nonce' => $nonce,
        ], $overrides), self::$privateKey, 'RS256', 'test-key');
    }

    private function signIn(array $overrides = [])
    {
        $nonce = $this->challenge()['nonce'];

        return $this->postJson('/api/auth/google', ['nonce' => $nonce, 'credential' => $this->credential($nonce, $overrides)]);
    }

    public function test_google_is_hidden_and_login_disabled_without_configuration(): void
    {
        config(['services.google.client_id' => null]);
        $this->getJson('/api/auth/google/challenge')->assertOk()->assertExactJson(['enabled' => false]);
        $this->postJson('/api/auth/google', [])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_verified_workspace_account_links_existing_user_without_changing_password_role_or_name(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'teacher@atu.edu.kz', 'role' => 'academic_director']);
        $before = $user->getRawOriginal();
        $this->signIn()->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonPath('user.role', 'academic_director')
            ->assertJsonStructure(['token'])->assertJsonMissingPath('user.google_sub')->assertJsonMissingPath('user.password');
        $user->refresh();
        foreach (['name', 'email', 'password', 'role'] as $field) {
            $this->assertSame($before[$field], $user->getRawOriginal($field));
        }
        $this->assertSame('google-person-1', $user->google_sub);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_personal_gmail_can_login_and_public_keys_are_cached(): void
    {
        User::factory()->create(['email' => 'person@gmail.com']);
        $this->signIn(['email' => 'person@gmail.com', 'hd' => null])->assertOk();
        $this->signIn(['email' => 'person@gmail.com', 'hd' => null])->assertOk();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_unknown_google_user_is_not_registered(): void
    {
        foreach ([
            'ru' => 'Для вашего аккаунта нет доступа к порталу. Обратитесь в ДИТ: кабинет 406, внутренние номера 127, 128.',
            'kk' => 'Аккаунтыңызға порталға кіру рұқсаты берілмеген. ДИТ бөліміне хабарласыңыз: 406-кабинет, ішкі нөмірлер: 127, 128.',
        ] as $locale => $message) {
            $this->withHeader('X-Locale', $locale)->signIn()->assertUnprocessable()
                ->assertJsonPath('errors.credential.0', $message);
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_invalid_claims_do_not_authenticate(): void
    {
        $user = User::factory()->create(['email' => 'teacher@atu.edu.kz']);
        foreach ([
            ['aud' => 'another-app'], ['iss' => 'https://attacker.example'], ['exp' => time() - 1],
            ['exp' => null], ['nonce' => 'wrong'], ['email_verified' => false], ['sub' => ''],
            ['iat' => time() + 3600], ['email' => ['bad']],
        ] as $overrides) {
            Cache::flush();
            $this->signIn($overrides)->assertUnprocessable();
        }
        $this->assertNull($user->fresh()->google_sub);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_non_google_mailbox_cannot_link_by_email_even_when_marked_verified(): void
    {
        User::factory()->create(['email' => 'person@example.com']);
        $this->signIn(['email' => 'person@example.com', 'hd' => null])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_browser_cookie_and_nonce_are_required_and_nonce_cannot_be_replayed(): void
    {
        User::factory()->create(['email' => 'teacher@atu.edu.kz']);
        $nonce = $this->challenge()['nonce'];
        $payload = ['nonce' => $nonce, 'credential' => $this->credential($nonce)];
        $this->withUnencryptedCookie('google_signin_challenge', 'attacker')->postJson('/api/auth/google', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $nonce = $this->challenge()['nonce'];
        $payload = ['nonce' => $nonce, 'credential' => $this->credential($nonce)];
        $this->postJson('/api/auth/google', $payload)->assertOk();
        $this->postJson('/api/auth/google', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_linked_identity_cannot_access_another_email_or_replace_an_existing_link(): void
    {
        $user = User::factory()->create(['email' => 'teacher@atu.edu.kz']);
        $user->forceFill(['google_sub' => 'other-identity'])->save();
        $this->signIn()->assertUnprocessable();
        $this->signIn(['sub' => 'other-identity', 'email' => 'changed@atu.edu.kz'])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertSame('other-identity', $user->fresh()->google_sub);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        User::factory()->create(['email' => 'teacher@atu.edu.kz']);
        foreach ([
            'ru' => 'Не удалось подтвердить вход через Google. Повторите попытку. Если ошибка повторяется, обратитесь в ДИТ: кабинет 406, внутренние номера 127, 128.',
            'kk' => 'Google арқылы кіруді растау мүмкін болмады. Қайталап көріңіз. Қате қайталанса, ДИТ бөліміне хабарласыңыз: 406-кабинет, ішкі нөмірлер: 127, 128.',
        ] as $locale => $message) {
            $nonce = $this->challenge()['nonce'];
            $token = $this->credential($nonce);
            $parts = explode('.', $token);
            $parts[2] = JWT::urlsafeB64Encode(str_repeat('x', 256));
            $this->withHeader('X-Locale', $locale)->postJson('/api/auth/google', ['nonce' => $nonce, 'credential' => implode('.', $parts)])
                ->assertUnprocessable()->assertJsonPath('errors.credential.0', $message);
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_google_network_error_has_localized_response_and_no_login(): void
    {
        $this->googleStatus = 503;
        $this->withHeader('X-Locale', 'kk')->signIn()->assertStatus(503)
            ->assertJsonPath('message', 'Google уақытша қолжетімсіз. Email және құпиясөз арқылы кіріңіз.');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_confirming_a_new_email_clears_the_former_google_link(): void
    {
        $user = User::factory()->create(['email' => 'teacher@atu.edu.kz']);
        $user->forceFill(['google_sub' => 'google-person-1'])->save();
        $token = 'email-change-token';
        DB::table('email_change_requests')->insert([
            'user_id' => $user->id, 'new_email' => 'changed@atu.edu.kz',
            'token' => hash('sha256', $token), 'created_at' => now(),
        ]);
        $this->postJson('/api/user/email/change-confirm', ['token' => $token])->assertOk();
        $this->assertNull($user->fresh()->google_sub);
        $this->assertSame('changed@atu.edu.kz', $user->fresh()->email);
    }

    public function test_stateful_sanctum_browser_cookies_work_too(): void
    {
        User::factory()->create(['email' => 'teacher@atu.edu.kz']);
        $this->withHeader('Origin', 'http://localhost')->signIn()->assertOk();
    }

    public function test_expired_browser_challenge_is_rejected(): void
    {
        $nonce = $this->challenge()['nonce'];
        $this->travel(6)->minutes();
        $this->postJson('/api/auth/google', ['nonce' => $nonce, 'credential' => $this->credential($nonce)])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_ambiguous_case_insensitive_email_does_not_link_either_account(): void
    {
        User::factory()->create(['email' => 'teacher@atu.edu.kz']);
        User::factory()->create(['email' => 'Teacher@atu.edu.kz']);
        $this->signIn()->assertUnprocessable();
        $this->assertSame(0, User::whereNotNull('google_sub')->count());
    }

    public function test_technical_email_account_is_not_linked(): void
    {
        User::factory()->create(['email' => 'legacy@hr-ai.invalid']);
        $this->signIn(['email' => 'legacy@hr-ai.invalid'])->assertUnprocessable();
        $this->assertSame(0, User::whereNotNull('google_sub')->count());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_google_challenge_requests_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->getJson('/api/auth/google/challenge')->assertOk();
        }
        $this->getJson('/api/auth/google/challenge')->assertStatus(429);
    }
}
