<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    private const ALLOWED_ROLES = ['user', 'economist', 'lawyer', 'science_director', 'digital_director', 'strategy_director', 'academic_director', 'library_director', 'professional_development_director', 'admin'];

    private const LEGACY_ROLE_ALIASES = [
        'compliance_director' => 'lawyer',
    ];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'account_type' => ['nullable', Rule::in(['regular', 'technical'])],
            'paginate' => ['sometimes', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ]);
        $q = trim((string) $request->query('q', ''));
        $role = trim((string) $request->query('role', ''));
        $emailVerified = trim((string) $request->query('email_verified', ''));
        $commission = trim((string) $request->query('commission', ''));

        $query = User::query()
            ->with([
                'positions:id,name,department_id,academic_unit_id',
                'positions.department:id,name',
                'positions.academicUnit:id,name,parent_id',
                'commissionMember:id,user_id,is_pps,is_staff',
                'commissionVacancies:id',
                'ppsFacultyCommissionMemberships:id,user_id',
            ])
            ->select('id', 'name', 'email', 'phone', 'role', 'email_verified_at', 'created_at')
            ->where(function ($query) use ($request) {
                if ($request->query('account_type') === 'technical') {
                    $query->where('email', 'like', '%@hr-ai.invalid');

                    return;
                }
                $query
                    ->whereNull('email')
                    ->orWhere('email', 'not like', '%@hr-ai.invalid');
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
            ->orderBy('name')->orderBy('id');

        if ($request->boolean('paginate')) {
            $users = $query->paginate((int) $request->query('per_page', 25));

            return response()->json([
                'data' => $users->getCollection()->map(fn (User $user) => $this->transformUser($user))->values(),
                'meta' => ['current_page' => $users->currentPage(), 'last_page' => $users->lastPage(), 'per_page' => $users->perPage(), 'total' => $users->total()],
            ]);
        }

        $users = $query->limit(300)->get()->map(fn (User $user) => $this->transformUser($user))->values();

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'username' => ['prohibited'],
            'phone' => ['nullable', 'string', 'max:255'],
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
                $user->fresh(['positions.department', 'positions.academicUnit', 'commissionMember', 'commissionVacancies:id', 'ppsFacultyCommissionMemberships:id,user_id'])
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
            'user' => $user->fresh(['positions.department', 'positions.academicUnit', 'commissionMember', 'commissionVacancies:id', 'ppsFacultyCommissionMemberships:id,user_id']),
        ]);
    }

    public function positionOptions(): JsonResponse
    {
        $positions = Position::query()
            ->with([
                'department:id,name',
                'academicUnit:id,name,parent_id',
                'academicUnit.parent:id,name',
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'department_id', 'academic_unit_id'])
            ->map(function (Position $position) {
                $unit = $position->academicUnit ?: $position->department;

                return [
                    'id' => $position->id,
                    'name' => $position->name,
                    'unit_name' => $unit?->name,
                    'parent_name' => $position->academicUnit?->parent?->name,
                    'type' => $position->academic_unit_id ? 'pps' : 'staff',
                ];
            })
            ->values();

        return response()->json($positions);
    }

    public function assignPosition(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'position_id' => ['required', 'integer', 'exists:positions,id'],
        ]);
        $user = User::query()->findOrFail($id);

        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'Административной учётной записи нельзя назначить должность.',
            ], 422);
        }

        $user->positions()->syncWithoutDetaching([(int) $validated['position_id']]);

        return response()->json([
            'message' => 'Должность назначена пользователю.',
            'user' => $this->transformUser($this->userWithRelations($user->id)),
        ]);
    }

    public function detachPosition(int $id, int $positionId): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        Position::query()->findOrFail($positionId);
        $user->positions()->detach($positionId);

        return response()->json([
            'message' => 'Должность снята с пользователя.',
            'user' => $this->transformUser($this->userWithRelations($user->id)),
        ]);
    }

    public function impersonate(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        if (str_starts_with((string) $admin->currentAccessToken()?->name, 'impersonated_by_')) {
            return response()->json(['message' => 'Сначала вернитесь в свой аккаунт администратора.'], 422);
        }
        $user = User::query()
            ->with([
                'positions:id,name,department_id,academic_unit_id',
                'positions.department:id,name',
                'positions.academicUnit:id,name,parent_id',
                'commissionMember:id,user_id,is_pps,is_staff',
                'commissionVacancies:id',
                'ppsFacultyCommissionMemberships:id,user_id',
            ])
            ->findOrFail($id);

        if ((int) $admin->id === (int) $user->id) {
            return response()->json([
                'message' => 'Нельзя войти от имени текущего пользователя.',
            ], 422);
        }

        $token = $user->createToken("impersonated_by_{$admin->id}")->plainTextToken;

        return response()->json([
            'message' => "Вход выполнен от имени пользователя {$user->name}.",
            'token' => $token,
            'user' => [
                ...$this->transformUser($user),
                'has_personnel_approvals' => $user->has_personnel_approvals,
            ],
            'impersonator' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => self::normalizeRole($admin->role),
            ],
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
                    'academic_unit' => $position->academicUnit ? ['id' => $position->academicUnit->id, 'name' => $position->academicUnit->name] : null,
                    'department' => $position->department ? [
                        'id' => $position->department->id,
                        'name' => $position->department->name,
                    ] : null,
                ];
            })->values(),
        ];
    }

    private function userWithRelations(int $id): User
    {
        return User::query()
            ->with([
                'positions:id,name,department_id,academic_unit_id',
                'positions.department:id,name',
                'positions.academicUnit:id,name,parent_id',
                'commissionMember:id,user_id,is_pps,is_staff',
                'commissionVacancies:id',
                'ppsFacultyCommissionMemberships:id,user_id',
            ])
            ->findOrFail($id);
    }

    private function emptyToNull($value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
