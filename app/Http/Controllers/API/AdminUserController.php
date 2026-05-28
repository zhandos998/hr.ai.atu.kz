<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    private const ALLOWED_ROLES = ['user', 'lawyer', 'science_director', 'digital_director', 'strategy_director', 'academic_director', 'library_director', 'admin'];
    private const LEGACY_ROLE_ALIASES = [
        'compliance_director' => 'lawyer',
    ];

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
                'commissionMember:id,user_id,is_pps,is_staff',
                'commissionVacancies:id',
                'ppsFacultyCommissionMemberships:id,user_id',
            ])
            ->select('id', 'name', 'email', 'phone', 'role', 'email_verified_at', 'created_at')
            ->where(function ($query) {
                $query
                    ->whereNull('email')
                    ->orWhere('email', 'not like', 'manual-candidate-%@hr-ai.invalid');
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->when($role !== '', function ($query) use ($role) {
                $normalizedRole = self::normalizeRole($role);

                if ($normalizedRole === 'lawyer') {
                    $query->whereIn('role', User::LEGAL_ROLE_ALIASES);
                    return;
                }

                $query->where('role', $normalizedRole);
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
                        ->orWhereHas('commissionVacancies')
                        ->orWhereHas('ppsFacultyCommissionMemberships');
                });
            })
            ->when($commission === 'no', function ($query) {
                $query->whereDoesntHave('commissionMember')
                    ->whereDoesntHave('commissionVacancies')
                    ->whereDoesntHave('ppsFacultyCommissionMemberships');
            })
            ->orderBy('name')
            ->limit(300)
            ->get()
            ->map(fn (User $user) => $this->transformUser($user))
            ->values();

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:255', Rule::unique('users', 'phone')],
            'role' => ['required', 'string', Rule::in(array_merge(self::ALLOWED_ROLES, array_keys(self::LEGACY_ROLE_ALIASES)))],
            'password' => ['required', 'string', 'min:4', 'max:64'],
        ]);

        $user = User::query()->create([
            'name' => trim($validated['name']),
            'email' => mb_strtolower(trim($validated['email'])),
            'phone' => $this->emptyToNull($validated['phone'] ?? null),
            'role' => self::normalizeRole($validated['role']),
            'password' => Hash::make($validated['password']),
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Пользователь создан.',
            'user' => $this->transformUser(
                $user->fresh(['positions.department', 'commissionMember', 'commissionVacancies:id', 'ppsFacultyCommissionMemberships:id,user_id'])
            ),
        ], 201);
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(array_merge(self::ALLOWED_ROLES, array_keys(self::LEGACY_ROLE_ALIASES)))],
        ]);

        $user = User::query()->findOrFail($id);

        if ((int) $request->user()->id === (int) $user->id) {
            return response()->json([
                'message' => 'Нельзя менять роль текущему администратору.',
            ], 422);
        }

        $user->role = self::normalizeRole($validated['role']);
        $user->save();

        return response()->json([
            'message' => 'Роль пользователя обновлена.',
            'user' => $user->fresh(['positions.department', 'commissionMember', 'commissionVacancies:id', 'ppsFacultyCommissionMemberships:id,user_id']),
        ]);
    }

    private static function normalizeRole(?string $role): ?string
    {
        return self::LEGACY_ROLE_ALIASES[$role] ?? $role;
    }

    private function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => self::normalizeRole($user->role),
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
    }

    private function emptyToNull($value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
