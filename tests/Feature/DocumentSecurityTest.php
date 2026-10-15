<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Models\Vacancy;
use App\Support\DocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function application(): Application
    {
        return Application::create([
            'user_id' => User::factory()->create()->id,
            'vacancy_id' => Vacancy::create(['title' => 'Test', 'description' => 'Test', 'type' => 'staff'])->id,
            'resume_status' => 'accepted', 'documents_status' => 'awaiting_upload',
            'compliance_status' => 'not_started', 'hiring_status' => 'not_started',
        ]);
    }

    public function test_application_requires_login_and_authorization(): void
    {
        $application = $this->application();
        $this->getJson("/api/applications/{$application->id}")->assertUnauthorized();
        $this->get("/api/applications/{$application->id}")->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/applications/{$application->id}")->assertForbidden();
        Sanctum::actingAs($application->user);
        $this->getJson("/api/applications/{$application->id}")->assertOk();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson("/api/applications/{$application->id}")->assertOk();
    }

    public function test_pdf_with_html_extension_is_not_written_to_storage(): void
    {
        Storage::fake('documents');
        $application = $this->application();
        Sanctum::actingAs($application->user);
        $fixture = UploadedFile::fake()->createWithContent('probe.pdf', "%PDF-1.4\n<html><script>window.probe=true</script></html>");
        $file = new UploadedFile($fixture->getPathname(), 'probe.html', null, null, true);

        $this->postJson("/api/applications/{$application->id}/upload-docs", ['diploma' => [$file]])
            ->assertUnprocessable();
        $this->assertDatabaseCount('application_documents', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_entire_upload_batch_is_validated_before_writing_files(): void
    {
        Storage::fake('documents');
        $application = $this->application();
        Sanctum::actingAs($application->user);
        $valid = UploadedFile::fake()->createWithContent('valid.pdf', "%PDF-1.4\nTest");
        $fixture = UploadedFile::fake()->createWithContent('bad.pdf', "%PDF-1.4\n<script>alert(1)</script>");
        $invalid = new UploadedFile($fixture->getPathname(), 'bad.html', null, null, true);

        $this->postJson("/api/applications/{$application->id}/upload-docs", ['diploma' => [$valid, $invalid]])
            ->assertUnprocessable()->assertJsonValidationErrors('diploma.1');
        $this->assertDatabaseCount('application_documents', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_signed_document_links_expire_and_serve_safe_headers(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('resumes/test.pdf', "%PDF-1.4\nTest");
        $this->get('/documents/resumes/test.pdf')->assertForbidden();
        $url = DocumentStorage::url('resumes/test.pdf');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->travel(16)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_personnel_files_cannot_be_opened_through_candidate_links(): void
    {
        Storage::fake('documents');
        Storage::disk('documents')->put('personnel-requests/1/salary.pdf', 'confidential');
        $this->get(DocumentStorage::url('personnel-requests/1/salary.pdf'))->assertNotFound();
    }

    public function test_privatization_preserves_files_and_is_repeatable(): void
    {
        Storage::fake('public');
        Storage::fake('documents');
        Storage::disk('public')->put('applications/1/diploma.pdf', 'legacy');
        Storage::disk('public')->put('logo.png', 'public logo');
        $this->artisan('documents:privatize')->assertSuccessful();
        $this->assertSame('legacy', Storage::disk('documents')->get('applications/1/diploma.pdf'));
        Storage::disk('public')->assertMissing('applications/1/diploma.pdf');
        Storage::disk('public')->assertExists('logo.png');
        $this->artisan('documents:privatize')->assertSuccessful();
    }

    public function test_conflicting_private_copy_does_not_destroy_public_original(): void
    {
        Storage::fake('public');
        Storage::fake('documents');
        Storage::disk('public')->put('resumes/1.pdf', 'original');
        Storage::disk('documents')->put('resumes/1.pdf', 'different');
        try {
            $this->artisan('documents:privatize')->run();
            $this->fail('Conflicting files must stop migration.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Source preserved', $exception->getMessage());
        }
        $this->assertSame('original', Storage::disk('public')->get('resumes/1.pdf'));
    }
}
