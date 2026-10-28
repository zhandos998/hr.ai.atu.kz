<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\WorkerImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class WorkerImportTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    private function file(array $rows, ?callable $modify = null): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray($rows, null, 'A1', true);
        if ($modify) {
            $modify($book);
        }
        $path = tempnam(sys_get_temp_dir(), 'worker-test-');
        $this->files[] = $path;
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return new UploadedFile($path, 'workers.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function preview(array $rows): array
    {
        return $this->post('/api/admin/users/import/preview', ['file' => $this->file($rows)], ['Accept' => 'application/json'])
            ->assertOk()->json();
    }

    private function apply(array $preview, array $rows): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/admin/users/import/apply', ['batch_id' => $preview['batch_id'], 'rows' => $rows]);
    }

    public function test_pages_and_technical_user_filter_include_all_invalid_domain_accounts(): void
    {
        $this->get('/admin/users/technical')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Users')->where('technical', true));
        $this->get('/admin/users/import')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/UserImport'));
        $normal = User::factory()->create(['email' => 'normal@atu.edu.kz']);
        $candidate = User::factory()->create(['email' => 'manual-candidate-test@hr-ai.invalid']);
        $imported = User::factory()->create(['email' => 'legacy-worker-test@hr-ai.invalid']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $regular = $this->getJson('/api/admin/users')->assertOk()->json();
        $this->assertContains($normal->id, array_column($regular, 'id'));
        $this->assertNotContains($candidate->id, array_column($regular, 'id'));
        $this->assertNotContains($imported->id, array_column($regular, 'id'));
        $technical = $this->getJson('/api/admin/users?account_type=technical&paginate=1')->assertOk()->assertJsonPath('meta.total', 2)->json('data');
        $this->assertEqualsCanonicalizing([$candidate->id, $imported->id], array_column($technical, 'id'));
    }

    public function test_technical_users_paginate_and_retain_filters(): void
    {
        User::factory()->count(31)->sequence(fn ($sequence) => ['email' => 'legacy-worker-'.$sequence->index.'@hr-ai.invalid', 'name' => 'Import Employee '.$sequence->index])->create();
        User::factory()->create(['email' => 'manual-candidate-other@hr-ai.invalid', 'name' => 'Different']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/admin/users?account_type=technical&paginate=1&q=Import&page=2')->assertOk()->assertJsonCount(6, 'data')->assertJsonPath('meta.total', 31)->assertJsonPath('meta.current_page', 2);
    }

    public function test_preview_keeps_first_data_row_and_never_exposes_passwords(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $preview = $this->preview([['Новый Сотрудник', 'Новый_Сотрудник', 'Excel-Secret-9282', 'Начальник охраны, внутренний 212']]);
        $this->assertCount(1, $preview['rows']);
        $this->assertSame(1, $preview['rows'][0]['row_number']);
        $this->assertSame('новый_сотрудник', $preview['rows'][0]['username']);
        $this->assertSame('create', $preview['rows'][0]['action']);
        $this->assertStringNotContainsString('Excel-Secret-9282', json_encode($preview));
        $this->assertArrayNotHasKey('password_hash', $preview['rows'][0]);
        $this->assertStringNotContainsString('Excel-Secret-9282', DB::table('worker_import_batches')->value('payload'));
        $this->assertStringNotContainsString('Новый Сотрудник', DB::table('worker_import_batches')->value('payload'));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_headers_are_optional_and_formatted_numeric_passwords_keep_leading_zeroes(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $file = $this->file([['ФИО', 'Логин', 'Пароль', 'Должность'], ['Новый Сотрудник', 'Новый_Сотрудник', 12, 'Библиотека']], fn ($book) => $book->getActiveSheet()->getStyle('C2')->getNumberFormat()->setFormatCode('0000'));
        $preview = $this->post('/api/admin/users/import/preview', ['file' => $file], ['Accept' => 'application/json'])->assertOk()->json();
        $this->assertSame(2, $preview['rows'][0]['row_number']);
        $this->apply($preview, [['row_number' => 2, 'action' => 'create']])->assertOk();
        $this->assertTrue(Hash::check('0012', User::where('name', 'Новый Сотрудник')->firstOrFail()->password));
    }

    public function test_import_preserves_existing_credentials_roles_contacts_and_assignments(): void
    {
        $department = Department::create(['name' => 'HR']);
        $first = Position::create(['department_id' => $department->id, 'name' => 'First']);
        $second = Position::create(['department_id' => $department->id, 'name' => 'Second']);
        $user = User::factory()->create(['name' => 'Существующий Сотрудник', 'role' => 'economist', 'password' => 'Keep-Existing-9282', 'contact_info' => 'Keep contact']);
        $user->positions()->attach($first);
        $before = $user->only(['password', 'role', 'email', 'contact_info', 'email_verified_at']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $preview = $this->preview([['Существующий Сотрудник', 'Существующий_Сотрудник', 'Different-Pass-8928', 'New contact']]);
        $this->assertSame('existing', $preview['rows'][0]['action']);
        $this->apply($preview, [['row_number' => 1, 'action' => 'existing', 'user_id' => $user->id, 'position_id' => $second->id]])->assertOk()->assertJsonPath('result.updated.0', $user->id)->assertJsonPath('result.positions_assigned', 1);
        $this->assertEquals($before, $user->fresh()->only(array_keys($before)));
        $this->assertNull($user->fresh()->username);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $user->positions()->pluck('positions.id')->all());
    }

    public function test_new_account_has_hashed_file_password_technical_email_and_can_login(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $preview = $this->preview([['Құшан Аружан', 'Құшан_Аружан', 'Excel-Secret-9282', 'Декан, кабинет 5']]);
        $result = $this->apply($preview, [['row_number' => 1, 'action' => 'create']])->assertOk()->json('result');
        $user = User::findOrFail($result['created'][0]);
        $this->assertTrue(str_ends_with($user->email, '@hr-ai.invalid'));
        $this->assertSame('user', $user->role);
        $this->assertSame('Декан, кабинет 5', $user->contact_info);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('Excel-Secret-9282', $user->password));
        $this->assertNull($user->username);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Excel-Secret-9282'])->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonMissingPath('user.username');
    }

    public function test_duplicate_names_and_similar_spellings_require_manual_selection(): void
    {
        User::factory()->create(['name' => 'Сарттарова Ляззат']);
        User::factory()->count(2)->create(['name' => 'Абжанова Шолпан']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $preview = $this->preview([['Сарттарова Лаззат', 'Сарттарова_Лаззат', '1234'], ['Абжанова Шолпан', 'Абжанова_Шолпан', '1234']]);
        foreach ($preview['rows'] as $row) {
            $this->assertSame('skip', $row['action']);
            $this->assertNull($row['user_id']);
            $this->assertNotEmpty($row['suggestions']);
        }
    }

    public function test_candidate_accounts_can_be_selected_without_changing_application_links(): void
    {
        $user = User::factory()->create(['name' => 'Кандидат Сотрудник', 'email' => 'manual-candidate-staff@hr-ai.invalid', 'password' => 'Original-Password']);
        $vacancy = Vacancy::create(['title' => 'Staff', 'description' => 'Test', 'type' => 'staff']);
        $application = Application::create(['user_id' => $user->id, 'vacancy_id' => $vacancy->id]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $preview = $this->preview([['Кандидат Сотрудник', 'Кандидат_Сотрудник', '1234']]);
        $option = collect($preview['users'])->firstWhere('id', $user->id);
        $this->assertTrue($option['technical_email']);
        $this->apply($preview, [['row_number' => 1, 'action' => 'existing', 'user_id' => $user->id]])->assertOk();
        $this->assertSame('manual-candidate-staff@hr-ai.invalid', $user->fresh()->email);
        $this->assertTrue(Hash::check('Original-Password', $user->fresh()->password));
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'user_id' => $user->id, 'vacancy_id' => $vacancy->id]);
        $this->assertDatabaseCount('applications', 1);
    }

    public function test_short_source_password_is_allowed_only_for_existing_accounts(): void
    {
        $user = User::factory()->create(['name' => 'Садыков Мурат']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $preview = $this->preview([['Садыков Мурат', 'user22', 0], ['Новый Сотрудник', 'new-worker', 0]]);
        $this->assertSame('existing', $preview['rows'][0]['action']);
        $this->assertSame('skip', $preview['rows'][1]['action']);
        $this->apply($preview, [['row_number' => 2, 'action' => 'create']])->assertUnprocessable();
        $this->apply($preview, [['row_number' => 1, 'action' => 'existing', 'user_id' => $user->id]])->assertOk();
    }

    public function test_user_changes_since_preview_cause_complete_rollback(): void
    {
        $user = User::factory()->create(['name' => 'Существующий Сотрудник']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $preview = $this->preview([['Новый Сотрудник', 'new-worker', '1234'], ['Существующий Сотрудник', 'existing-worker', '1234']]);
        $user->update(['contact_info' => 'Changed after preview']);
        $this->apply($preview, [['row_number' => 1, 'action' => 'create'], ['row_number' => 2, 'action' => 'existing', 'user_id' => $user->id]])->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['name' => 'Новый Сотрудник']);
        $this->assertNull($user->fresh()->username);
    }

    public function test_repeated_confirmation_is_idempotent_and_clears_sensitive_payload(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $preview = $this->preview([['Новый Сотрудник', 'new-worker', '1234']]);
        $selections = [['row_number' => 1, 'action' => 'create']];
        $first = $this->apply($preview, $selections)->assertOk()->json('result');
        $this->apply($preview, $selections)->assertOk()->assertJsonPath('result', $first);
        $this->assertSame(1, User::where('name', 'Новый Сотрудник')->count());
        $batch = WorkerImportBatch::findOrFail($preview['batch_id']);
        $this->assertNull($batch->payload);
        $this->assertNotNull($batch->completed_at);
    }

    public function test_duplicate_logins_or_target_users_reject_entire_plan(): void
    {
        $user = User::factory()->create(['name' => 'Existing']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $preview = $this->preview([['Первый Сотрудник', 'first-worker', '1234'], ['Второй Сотрудник', 'second-worker', '1234']]);
        $this->apply($preview, [['row_number' => 1, 'action' => 'existing', 'user_id' => $user->id], ['row_number' => 2, 'action' => 'existing', 'user_id' => $user->id]])->assertUnprocessable();
        $this->assertNull($user->fresh()->username);
        $duplicate = $this->preview([['Первый Сотрудник', 'SAME-LOGIN', '1234'], ['Второй Сотрудник', 'same-login', '1234']]);
        $this->apply($duplicate, [['row_number' => 1, 'action' => 'create'], ['row_number' => 2, 'action' => 'create']])->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['name' => 'Первый Сотрудник']);
    }

    public function test_reserved_username_and_existing_different_username_are_not_overwritten(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'username' => 'admin-worker']);
        $user = User::factory()->create(['name' => 'Existing', 'username' => 'keep-existing']);
        Sanctum::actingAs($admin);
        $preview = $this->preview([['Новый Сотрудник', 'admin-worker', '1234']]);
        $this->apply($preview, [['row_number' => 1, 'action' => 'create']])->assertUnprocessable();
        $other = $this->preview([['Existing', 'replace-existing', '1234']]);
        $this->apply($other, [['row_number' => 1, 'action' => 'existing', 'user_id' => $user->id]])->assertOk();
        $this->assertSame('keep-existing', $user->fresh()->username);
    }

    public function test_only_admin_and_preview_owner_can_apply_and_expired_previews_are_rejected(): void
    {
        $this->postJson('/api/admin/users/import/apply', [])->assertUnauthorized();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/admin/users/import/preview', [])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $preview = $this->preview([['Новый Сотрудник', 'new-worker', '1234']]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->apply($preview, [['row_number' => 1, 'action' => 'create']])->assertNotFound();
        Sanctum::actingAs($admin);
        $this->travel(31)->minutes();
        $this->apply($preview, [['row_number' => 1, 'action' => 'create']])->assertUnprocessable();
    }

    public function test_formula_multiple_sheets_and_excessive_rows_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([
            $this->file([['Name', 'worker-login', '=1+1']]),
            $this->file([['Name', 'worker-login', '1234']], fn ($book) => $book->createSheet()),
            $this->file([['Name', 'worker-login', '1234']], fn ($book) => $book->getActiveSheet()->setCellValue('A202', 'Too many')),
        ] as $file) {
            $this->post('/api/admin/users/import/preview', ['file' => $file], ['Accept' => 'application/json'])->assertUnprocessable();
        }
        $this->assertDatabaseCount('worker_import_batches', 0);
    }

    public function test_unknown_rows_or_users_and_invalid_positions_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $preview = $this->preview([['Новый Сотрудник', 'new-worker', '1234']]);
        foreach ([['row_number' => 2, 'action' => 'create'], ['row_number' => 1, 'action' => 'existing', 'user_id' => $admin->id], ['row_number' => 1, 'action' => 'create', 'position_id' => 99999]] as $row) {
            $this->apply($preview, [$row])->assertUnprocessable();
        }
        $this->assertDatabaseMissing('users', ['name' => 'Новый Сотрудник']);
    }

    public function test_repeated_preview_recognizes_imported_accounts_without_assigning_a_username(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $rows = [['Новый Сотрудник', 'new-worker', '1234']];
        $preview = $this->preview($rows);
        $id = $this->apply($preview, [['row_number' => 1, 'action' => 'create']])->assertOk()->json('result.created.0');
        $again = $this->preview($rows);
        $this->assertSame('existing', $again['rows'][0]['action']);
        $this->assertSame($id, $again['rows'][0]['user_id']);
        $option = collect($again['users'])->firstWhere('id', $id);
        $this->assertArrayNotHasKey('username', $option);
        $this->assertNull(User::findOrFail($id)->username);
        $this->apply($again, [['row_number' => 1, 'action' => 'create']])->assertUnprocessable();
        $this->assertSame(1, User::where('name', 'Новый Сотрудник')->count());
    }
}
