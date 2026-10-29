<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentSubtreePositionsTest extends TestCase
{
    use RefreshDatabase;

    private function hierarchy(): array
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $root = Department::create(['name' => 'Департамент']);
        $child = Department::create(['name' => 'Отдел', 'parent_id' => $root->id]);
        $grandchild = Department::create(['name' => 'Сектор', 'parent_id' => $child->id]);
        $sibling = Department::create(['name' => 'Другой отдел', 'parent_id' => $root->id]);
        $outside = Department::create(['name' => 'Другой департамент']);
        $positions = collect([$root, $child, $grandchild, $sibling, $outside])->mapWithKeys(fn ($department) => [
            $department->id => Position::create(['name' => 'Специалист '.$department->name, 'department_id' => $department->id]),
        ]);

        return [$root, $child, $grandchild, $sibling, $outside, $positions];
    }

    public function test_details_include_every_descendant_with_real_department_and_assigned_users(): void
    {
        [$root, $child, $grandchild, $sibling, $outside, $positions] = $this->hierarchy();
        $employee = User::factory()->create(['name' => 'Сотрудник сектора']);
        $positions[$grandchild->id]->users()->attach($employee);
        $response = $this->getJson('/api/admin/departments/'.$root->id)->assertOk()->assertJsonCount(4, 'positions');
        $items = collect($response->json('positions'))->keyBy('department_id');
        $this->assertEqualsCanonicalizing([$root->id, $child->id, $grandchild->id, $sibling->id], $items->keys()->all());
        $this->assertSame('Сектор', $items[$grandchild->id]['department']['name']);
        $this->assertSame($grandchild->id, $items[$grandchild->id]['department']['id']);
        $this->assertSame($employee->id, $items[$grandchild->id]['users'][0]['id']);
        $this->assertFalse($items->has($outside->id));
        $this->assertSame($grandchild->id, $positions[$grandchild->id]->fresh()->department_id);
    }

    public function test_child_page_excludes_ancestors_and_sibling_branches(): void
    {
        [$root, $child, $grandchild] = $this->hierarchy();
        $response = $this->getJson('/api/admin/departments/'.$child->id)->assertOk()->assertJsonCount(2, 'positions');
        $this->assertEqualsCanonicalizing([$child->id, $grandchild->id], array_column($response->json('positions'), 'department_id'));
    }

    public function test_directory_counts_match_subtree_details_without_changing_direct_positions(): void
    {
        [$root, $child, $grandchild, $sibling, $outside] = $this->hierarchy();
        $items = collect($this->getJson('/api/admin/departments')->assertOk()->json())->keyBy('id');
        foreach ([$root->id => 4, $child->id => 2, $grandchild->id => 1, $sibling->id => 1, $outside->id => 1] as $id => $count) {
            $this->assertSame($count, $items[$id]['subtree_positions_count']);
            $this->assertCount(1, $items[$id]['positions']);
        }
    }

    public function test_empty_root_still_includes_positions_when_only_descendants_have_them(): void
    {
        [$root, $child, $grandchild, $sibling, $outside, $positions] = $this->hierarchy();
        $positions[$root->id]->delete();
        $this->getJson('/api/admin/departments/'.$root->id)->assertOk()->assertJsonCount(3, 'positions');
        $empty = Department::create(['name' => 'Пустой департамент']);
        $this->getJson('/api/admin/departments/'.$empty->id)->assertOk()->assertJsonCount(0, 'positions');
        $this->getJson('/api/admin/departments/999999')->assertNotFound();
    }

    public function test_legacy_cycles_do_not_repeat_positions_or_hang(): void
    {
        [$root, $child, $grandchild] = $this->hierarchy();
        $root->update(['parent_id' => $grandchild->id]);
        $response = $this->getJson('/api/admin/departments/'.$root->id)->assertOk()->assertJsonCount(4, 'positions');
        $this->assertCount(4, array_unique(array_column($response->json('positions'), 'id')));
        $items = collect($this->getJson('/api/admin/departments')->assertOk()->json())->keyBy('id');
        $this->assertSame(4, $items[$root->id]['subtree_positions_count']);
    }

    public function test_deep_hierarchy_uses_a_bounded_number_of_queries(): void
    {
        [$root, $child, $grandchild] = $this->hierarchy();
        $parent = $grandchild;
        for ($index = 0; $index < 12; $index++) {
            $parent = Department::create(['name' => 'Уровень '.$index, 'parent_id' => $parent->id]);
            Position::create(['name' => 'Должность '.$index, 'department_id' => $parent->id]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/api/admin/departments/'.$root->id)->assertOk()->assertJsonCount(16, 'positions');
        $this->assertLessThanOrEqual(7, count(DB::getQueryLog()));
        DB::disableQueryLog();
    }
}
