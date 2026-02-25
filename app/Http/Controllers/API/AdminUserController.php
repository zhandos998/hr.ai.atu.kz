<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    private const ALLOWED_ROLES = ['user', 'lawyer', 'admin'];

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $role = trim((string) $request->query('role', ''));
        $emailVerified = trim((string) $request->query('email_verified', ''));
        $commission = trim((string) $request->query('commission', ''));

        $users = User::query()
            ->with([
                'positions:id,name,department_id',
                'positions.department:id,name',
                'commissionMember:id,user_id',
                'commissionVacancies:id',
            ])
            ->select('id', 'name', 'email', 'phone', 'role', 'email_verified_at', 'created_at')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->when($role !== '', function ($query) use ($role) {
                $query->where('role', $role);
            })
            ->when($emailVerified === 'yes', function ($query) {
                $query->whereNotNull('email_verified_at');
            })
            ->when($emailVerified === 'no', function ($query) {
                $query->whereNull('email_verified_at');
            })
            ->when($commission === 'yes', function ($query) {
                $query->where(function ($inner) {
                    $inner
                        ->whereHas('commissionMember')
                        ->orWhereHas('commissionVacancies');
                });
            })
            ->when($commission === 'no', function ($query) {
                $query->whereDoesntHave('commissionMember')
                    ->whereDoesntHave('commissionVacancies');
            })
            ->orderBy('name')
            ->limit(300)
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                    'is_commission_member' => $user->is_commission_member,
                    'positions' => $user->positions->map(function ($position) {
                        return [
                            'id' => $position->id,
                            'name' => $position->name,
                            'department' => $position->department ? [
                                'id' => $position->department->id,
                                'name' => $position->department->name,
                            ] : null,
                        ];
                    })->values(),
                ];
            })
            ->values();

        return response()->json($users);
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(self::ALLOWED_ROLES)],
        ]);

        $user = User::query()->findOrFail($id);

        if ((int) $request->user()->id === (int) $user->id) {
            return response()->json([
                'message' => 'Нельзя менять роль текущему администратору.',
            ], 422);
        }

        $user->role = $validated['role'];
        $user->save();

        return response()->json([
            'message' => 'Роль пользователя обновлена.',
            'user' => $user->fresh(['positions.department', 'commissionMember', 'commissionVacancies:id']),
        ]);
    }
}
