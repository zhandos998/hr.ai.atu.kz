<?php

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\FacultyDepartmentSeeder;
use Database\Seeders\PpsPositionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_academic_directory_is_separate_and_validates_hierarchy(): void
    {
        Department::create(['name' => 'HR']);
        $facultyId = $this->postJson('/api/admin/academic-units', ['name' => 'Факультет'])->assertCreated()->assertJsonPath('kind', 'faculty')->json('id');
        $chairId = $this->postJson('/api/admin/academic-units', ['name' => 'Кафедра', 'parent_id' => $facultyId])->assertCreated()->assertJsonPath('kind', 'chair')->json('id');
        $this->getJson('/api/admin/departments')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'HR');
        $this->getJson('/api/admin/academic-units')->assertOk()->assertJsonCount(2);
        $this->postJson('/api/admin/academic-units', ['name' => 'Invalid', 'parent_id' => $chairId])->assertUnprocessable();
        $this->putJson("/api/admin/academic-units/{$facultyId}", ['name' => 'Факультет', 'parent_id' => $facultyId])->assertUnprocessable();
        $this->deleteJson("/api/admin/academic-units/{$facultyId}")->assertUnprocessable();
        $this->putJson("/api/admin/academic-units/{$chairId}", ['name' => 'КШИТ', 'parent_id' => $facultyId])->assertOk();
        $this->deleteJson("/api/admin/academic-units/{$chairId}")->assertOk();
        $this->deleteJson("/api/admin/academic-units/{$facultyId}")->assertOk();
    }

    public function test_position_requires_exactly_one_valid_owner_and_updates_it(): void
    {
        $staff = Department::create(['name' => 'HR']);
        $faculty = AcademicUnit::create(['name' => 'Другое', 'kind' => 'faculty']);
        $chair = AcademicUnit::create(['name' => 'КШИТ', 'kind' => 'chair', 'parent_id' => $faculty->id]);
        $endpoint = '/api/admin/positions';
        $this->postJson($endpoint, ['name' => 'Invalid'])->assertUnprocessable();
        $this->postJson($endpoint, ['name' => 'Invalid', 'academic_unit_id' => $faculty->id])->assertUnprocessable();
        $this->postJson($endpoint, ['name' => 'Invalid', 'department_id' => $staff->id, 'academic_unit_id' => $chair->id])->assertUnprocessable();
        $id = $this->postJson($endpoint, ['name' => 'Ассистент', 'academic_unit_id' => $chair->id])->assertCreated()->json('id');
        $this->getJson($endpoint)->assertOk()->assertJsonPath('0.academic_unit.name', 'КШИТ')->assertJsonPath('0.department_id', null);
        $this->postJson($endpoint, ['name' => 'Ассистент', 'academic_unit_id' => $chair->id])->assertUnprocessable();
        $this->deleteJson("/api/admin/academic-units/{$chair->id}")->assertUnprocessable();
        $this->putJson("{$endpoint}/{$id}", ['name' => 'Специалист', 'department_id' => $staff->id])->assertOk()->assertJsonPath('academic_unit_id', null);
    }

    public function test_staff_position_scope_excludes_academic_positions(): void
    {
        $staff = Department::create(['name' => 'HR']);
        $faculty = AcademicUnit::create(['name' => 'Факультет', 'kind' => 'faculty']);
        $chair = AcademicUnit::create(['name' => 'Кафедра', 'kind' => 'chair', 'parent_id' => $faculty->id]);
        Position::create(['name' => 'Специалист', 'department_id' => $staff->id]);
        Position::create(['name' => 'Ассистент', 'academic_unit_id' => $chair->id]);

        $this->getJson('/api/admin/positions?scope=staff')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Специалист')
            ->assertJsonPath('0.academic_unit_id', null);

        $this->getJson('/api/admin/positions')->assertOk()->assertJsonCount(2);
    }

    public function test_pps_positions_are_grouped_by_unique_name(): void
    {
        $faculty = AcademicUnit::create(['name' => 'Факультет', 'kind' => 'faculty']);
        $firstChair = AcademicUnit::create(['name' => 'Кафедра 1', 'kind' => 'chair', 'parent_id' => $faculty->id]);
        $secondChair = AcademicUnit::create(['name' => 'Кафедра 2', 'kind' => 'chair', 'parent_id' => $faculty->id]);
        Position::create(['name' => 'Ассистент', 'academic_unit_id' => $firstChair->id]);
        Position::create(['name' => 'Ассистент', 'academic_unit_id' => $secondChair->id]);
        Position::create(['name' => 'Профессор', 'academic_unit_id' => $firstChair->id]);

        $this->getJson('/api/admin/pps-positions')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.name', 'Ассистент')
            ->assertJsonPath('0.chairs_count', 2)
            ->assertJsonPath('0.faculties_count', 1)
            ->assertJsonCount(2, '0.chairs');

        $this->get('/admin/pps-positions')->assertOk();
        $this->get('/admin/pps-positions/create')->assertOk();
    }

    public function test_admin_adds_one_pps_position_to_all_chairs(): void
    {
        $firstFaculty = AcademicUnit::create(['name' => 'Факультет 1', 'kind' => 'faculty']);
        $secondFaculty = AcademicUnit::create(['name' => 'Факультет 2', 'kind' => 'faculty']);
        $firstChair = AcademicUnit::create(['name' => 'Кафедра 1', 'kind' => 'chair', 'parent_id' => $firstFaculty->id]);
        $secondChair = AcademicUnit::create(['name' => 'Кафедра 2', 'kind' => 'chair', 'parent_id' => $secondFaculty->id]);

        $this->postJson('/api/admin/pps-positions', [
            'name' => 'Исполняющий обязанности заведующего кафедрой',
        ])->assertCreated()
            ->assertJsonPath('position.name', 'Исполняющий обязанности заведующего кафедрой')
            ->assertJsonPath('position.chairs_count', 2)
            ->assertJsonPath('position.faculties_count', 2);

        $this->assertDatabaseHas('positions', [
            'name' => 'Исполняющий обязанности заведующего кафедрой',
            'academic_unit_id' => $firstChair->id,
            'department_id' => null,
        ]);
        $this->assertDatabaseHas('positions', [
            'name' => 'Исполняющий обязанности заведующего кафедрой',
            'academic_unit_id' => $secondChair->id,
            'department_id' => null,
        ]);
        $this->assertSame(2, Position::query()
            ->where('name', 'Исполняющий обязанности заведующего кафедрой')
            ->count());

        $this->postJson('/api/admin/pps-positions', [
            'name' => 'Исполняющий обязанности заведующего кафедрой',
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_department_details_include_only_its_positions_and_users(): void
    {
        $department = Department::create(['name' => 'HR']);
        $otherDepartment = Department::create(['name' => 'Finance']);
        $user = User::factory()->create(['name' => 'Сотрудник HR']);
        $position = Position::create(['name' => 'HR менеджер', 'department_id' => $department->id]);
        Position::create(['name' => 'Бухгалтер', 'department_id' => $otherDepartment->id]);
        $position->users()->attach($user);

        $this->getJson("/api/admin/departments/{$department->id}")
            ->assertOk()
            ->assertJsonPath('name', 'HR')
            ->assertJsonCount(1, 'positions')
            ->assertJsonPath('positions.0.name', 'HR менеджер')
            ->assertJsonPath('positions.0.users.0.name', 'Сотрудник HR');

        $this->get("/admin/departments/{$department->id}")->assertOk();

        $this->getJson("/api/admin/positions/{$position->id}")
            ->assertOk()
            ->assertJsonPath('name', 'HR менеджер')
            ->assertJsonPath('department.name', 'HR')
            ->assertJsonPath('users.0.name', 'Сотрудник HR');

        $this->get("/admin/positions/{$position->id}")->assertOk();
    }

    public function test_pps_seeders_are_idempotent_and_do_not_recreate_departments(): void
    {
        $this->seed([FacultyDepartmentSeeder::class, PpsPositionSeeder::class]);
        $positionIds = Position::pluck('id')->all();
        $this->seed([FacultyDepartmentSeeder::class, PpsPositionSeeder::class]);
        $this->assertDatabaseCount('departments', 0);
        $this->assertSame(7, AcademicUnit::where('kind', 'faculty')->count());
        $this->assertSame(21, AcademicUnit::where('kind', 'chair')->count());
        $this->assertSame($positionIds, Position::pluck('id')->all());
        $kshit = AcademicUnit::where('name', 'КШИТ')->firstOrFail();
        $this->assertSame(15, $kshit->positions()->count());
    }

    public function test_pps_creation_rejects_wrong_faculty_and_staff_positions(): void
    {
        $faculty = AcademicUnit::create(['name' => 'Другое', 'kind' => 'faculty']);
        $chair = AcademicUnit::create(['name' => 'КШИТ', 'kind' => 'chair', 'parent_id' => $faculty->id]);
        $department = Department::create(['name' => 'HR']);
        $position = Position::create(['name' => 'Специалист', 'department_id' => $department->id]);
        $payload = ['full_name' => 'Test', 'vacancy_type' => 'pps', 'faculty_name' => $faculty->name, 'department_name' => $chair->name, 'position_id' => $position->id];
        $this->postJson('/api/admin/applications', $payload)->assertUnprocessable();
        $payload['position_id'] = Position::create(['name' => 'Ассистент', 'academic_unit_id' => $chair->id])->id;
        $payload['faculty_name'] = 'Wrong faculty';
        $this->postJson('/api/admin/applications', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_non_admin_cannot_access_academic_directory(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/admin/academic-units')->assertForbidden();
        $this->postJson('/api/admin/academic-units', ['name' => 'Invalid'])->assertForbidden();
        $this->postJson('/api/admin/pps-positions', ['name' => 'Invalid'])->assertForbidden();
    }
}
