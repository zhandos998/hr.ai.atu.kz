<?php

namespace Tests\Feature;

use App\Models\PersonnelRegisterEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonnelRegisterTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $type = 'sick_leave', array $overrides = []): array
    {
        return array_replace([
            'employee_name' => 'Серикова Айгуль', 'position_name' => 'Ведущий специалист',
            'document_number' => 'ЛН-101', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-10',
            ...($type === 'sick_leave' ? ['diagnosis' => 'Тестовый диагноз'] : ['leave_type' => 'Ежегодный отпуск']),
        ], $overrides);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function create(string $type = 'sick_leave', array $overrides = []): int
    {
        return $this->postJson('/api/admin/personnel-register/'.$type, $this->payload($type, $overrides))->assertCreated()->json('id');
    }

    public function test_guests_cannot_read_or_write_any_register(): void
    {
        foreach (['sick_leave', 'leave'] as $type) {
            $url = '/api/admin/personnel-register/'.$type;
            $this->getJson($url)->assertUnauthorized();
            $this->getJson($url.'/1')->assertUnauthorized();
            $this->postJson($url, $this->payload($type))->assertUnauthorized();
            $this->putJson($url.'/1', $this->payload($type))->assertUnauthorized();
            $this->deleteJson($url.'/1')->assertUnauthorized();
        }
    }

    public function test_regular_users_and_other_roles_cannot_access_register_data(): void
    {
        foreach (['user', 'commission', 'economist', 'lawyer', 'academic_director'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            foreach (['sick_leave', 'leave'] as $type) {
                $url = '/api/admin/personnel-register/'.$type;
                $this->getJson($url)->assertForbidden();
                $this->getJson($url.'/1')->assertForbidden();
                $this->postJson($url, $this->payload($type))->assertForbidden();
                $this->putJson($url.'/1', $this->payload($type))->assertForbidden();
                $this->deleteJson($url.'/1')->assertForbidden();
            }
        }
        $this->assertDatabaseCount('personnel_register_entries', 0);
    }

    public function test_admin_can_create_each_type_without_creating_a_user(): void
    {
        $admin = $this->admin();
        $sick = $this->create();
        $leave = $this->create('leave');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('personnel_register_entries', ['id' => $sick, 'type' => 'sick_leave', 'created_by' => $admin->id]);
        $this->getJson('/api/admin/personnel-register/leave/'.$leave)->assertOk()->assertJsonPath('leave_type', 'Ежегодный отпуск');
    }

    public function test_diagnosis_is_encrypted_in_the_database_and_only_returned_to_admin(): void
    {
        $this->admin();
        $id = $this->create();
        $stored = DB::table('personnel_register_entries')->where('id', $id)->value('diagnosis');
        $this->assertNotSame('Тестовый диагноз', $stored);
        $this->assertStringNotContainsString('Тестовый диагноз', $stored);
        $this->getJson('/api/admin/personnel-register/sick_leave/'.$id)->assertOk()
            ->assertJsonPath('diagnosis', 'Тестовый диагноз')->assertJsonPath('starts_on', '2026-10-01')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/admin/personnel-register/sick_leave')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_register_types_cannot_read_modify_or_delete_each_others_entries(): void
    {
        $this->admin();
        $id = $this->create();
        $this->getJson('/api/admin/personnel-register/leave')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/admin/personnel-register/leave/'.$id)->assertNotFound();
        $this->putJson('/api/admin/personnel-register/leave/'.$id, $this->payload('leave'))->assertNotFound();
        $this->deleteJson('/api/admin/personnel-register/leave/'.$id)->assertNotFound();
        $this->assertDatabaseHas('personnel_register_entries', ['id' => $id, 'deleted_at' => null]);
    }

    public function test_fields_and_dates_are_validated_in_both_languages(): void
    {
        $this->admin();
        foreach (['ru', 'kk'] as $locale) {
            $response = $this->withHeader('X-Locale', $locale)->postJson('/api/admin/personnel-register/sick_leave', $this->payload(overrides: [
                'employee_name' => '', 'starts_on' => '2026-10-10', 'ends_on' => '2026-10-01',
            ]))->assertUnprocessable()->assertJsonValidationErrors(['employee_name', 'ends_on']);
            $this->assertStringNotContainsString('validation.', $response->getContent());
        }
        $this->postJson('/api/admin/personnel-register/leave', $this->payload('leave', ['leave_type' => '']))->assertUnprocessable()->assertJsonValidationErrors('leave_type');
        $this->postJson('/api/admin/personnel-register/sick_leave', $this->payload(overrides: ['starts_on' => 'not-a-date']))->assertUnprocessable();
        $this->assertDatabaseCount('personnel_register_entries', 0);
    }

    public function test_type_specific_fields_and_audit_fields_cannot_be_forged(): void
    {
        $admin = $this->admin();
        $this->postJson('/api/admin/personnel-register/leave', $this->payload('leave', ['diagnosis' => 'hidden']))->assertUnprocessable();
        $this->postJson('/api/admin/personnel-register/sick_leave', $this->payload(overrides: ['leave_type' => 'Wrong']))->assertUnprocessable();
        $id = $this->create(overrides: ['type' => 'leave', 'created_by' => 999, 'updated_by' => 999]);
        $this->assertDatabaseHas('personnel_register_entries', ['id' => $id, 'type' => 'sick_leave', 'created_by' => $admin->id, 'updated_by' => $admin->id]);
    }

    public function test_search_is_partial_case_insensitive_and_supports_literal_wildcards(): void
    {
        $this->admin();
        $first = $this->create(overrides: ['document_number' => 'ЛН_10%']);
        $this->create(overrides: ['employee_name' => 'Иванов Иван', 'position_name' => 'Директор', 'document_number' => 'ЛН-20']);
        foreach (['АЙГУЛЬ', 'серикова', 'ведущий', 'ЛН_10%'] as $search) {
            $this->getJson('/api/admin/personnel-register/sick_leave?q='.urlencode($search))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $first);
        }
        $this->getJson('/api/admin/personnel-register/sick_leave?q=not-found')->assertOk()->assertJsonPath('total', 0);
    }

    public function test_date_filter_uses_overlapping_periods_and_supports_one_sided_ranges(): void
    {
        $this->admin();
        $this->create();
        $this->create(overrides: ['starts_on' => '2026-09-01', 'ends_on' => '2026-09-30']);
        $this->create(overrides: ['starts_on' => '2026-10-20', 'ends_on' => '2026-10-25']);
        $base = '/api/admin/personnel-register/sick_leave';
        $this->getJson($base.'?date_from=2026-10-05&date_to=2026-10-15')->assertOk()->assertJsonPath('total', 1);
        $this->getJson($base.'?date_to=2026-10-01')->assertOk()->assertJsonPath('total', 2);
        $this->getJson($base.'?date_from=2026-10-10')->assertOk()->assertJsonPath('total', 2);
        $this->getJson($base.'?date_from=2026-10-10&date_to=2026-10-01')->assertUnprocessable();
    }

    public function test_pagination_is_bounded_and_filters_are_applied_before_pagination(): void
    {
        $this->admin();
        for ($index = 0; $index < 26; $index++) {
            $entry = new PersonnelRegisterEntry($this->payload(overrides: ['employee_name' => 'Серикова '.$index]));
            $entry->type = 'sick_leave';
            $entry->save();
        }
        $this->getJson('/api/admin/personnel-register/sick_leave?page=2')->assertOk()->assertJsonPath('total', 26)->assertJsonCount(1, 'data')->assertJsonPath('current_page', 2);
        $this->getJson('/api/admin/personnel-register/sick_leave?per_page=10&q='.urlencode('серикова'))->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('last_page', 3);
        $this->getJson('/api/admin/personnel-register/sick_leave?per_page=999')->assertUnprocessable();
    }

    public function test_update_preserves_type_creator_and_records_the_editor(): void
    {
        $creator = $this->admin();
        $id = $this->create();
        $editor = $this->admin();
        $this->putJson('/api/admin/personnel-register/sick_leave/'.$id, $this->payload(overrides: ['diagnosis' => 'Изменён', 'type' => 'leave', 'created_by' => $editor->id]))
            ->assertOk()->assertJsonPath('diagnosis', 'Изменён');
        $this->assertDatabaseHas('personnel_register_entries', ['id' => $id, 'type' => 'sick_leave', 'created_by' => $creator->id, 'updated_by' => $editor->id]);
    }

    public function test_delete_hides_record_without_destroying_it(): void
    {
        $this->admin();
        $id = $this->create();
        $url = '/api/admin/personnel-register/sick_leave';
        $this->deleteJson($url.'/'.$id)->assertOk();
        $this->assertSoftDeleted('personnel_register_entries', ['id' => $id]);
        $this->getJson($url)->assertOk()->assertJsonPath('total', 0);
        $this->getJson($url.'/'.$id)->assertNotFound();
        $this->assertSame('Тестовый диагноз', PersonnelRegisterEntry::withTrashed()->findOrFail($id)->diagnosis);
    }

    public function test_web_pages_render_without_inlining_medical_records(): void
    {
        $this->admin();
        $id = $this->create();
        foreach (['/admin/sick-leaves', '/admin/leaves', '/admin/sick-leaves/create', '/admin/leaves/create', '/admin/sick-leaves/'.$id, '/admin/sick-leaves/'.$id.'/edit'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('Тестовый диагноз')->assertDontSee('Серикова Айгуль');
        }
    }
}
