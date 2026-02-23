<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CandidateAIResult;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\Request;

class AdminStructureController extends Controller
{
    public function departments()
    {
        $departments = Department::query()
            ->with(['positions' => function ($query) {
                $query->select('id', 'department_id', 'name', 'duties', 'qualification')->orderBy('name');
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return response()->json($departments);
    }

    public function storeDepartment(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
            'description' => 'nullable|string',
        ]);

        $department = Department::create($validated);

        return response()->json($department, 201);
    }

    public function updateDepartment(Request $request, int $id)
    {
        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:departments,name,' . $department->id,
            'description' => 'nullable|string',
        ]);

        $department->update($validated);

        return response()->json($department);
    }

    public function destroyDepartment(int $id)
    {
        $department = Department::withCount('positions')->findOrFail($id);

        if ($department->positions_count > 0) {
            return response()->json([
                'message' => 'Нельзя удалить отдел, пока в нем есть должности.',
            ], 422);
        }

        $department->delete();

        return response()->json(['message' => 'Отдел удален.']);
    }

    public function positions()
    {
        $positions = Position::query()
            ->with([
                'department:id,name',
                'users:id,name,email,phone',
            ])
            ->orderBy('name')
            ->get(['id', 'department_id', 'name', 'duties', 'qualification']);

        return response()->json($positions);
    }

    public function storePosition(Request $request)
    {
        $validated = $request->validate([
            'department_id' => 'required|integer|exists:departments,id',
            'name' => 'required|string|max:255',
            'duties' => 'nullable|string',
            'qualification' => 'nullable|string',
        ]);

        $exists = Position::query()
            ->where('department_id', $validated['department_id'])
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Такая должность в этом отделе уже существует.',
            ], 422);
        }

        $position = Position::create($validated)->load('department:id,name');

        return response()->json($position, 201);
    }

    public function updatePosition(Request $request, int $id)
    {
        $position = Position::findOrFail($id);

        $validated = $request->validate([
            'department_id' => 'required|integer|exists:departments,id',
            'name' => 'required|string|max:255',
            'duties' => 'nullable|string',
            'qualification' => 'nullable|string',
        ]);

        $exists = Position::query()
            ->where('department_id', $validated['department_id'])
            ->where('name', $validated['name'])
            ->where('id', '!=', $position->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Такая должность в этом отделе уже существует.',
            ], 422);
        }

        $position->update($validated);

        return response()->json($position->load('department:id,name'));
    }

    public function destroyPosition(int $id)
    {
        $position = Position::findOrFail($id);

        $usedInCandidateResults = CandidateAIResult::query()
            ->where('position_id', $position->id)
            ->exists();

        if ($usedInCandidateResults) {
            return response()->json([
                'message' => 'Нельзя удалить должность, она используется в результатах AI-оценки.',
            ], 422);
        }

        $position->delete();

        return response()->json(['message' => 'Должность удалена.']);
    }

    public function positionUsers(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->select('id', 'name', 'email', 'phone')
            ->where(function ($query) {
                $query
                    ->whereNull('role')
                    ->orWhereNotIn('role', ['admin', 'lawyer']);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(200)
            ->get();

        return response()->json($users);
    }

    public function attachUserToPosition(Request $request, int $id)
    {
        $position = Position::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $allowedUserExists = User::query()
            ->where('id', $validated['user_id'])
            ->where(function ($query) {
                $query
                    ->whereNull('role')
                    ->orWhereNotIn('role', ['admin', 'lawyer']);
            })
            ->exists();

        if (!$allowedUserExists) {
            return response()->json([
                'message' => 'Нельзя привязать выбранного пользователя к должности.',
            ], 422);
        }

        $position->users()->syncWithoutDetaching([$validated['user_id']]);

        return response()->json([
            'message' => 'Пользователь добавлен к должности.',
            'position' => $position->fresh()->load('users:id,name,email,phone'),
        ]);
    }

    public function detachUserFromPosition(int $id, int $userId)
    {
        $position = Position::findOrFail($id);
        $position->users()->detach($userId);

        return response()->json([
            'message' => 'Пользователь удален из должности.',
            'position' => $position->fresh()->load('users:id,name,email,phone'),
        ]);
    }
}
