<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FacultyDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_faculty_list_excludes_chairs_and_keeps_the_default_flat_api_for_application_forms(): void
    {
        $faculty = AcademicUnit::create(['name' => 'Faculty One', 'kind' => 'faculty']);
        AcademicUnit::create(['name' => 'Faculty Two', 'kind' => 'faculty']);
        AcademicUnit::create(['name' => 'Chair', 'kind' => 'chair', 'parent_id' => $faculty->id]);

        $this->getJson('/api/admin/academic-units?kind=faculty')->assertOk()->assertJsonCount(2)
            ->assertJsonPath('0.kind', 'faculty')->assertJsonPath('0.children_count', 1)
            ->assertJsonPath('1.kind', 'faculty')->assertJsonPath('1.children_count', 0);
        $this->getJson('/api/admin/academic-units')->assertOk()->assertJsonCount(3);
        $this->getJson('/api/admin/academic-units?kind=chair')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Chair');
        $this->getJson('/api/admin/academic-units?kind=wrong')->assertUnprocessable();
    }

    public function test_opening_a_faculty_returns_only_its_chairs_with_position_counts_without_changing_structure(): void
    {
        $faculty = AcademicUnit::create(['name' => 'Faculty', 'kind' => 'faculty']);
        $other = AcademicUnit::create(['name' => 'Other Faculty', 'kind' => 'faculty']);
        AcademicUnit::create(['name' => 'Chair Z', 'kind' => 'chair', 'parent_id' => $faculty->id]);
        $chair = AcademicUnit::create(['name' => 'Chair A', 'kind' => 'chair', 'parent_id' => $faculty->id]);
        AcademicUnit::create(['name' => 'Other Chair', 'kind' => 'chair', 'parent_id' => $other->id]);
        Position::create(['name' => 'Assistant', 'academic_unit_id' => $chair->id]);
        $before = AcademicUnit::orderBy('id')->get()->toArray();

        $this->getJson('/api/admin/academic-units/'.$faculty->id)->assertOk()
            ->assertJsonPath('name', 'Faculty')->assertJsonCount(2, 'children')
            ->assertJsonPath('children.0.name', 'Chair A')->assertJsonPath('children.0.positions_count', 1)
            ->assertJsonPath('children.1.name', 'Chair Z')->assertJsonPath('children.1.positions_count', 0)
            ->assertJsonMissing(['name' => 'Other Chair']);
        $this->assertSame($before, AcademicUnit::orderBy('id')->get()->toArray());
        $empty = AcademicUnit::create(['name' => 'Empty Faculty', 'kind' => 'faculty']);
        $this->getJson('/api/admin/academic-units/'.$empty->id)->assertOk()->assertJsonCount(0, 'children');
        $this->getJson('/api/admin/academic-units/'.$chair->id)->assertNotFound();
        $this->getJson('/api/admin/academic-units/999999')->assertNotFound();
    }

    public function test_faculty_directory_has_a_dedicated_page_and_admin_only_data(): void
    {
        $faculty = AcademicUnit::create(['name' => 'Faculty', 'kind' => 'faculty']);
        $this->get('/admin/academic-units')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/AcademicUnits/Index'));
        $this->get('/admin/academic-units/'.$faculty->id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/AcademicUnits/Index')->where('facultyId', $faculty->id));
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/admin/academic-units?kind=faculty')->assertForbidden();
        $this->getJson('/api/admin/academic-units/'.$faculty->id)->assertForbidden();
    }
}
