<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PersonnelRegisterEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PersonnelRegisterController extends Controller
{
    public function index(Request $request, string $type): JsonResponse
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:255',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('date_from'), 'after_or_equal:date_from')],
            'page' => 'sometimes|integer|min:1',
            'per_page' => ['sometimes', 'integer', Rule::in([10, 25, 50, 100])],
        ]);
        $query = PersonnelRegisterEntry::where('type', $type);
        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            // SQLite's built-in LOWER does not case-fold Cyrillic; keep local and MySQL search consistent.
            $lower = 'LOWER';
            if (DB::connection()->getDriverName() === 'sqlite') {
                DB::connection()->getPdo()->sqliteCreateFunction('personnel_lower', fn ($value) => mb_strtolower($value ?? ''), 1);
                $lower = 'personnel_lower';
            }
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
            $query->where(function ($query) use ($lower, $pattern) {
                foreach (['employee_name', 'position_name', 'document_number'] as $column) {
                    $query->orWhereRaw("{$lower}({$column}) LIKE ? ESCAPE '!'", [$pattern]);
                }
            });
        }
        // Include every record whose absence period intersects the selected interval.
        $query->when($filters['date_from'] ?? null, fn ($query, $date) => $query->where('ends_on', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->where('starts_on', '<=', $date));

        return $this->json($query->latest('id')->paginate($filters['per_page'] ?? 25));
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $entry = new PersonnelRegisterEntry($this->validated($request, $type));
        $entry->type = $type;
        $entry->created_by = $request->user()->id;
        $entry->updated_by = $request->user()->id;
        $entry->save();

        return $this->json($entry, 201);
    }

    public function show(string $type, int $id): JsonResponse
    {
        return $this->json($this->entry($type, $id));
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $entry = $this->entry($type, $id);
        $entry->fill($this->validated($request, $type));
        $entry->updated_by = $request->user()->id;
        $entry->save();

        return $this->json($entry);
    }

    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        $entry = $this->entry($type, $id);
        $entry->updated_by = $request->user()->id;
        $entry->save();
        $entry->delete();

        return $this->json(['deleted' => true]);
    }

    private function entry(string $type, int $id): PersonnelRegisterEntry
    {
        return PersonnelRegisterEntry::where('type', $type)->findOrFail($id);
    }

    private function validated(Request $request, string $type): array
    {
        return $request->validate([
            'employee_name' => 'required|string|max:255',
            'position_name' => 'required|string|max:500',
            'document_number' => 'required|string|max:255',
            'starts_on' => 'required|date_format:Y-m-d',
            'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on',
            'diagnosis' => $type === 'sick_leave' ? 'nullable|string|max:2000' : 'prohibited',
            'leave_type' => $type === 'leave' ? 'required|string|max:255' : 'prohibited',
        ]);
    }

    private function json(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)->header('Cache-Control', 'no-store, private');
    }
}
