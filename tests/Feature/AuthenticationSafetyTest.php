<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_failed_logins_are_rate_limited(): void
    {
        Cache::flush();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', ['email' => 'missing@example.com', 'password' => 'bad'])->assertUnprocessable();
        }
        $this->withHeader('X-Locale', 'kk')->postJson('/api/login', ['email' => 'missing@example.com', 'password' => 'bad'])
            ->assertStatus(429)->assertJsonPath('message', 'Тым көп әрекет жасалды. Бір минуттан кейін қайталаңыз.');
    }

    public function test_password_change_revokes_other_tokens_but_retains_current_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $current = $user->createToken('current');
        $other = $user->createToken('other');
        $this->withToken($current->plainTextToken)->postJson('/api/user/password', [
            'current_password' => 'old-password', 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertOk();
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    public function test_nested_impersonation_is_rejected_by_server(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create();
        $token = $admin->createToken('impersonated_by_999')->plainTextToken;
        $this->withToken($token)->postJson("/api/admin/users/{$target->id}/impersonate")->assertUnprocessable();
        $this->assertSame(0, $target->tokens()->count());
    }

    public function test_resume_upload_requires_an_owned_application_and_replaces_its_resume(): void
    {
        Storage::fake('documents');
        $user = User::factory()->create();
        $vacancy = Vacancy::create(['title' => 'Test', 'description' => 'Test', 'type' => 'staff']);
        $application = Application::create(['user_id' => $user->id, 'vacancy_id' => $vacancy->id, 'resume_status' => 'pending', 'hiring_status' => 'not_started']);
        Sanctum::actingAs($user);
        $this->postJson('/api/resumes', ['resume' => UploadedFile::fake()->create('test.pdf', 1)])->assertUnprocessable();
        $this->postJson('/api/resumes', ['application_id' => $application->id, 'resume' => UploadedFile::fake()->create('test.pdf', 1)])->assertOk();
        $oldPath = $application->fresh()->resume->file_path;
        $this->postJson('/api/resumes', ['application_id' => $application->id, 'resume' => UploadedFile::fake()->create('new.pdf', 1)])->assertOk();
        $this->assertDatabaseCount('resumes', 1);
        Storage::disk('documents')->assertMissing($oldPath);
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/resumes', ['application_id' => $application->id, 'resume' => UploadedFile::fake()->create('test.pdf', 1)])->assertNotFound();
    }
}
