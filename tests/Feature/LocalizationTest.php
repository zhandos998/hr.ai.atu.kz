<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_email_validation_is_returned_in_russian(): void
    {
        User::factory()->create(['email' => 'used@example.com']);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->withHeader('X-Locale', 'ru')
            ->postJson('/api/user/email/change-request', [
                'new_email' => 'used@example.com',
                'password' => 'password',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.new_email.0', 'Этот email уже используется другим пользователем.');
    }

    public function test_duplicate_email_validation_is_returned_in_kazakh(): void
    {
        User::factory()->create(['email' => 'used@example.com']);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->withHeader('X-Locale', 'kk')
            ->postJson('/api/user/email/change-request', [
                'new_email' => 'used@example.com',
                'password' => 'password',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.new_email.0', 'Бұл email басқа пайдаланушыда тіркелген.');
    }

    public function test_email_delivery_failure_is_returned_in_russian_and_request_is_removed(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('SMTP unavailable'));

        $this->withHeader('X-Locale', 'ru')
            ->postJson('/api/user/email/change-request', [
                'new_email' => 'new@example.com',
                'password' => 'password',
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.new_email.0',
                'Не удалось отправить письмо. Проверьте email или повторите попытку позже.'
            );

        $this->assertSame(0, DB::table('email_change_requests')->count());
    }

    public function test_email_delivery_failure_is_returned_in_kazakh(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        Mail::shouldReceive('raw')->once()->andThrow(new RuntimeException('SMTP unavailable'));

        $this->withHeader('X-Locale', 'kk')
            ->postJson('/api/user/email/change-request', [
                'new_email' => 'new@example.com',
                'password' => 'password',
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.new_email.0',
                'Хатты жіберу мүмкін болмады. Email мекенжайын тексеріңіз немесе кейінірек қайталап көріңіз.'
            );
    }
}
