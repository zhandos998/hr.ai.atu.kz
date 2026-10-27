<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FacultyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_get_faculties_with_chairs_and_other_is_last(): void
    {
        $other = AcademicUnit::create(['name' => 'Другое', 'kind' => 'faculty']);
        $second = AcademicUnit::create(['name' => 'Faculty B', 'kind' => 'faculty']);
        $first = AcademicUnit::create(['name' => 'Faculty A', 'kind' => 'faculty', 'description' => 'Internal description']);
        $secondChair = AcademicUnit::create(['name' => 'Chair B', 'kind' => 'chair', 'parent_id' => $first->id]);
        $firstChair = AcademicUnit::create(['name' => 'Chair A', 'kind' => 'chair', 'parent_id' => $first->id]);
        $kshit = AcademicUnit::create(['name' => 'КШИТ', 'kind' => 'chair', 'parent_id' => $other->id]);
        Department::create(['name' => 'Administrative department']);
        Position::create(['name' => 'Assistant', 'academic_unit_id' => $firstChair->id, 'duties' => 'Internal duties']);

        DB::enableQueryLog();
        $this->getJson('/api/faculties')->assertOk()->assertExactJson([
            'data' => [
                ['id' => $first->id, 'name' => 'Faculty A', 'departments' => [
                    ['id' => $firstChair->id, 'name' => 'Chair A', 'faculty_id' => $first->id],
                    ['id' => $secondChair->id, 'name' => 'Chair B', 'faculty_id' => $first->id],
                ]],
                ['id' => $second->id, 'name' => 'Faculty B', 'departments' => []],
                ['id' => $other->id, 'name' => 'Другое', 'departments' => [
                    ['id' => $kshit->id, 'name' => 'КШИТ', 'faculty_id' => $other->id],
                ]],
            ],
        ]);
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "academic_units"'));
        DB::disableQueryLog();
        $this->assertSame(2, $queries->count());
    }

    public function test_guest_can_get_only_the_selected_facultys_chairs(): void
    {
        $first = AcademicUnit::create(['name' => 'Faculty A', 'kind' => 'faculty']);
        $second = AcademicUnit::create(['name' => 'Faculty B', 'kind' => 'faculty']);
        $chair = AcademicUnit::create(['name' => 'Chair A', 'kind' => 'chair', 'parent_id' => $first->id]);
        AcademicUnit::create(['name' => 'Chair B', 'kind' => 'chair', 'parent_id' => $second->id]);
        $this->getJson("/api/faculties/{$first->id}/departments")->assertOk()->assertExactJson([
            'faculty' => ['id' => $first->id, 'name' => 'Faculty A'],
            'data' => [['id' => $chair->id, 'name' => 'Chair A', 'faculty_id' => $first->id]],
        ]);
    }

    public function test_missing_faculty_and_chair_id_and_non_numeric_id_return_not_found(): void
    {
        $faculty = AcademicUnit::create(['name' => 'Faculty', 'kind' => 'faculty']);
        $chair = AcademicUnit::create(['name' => 'Chair', 'kind' => 'chair', 'parent_id' => $faculty->id]);
        foreach ([$chair->id, 999999, 'invalid'] as $id) {
            $this->getJson("/api/faculties/{$id}/departments")->assertNotFound();
        }
    }

    public function test_empty_directory_and_empty_faculty_return_empty_lists(): void
    {
        $this->getJson('/api/faculties')->assertOk()->assertExactJson(['data' => []]);
        $faculty = AcademicUnit::create(['name' => 'Empty faculty', 'kind' => 'faculty']);
        $this->getJson("/api/faculties/{$faculty->id}/departments")->assertOk()->assertJsonPath('data', []);
    }

    public function test_public_api_is_read_only_and_admin_api_stays_protected(): void
    {
        $this->postJson('/api/faculties', ['name' => 'New faculty'])->assertMethodNotAllowed();
        $this->getJson('/api/admin/academic-units')->assertUnauthorized();
        $this->postJson('/api/admin/academic-units', ['name' => 'New faculty'])->assertUnauthorized();
        $this->assertDatabaseCount('academic_units', 0);
    }
}
