<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AcademicUnit;
use App\Models\PersonnelRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AcademicStructureController extends Controller
{
    public function index()
    {
        return response()->json(AcademicUnit::with('positions:id,academic_unit_id,name,duties,qualification')
            ->orderByRaw("CASE WHEN name = 'Другое' THEN 1 ELSE 0 END")
            ->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        return response()->json(AcademicUnit::create($this->validated($request)), 201);
    }

    public function update(Request $request, int $id)
    {
        $unit = AcademicUnit::findOrFail($id);
        $data = $this->validated($request, $unit);
        if ($data['kind'] !== $unit->kind && ($unit->children()->exists() || $unit->positions()->exists())) {
            return response()->json(['message' => 'Нельзя изменить тип: есть кафедры или должности.'], 422);
        }
        $unit->update($data);

        return response()->json($unit);
    }

    public function destroy(int $id)
    {
        $unit = AcademicUnit::findOrFail($id);
        if (PersonnelRequest::where('faculty_id', $id)->orWhere('academic_unit_id', $id)->orWhere('target_faculty_id', $id)->orWhere('target_academic_unit_id', $id)->exists()) {
            return response()->json(['message' => __('messages.personnel_unit_in_use')], 422);
        }
        if ($unit->children()->exists() || $unit->positions()->exists()) {
            return response()->json(['message' => 'Нельзя удалить: есть кафедры или должности.'], 422);
        }
        $unit->delete();

        return response()->json(['message' => 'Запись удалена.']);
    }

    private function validated(Request $request, ?AcademicUnit $unit = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('academic_units')->ignore($unit?->id)],
            'parent_id' => ['nullable', 'integer', Rule::exists('academic_units', 'id')->where('kind', 'faculty'), Rule::notIn([$unit?->id ?? 0])],
        ]);
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['kind'] = $data['parent_id'] ? 'chair' : 'faculty';
        $data['description'] = $data['kind'] === 'faculty' ? 'Факультет' : 'Кафедра';

        return $data;
    }
}
