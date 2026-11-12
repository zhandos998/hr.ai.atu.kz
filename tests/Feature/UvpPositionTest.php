<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Position;
use App\Models\UvpPosition;
use Database\Seeders\UvpPositionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UvpPositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_uvp_catalog_contains_the_seven_requested_positions(): void
    {
        $this->seed(UvpPositionSeeder::class);

        $this->assertSame([
            'Заведующий лабораторией',
            'Мастер производственного обучения',
            'Старший лаборант',
            'Лаборант',
            'Механик',
            'Инженер',
            'Инженер ВКК',
        ], UvpPosition::query()->orderBy('id')->pluck('name')->all());
    }

    public function test_reseeding_preserves_ids_custom_entries_and_the_existing_positions_catalog(): void
    {
        $department = Department::create(['name' => 'Test department']);
        $position = Position::create(['name' => 'Инженер', 'department_id' => $department->id]);
        $custom = UvpPosition::create(['name' => 'Техник']);
        $existing = UvpPosition::create(['name' => 'Инженер']);
        $before = $position->fresh()->getAttributes();

        $this->seed(UvpPositionSeeder::class);
        $catalog = UvpPosition::query()->orderBy('id')->get()->toArray();
        $this->seed(UvpPositionSeeder::class);

        $this->assertSame($catalog, UvpPosition::query()->orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('uvp_positions', 8);
        $this->assertSame($existing->id, UvpPosition::where('name', 'Инженер')->sole()->id);
        $this->assertDatabaseHas('uvp_positions', ['id' => $custom->id, 'name' => 'Техник']);
        $this->assertSame($before, $position->fresh()->getAttributes());
        $this->assertDatabaseCount('positions', 1);
    }

    public function test_duplicate_names_are_rejected_by_the_database(): void
    {
        UvpPosition::create(['name' => 'Инженер']);

        $this->expectException(QueryException::class);
        UvpPosition::create(['name' => 'Инженер']);
    }
}
