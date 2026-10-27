<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AcademicUnit;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;

class FacultyController extends Controller
{
    public function index(): JsonResponse
    {
        $faculties = AcademicUnit::query()
            ->where('kind', 'faculty')
            ->with(['children' => fn (HasMany $query) => $query->where('kind', 'chair')
                ->select('id', 'name', 'parent_id')->orderBy('name')])
            ->orderByRaw("CASE WHEN name = 'Другое' THEN 1 ELSE 0 END")
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'data' => $faculties->map(fn (AcademicUnit $faculty) => [
                'id' => $faculty->id,
                'name' => $faculty->name,
                'departments' => $faculty->children->map(fn (AcademicUnit $chair) => $this->chairData($chair))->values(),
            ])->values(),
        ]);
    }

    public function departments(int $faculty): JsonResponse
    {
        $unit = AcademicUnit::query()->where('kind', 'faculty')->findOrFail($faculty, ['id', 'name']);
        $chairs = AcademicUnit::query()->where('kind', 'chair')->where('parent_id', $unit->id)
            ->orderBy('name')->get(['id', 'name', 'parent_id']);

        return response()->json([
            'faculty' => ['id' => $unit->id, 'name' => $unit->name],
            'data' => $chairs->map(fn (AcademicUnit $chair) => $this->chairData($chair))->values(),
        ]);
    }

    private function chairData(AcademicUnit $chair): array
    {
        return ['id' => $chair->id, 'name' => $chair->name, 'faculty_id' => $chair->parent_id];
    }
}
