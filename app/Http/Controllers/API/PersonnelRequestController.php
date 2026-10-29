<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AcademicUnit;
use App\Models\Department;
use App\Models\PersonnelActionApprovalRoute;
use App\Models\PersonnelRequest;
use App\Models\PersonnelRequestApproval;
use App\Models\PersonnelRequestDocument;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PersonnelRequestController extends Controller
{
    private const ACTION_TYPES = [
        PersonnelRequest::ACTION_HIRE,
        PersonnelRequest::ACTION_DISMISSAL,
        PersonnelRequest::ACTION_TRANSFER,
        PersonnelRequest::ACTION_MATERNITY_LEAVE,
    ];

    public function publicStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_name' => ['required', 'string', 'min:2', 'max:255', "regex:/^[\pL\pM\s'’-]+$/u"],
        ], [
            'employee_name.regex' => 'Введите фамилию, имя или ФИО без специальных символов.',
        ]);
        $searchWords = preg_split(
            '/\s+/u',
            preg_replace('/\s+/u', ' ', trim($validated['employee_name'])),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $items = PersonnelRequest::query()
            ->whereNull('archived_at')
            ->where(function ($query) use ($searchWords) {
                foreach ($searchWords as $word) {
                    $query->where('employee_name', 'like', "%{$word}%");
                }
            })
            ->with([
                'department:id,name',
                'position:id,name',
                'targetDepartment:id,name',
                'targetPosition:id,name',
                'faculty:id,name', 'academicUnit:id,name', 'targetFaculty:id,name', 'targetAcademicUnit:id,name',
                'approvals.user:id,name,contact_info',
                'approvals.user.positions:id,name,department_id,academic_unit_id',
                'approvals.user.positions.department:id,name',
                'approvals.user.positions.academicUnit:id,name,parent_id',
                'approvals.user.positions.academicUnit.parent:id,name',
            ])
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (PersonnelRequest $item) => [
                'id' => $item->id,
                'employee_name' => $item->employee_name,
                'action_type' => $item->action_type,
                'employee_type' => $item->employee_type,
                'faculty' => $item->faculty?->only(['id', 'name']),
                'academic_unit' => $item->academicUnit?->only(['id', 'name']),
                'target_faculty' => $item->targetFaculty?->only(['id', 'name']),
                'target_academic_unit' => $item->targetAcademicUnit?->only(['id', 'name']),
                'status' => $item->status,
                'created_at' => $item->created_at,
                'department' => $item->department?->only(['id', 'name']),
                'position' => $item->position?->only(['id', 'name']),
                'target_department' => $item->targetDepartment?->only(['id', 'name']),
                'target_position' => $item->targetPosition?->only(['id', 'name']),
                'approvals' => $item->approvals->map(fn (PersonnelRequestApproval $approval) => [
                    'id' => $approval->id,
                    'approval_order' => $approval->approval_order,
                    'decision' => $approval->decision,
                    'decided_at' => $approval->decided_at,
                    'user' => $approval->user ? [
                        'id' => $approval->user->id,
                        'name' => $approval->user->name,
                        'contact_info' => $approval->user->contact_info,
                        'positions' => $approval->user->positions->map(fn (Position $position) => [
                            'id' => $position->id,
                            'name' => $position->name,
                            'unit_name' => ($position->academicUnit ?: $position->department)?->name,
                            'parent_name' => $position->academicUnit?->parent?->name,
                            'type' => $position->academic_unit_id ? 'pps' : 'staff',
                        ])->values(),
                    ] : null,
                ])->values(),
            ])
            ->values();

        return response()->json(['data' => $items]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['employee_type' => ['nullable', Rule::in(PersonnelRequest::EMPLOYEE_TYPES)]]);
        $user = $request->user();
        $search = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $actionType = trim((string) $request->query('action_type', ''));

        $query = PersonnelRequest::query()
            ->when($filters['employee_type'] ?? null, fn ($query, $type) => $query->where('employee_type', $type))
            ->when($request->boolean('archived'), fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
            ->with([
                'department:id,name',
                'position:id,name',
                'targetDepartment:id,name',
                'targetPosition:id,name',
                'faculty:id,name', 'academicUnit:id,name', 'targetFaculty:id,name', 'targetAcademicUnit:id,name',
                'approvals.user:id,name,email',
            ]);

        if ($this->canViewFinancialData($user)) {
            $query->withCount('documents');
        }

        if (! $user->isAdmin()) {
            $query->whereHas('approvals', fn ($approvalQuery) => $approvalQuery->where('user_id', $user->id));
        }

        $query
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($inner) use ($search) {
                    $inner->where('employee_name', 'like', "%{$search}%")
                        ->orWhereHas('department', fn ($department) => $department->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('faculty', fn ($unit) => $unit->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('academicUnit', fn ($unit) => $unit->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('position', fn ($position) => $position->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true), fn ($builder) => $builder->where('status', $status))
            ->when(in_array($actionType, self::ACTION_TYPES, true), fn ($builder) => $builder->where('action_type', $actionType))
            ->latest('id');

        $items = $query->paginate(15)->withQueryString();

        $data = collect($items->items())
            ->map(fn (PersonnelRequest $item) => $this->applyFinancialVisibility(
                $this->withoutUserAppends($item),
                $user
            ))
            ->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $personnelRequest = $this->findAuthorized($request, $id);

        return response()->json($this->loadDetails($personnelRequest, $request->user()));
    }

    public function store(Request $request): JsonResponse
    {
        $employeeType = $request->input('employee_type', 'staff');
        $validated = $request->validate([
            'employee_type' => ['sometimes', 'required', Rule::in(PersonnelRequest::EMPLOYEE_TYPES)],
            ...$this->personnelStructureRules($request, $employeeType === 'pps'),
            'employee_name' => ['required', 'string', 'max:255'],
            'action_type' => ['required', Rule::in(self::ACTION_TYPES)],
            'salary' => ['nullable', 'required_if:action_type,hire', 'numeric', 'min:0', 'max:999999999999.99'],
            'work_experience' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:5000'],
            'approver_user_ids' => ['sometimes', 'required', 'array', 'min:1', 'max:100'],
            'approver_user_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'documents' => ['nullable', 'array', 'max:20'],
            'documents.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'extensions:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
        ]);

        $structure = $this->validatedPersonnelStructure($validated, $employeeType);

        $approverIds = array_key_exists('approver_user_ids', $validated)
            ? array_map('intval', $validated['approver_user_ids'])
            : $this->defaultApproverIds($validated['action_type']);
        $eligibleApproverCount = $this->personnelApproverCandidatesQuery()
            ->whereIn('id', $approverIds)
            ->count();

        if ($eligibleApproverCount !== count($approverIds)) {
            throw ValidationException::withMessages([
                'approver_user_ids' => ['В составе комиссии есть недоступный пользователь.'],
            ]);
        }

        $personnelRequest = DB::transaction(function () use ($request, $validated, $approverIds, $structure) {
            $personnelRequest = PersonnelRequest::query()->create([
                'employee_name' => trim($validated['employee_name']),
                'action_type' => $validated['action_type'],
                ...$structure,
                'salary' => $validated['action_type'] === PersonnelRequest::ACTION_HIRE ? $validated['salary'] : null,
                'work_experience' => $validated['action_type'] === PersonnelRequest::ACTION_HIRE
                    ? $this->emptyToNull($validated['work_experience'] ?? null)
                    : null,
                'details' => $this->emptyToNull($validated['details'] ?? null),
                'status' => 'pending',
                'created_by' => $request->user()->id,
            ]);

            foreach ($approverIds as $index => $approverId) {
                $personnelRequest->approvals()->create([
                    'user_id' => $approverId,
                    'approval_order' => $index + 1,
                    'decision' => 'pending',
                ]);
            }

            $this->storeDocuments($personnelRequest, $request->file('documents', []));

            return $personnelRequest;
        });

        return response()->json([
            'message' => 'Кадровая заявка создана.',
            'request' => $this->loadDetails($personnelRequest),
        ], 201);
    }

    public function archive(Request $request, int $id): JsonResponse
    {
        $item = PersonnelRequest::query()->findOrFail($id);
        if ($item->archived_at === null) {
            $item->update(['archived_at' => now(), 'archived_by' => $request->user()->id]);
        }

        return response()->json(['request' => $this->loadDetails($item)]);
    }

    public function restore(int $id): JsonResponse
    {
        $item = PersonnelRequest::query()->findOrFail($id);
        $item->update(['archived_at' => null, 'archived_by' => null]);

        return response()->json(['request' => $this->loadDetails($item)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $personnelRequest = PersonnelRequest::query()->findOrFail($id);
        $this->ensureEditable($personnelRequest);
        $employeeType = $personnelRequest->employee_type;
        $validated = $request->validate([
            'employee_type' => ['sometimes', 'required', Rule::in([$employeeType])],
            ...$this->personnelStructureRules($request, $employeeType === 'pps'),
            'employee_name' => ['required', 'string', 'max:255'],
            'action_type' => ['required', Rule::in(self::ACTION_TYPES)],
            'salary' => ['nullable', 'required_if:action_type,hire', 'numeric', 'min:0', 'max:999999999999.99'],
            'work_experience' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:5000'],
            'documents' => ['nullable', 'array', 'max:20'],
            'documents.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'extensions:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
        ]);

        $structure = $this->validatedPersonnelStructure($validated, $employeeType);

        $actionChanged = $validated['action_type'] !== $personnelRequest->action_type;
        $approverIds = $actionChanged ? $this->defaultApproverIds($validated['action_type']) : [];

        DB::transaction(function () use ($personnelRequest, $validated, $actionChanged, $approverIds, $request, $structure) {
            $personnelRequest->update([
                'employee_name' => trim($validated['employee_name']),
                'action_type' => $validated['action_type'],
                ...$structure,
                'salary' => $validated['action_type'] === PersonnelRequest::ACTION_HIRE ? $validated['salary'] : null,
                'work_experience' => $validated['action_type'] === PersonnelRequest::ACTION_HIRE
                    ? $this->emptyToNull($validated['work_experience'] ?? $personnelRequest->work_experience)
                    : null,
                'details' => $this->emptyToNull($validated['details'] ?? null),
            ]);

            if ($actionChanged) {
                $personnelRequest->approvals()->delete();
                foreach ($approverIds as $index => $approverId) {
                    $personnelRequest->approvals()->create([
                        'user_id' => $approverId,
                        'approval_order' => $index + 1,
                        'decision' => 'pending',
                    ]);
                }
                $personnelRequest->update(['status' => 'pending']);
            }

            $this->storeDocuments($personnelRequest, $request->file('documents', []));
        });

        return response()->json([
            'message' => 'Кадровая заявка обновлена.',
            'request' => $this->loadDetails($personnelRequest->fresh()),
        ]);
    }

    public function uploadDocuments(Request $request, int $id): JsonResponse
    {
        $personnelRequest = PersonnelRequest::query()->findOrFail($id);
        $this->ensureEditable($personnelRequest);
        $validated = $request->validate([
            'documents' => ['required', 'array', 'min:1', 'max:20'],
            'documents.*' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'extensions:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
        ]);

        $this->storeDocuments($personnelRequest, $request->file('documents', []));

        return response()->json([
            'message' => count($validated['documents']).' файл(ов) добавлено.',
            'request' => $this->loadDetails($personnelRequest->fresh()),
        ]);
    }

    public function destroyDocument(int $id, int $documentId): JsonResponse
    {
        $this->ensureEditable(PersonnelRequest::query()->findOrFail($id));
        $document = PersonnelRequestDocument::query()
            ->where('personnel_request_id', $id)
            ->findOrFail($documentId);

        Storage::disk('documents')->delete($document->path);
        $document->delete();

        return response()->json(['message' => 'Документ удалён.']);
    }

    public function addApprover(Request $request, int $id): JsonResponse
    {
        $personnelRequest = PersonnelRequest::query()->findOrFail($id);
        $this->ensureActive($personnelRequest);
        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('personnel_request_approvals')->where(
                    fn ($query) => $query->where('personnel_request_id', $personnelRequest->id)
                ),
            ],
            'approval_order' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (! $this->personnelApproverCandidatesQuery()->whereKey($validated['user_id'])->exists()) {
            throw ValidationException::withMessages(['user_id' => ['Этот пользователь недоступен для согласования.']]);
        }

        $approval = $personnelRequest->approvals()->create([
            'user_id' => $validated['user_id'],
            'approval_order' => $validated['approval_order']
                ?? (($personnelRequest->approvals()->max('approval_order') ?? 0) + 1),
            'decision' => 'pending',
        ]);
        $personnelRequest->refreshStatus();

        return response()->json([
            'message' => 'Участник добавлен в комиссию.',
            'approval' => $approval->load('user:id,name,email'),
            'request' => $this->loadDetails($personnelRequest->fresh()),
        ], 201);
    }

    public function removeApprover(int $id, int $approvalId): JsonResponse
    {
        $personnelRequest = PersonnelRequest::query()->findOrFail($id);
        $this->ensureActive($personnelRequest);
        $approval = $personnelRequest->approvals()->findOrFail($approvalId);
        if ($approval->decision !== 'pending' || $approval->decided_at !== null) {
            throw ValidationException::withMessages([
                'approval' => ['Нельзя удалить участника, который уже принял решение.'],
            ]);
        }
        if ($personnelRequest->approvals()->count() <= 1) {
            throw ValidationException::withMessages([
                'approval' => ['В комиссии должен остаться хотя бы один участник.'],
            ]);
        }

        $approval->delete();
        $personnelRequest->refreshStatus();

        return response()->json([
            'message' => 'Участник удалён из комиссии.',
            'request' => $this->loadDetails($personnelRequest->fresh()),
        ]);
    }

    public function decide(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'comment' => ['nullable', 'string', 'max:2000', 'required_if:decision,rejected'],
        ]);

        $personnelRequest = $this->findAuthorized($request, $id);
        $this->ensureActive($personnelRequest);

        if ($request->user()->isAdmin()) {
            throw ValidationException::withMessages([
                'decision' => ['Администратор не может голосовать вместо согласующего.'],
            ]);
        }

        $approval = $personnelRequest->approvals()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $approval->update([
            'decision' => $validated['decision'],
            'comment' => $this->emptyToNull($validated['comment'] ?? null),
            'decided_at' => now(),
        ]);

        $personnelRequest->refreshStatus();

        return response()->json([
            'message' => 'Решение сохранено.',
            'request' => $this->loadDetails($personnelRequest->fresh(), $request->user()),
        ]);
    }

    public function document(Request $request, int $requestId, int $documentId): BinaryFileResponse
    {
        $this->findAuthorized($request, $requestId);
        abort_unless($this->canViewFinancialData($request->user()), 403);

        $document = PersonnelRequestDocument::query()
            ->where('personnel_request_id', $requestId)
            ->findOrFail($documentId);

        abort_unless(Storage::disk('documents')->exists($document->path), 404);

        $mime = Storage::disk('documents')->mimeType($document->path) ?: 'application/octet-stream';
        $disposition = in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)
            ? 'inline'
            : 'attachment';

        $response = response()->file(Storage::disk('documents')->path($document->path), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);

        $response->setContentDisposition(
            $disposition,
            $document->original_name,
            Str::ascii($document->original_name) ?: 'document'
        );

        return $response;
    }

    public function referenceData(): JsonResponse
    {
        $users = $this->personnelApproverCandidatesQuery()
            ->with(['positions:id,name,department_id,academic_unit_id', 'positions.department:id,name', 'positions.academicUnit:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'positions' => $user->positions,
            ])
            ->values();

        return response()->json([
            'departments' => Department::query()
                ->with(['positions' => fn ($query) => $query->select('id', 'department_id', 'name')->orderBy('name')])
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id', 'description']),
            'users' => $users,
            'academic_units' => AcademicUnit::query()->with(['positions' => fn ($query) => $query->select('id', 'academic_unit_id', 'name')->whereNull('department_id')->orderBy('name')])
                ->orderByRaw("CASE WHEN name = 'Другое' THEN 1 ELSE 0 END")->orderBy('name')->get(['id', 'name', 'parent_id', 'kind']),
        ]);
    }

    public function approvalRoutes(): JsonResponse
    {
        $routes = PersonnelActionApprovalRoute::query()
            ->with(['approver:id,name,email', 'approver.positions:id,name,department_id,academic_unit_id', 'approver.positions.department:id,name', 'approver.positions.academicUnit:id,name'])
            ->orderBy('action_type')
            ->orderBy('approval_order')
            ->orderBy('id')
            ->get();

        $routes->each(fn (PersonnelActionApprovalRoute $route) => $route->approver?->setAppends([]));

        return response()->json($routes);
    }

    public function saveApprovalRoutes(Request $request, string $actionType): JsonResponse
    {
        abort_unless(in_array($actionType, self::ACTION_TYPES, true), 404);

        $validated = $request->validate([
            'approver_user_ids' => ['required', 'array', 'min:1', 'max:100'],
            'approver_user_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
        ], [
            'approver_user_ids.*.distinct' => 'Участники комиссии не должны повторяться.',
        ]);
        $ids = array_map('intval', $validated['approver_user_ids']);
        if ($this->personnelApproverCandidatesQuery()->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'approver_user_ids' => ['В составе комиссии есть недоступный пользователь.'],
            ]);
        }

        DB::transaction(function () use ($actionType, $ids) {
            PersonnelActionApprovalRoute::query()->where('action_type', $actionType)->delete();
            foreach ($ids as $index => $id) {
                PersonnelActionApprovalRoute::query()->create([
                    'action_type' => $actionType,
                    'approver_user_id' => $id,
                    'approval_order' => $index + 1,
                ]);
            }
        });

        return response()->json([
            'message' => 'Комиссия по умолчанию сохранена.',
        ], 201);
    }

    private function defaultApproverIds(string $actionType): array
    {
        $ids = PersonnelActionApprovalRoute::query()->where('action_type', $actionType)
            ->orderBy('approval_order')->orderBy('id')->pluck('approver_user_id')->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            throw ValidationException::withMessages([
                'approver_user_ids' => ['Настройте комиссию по умолчанию для выбранного кадрового действия.'],
            ]);
        }
        if ($this->personnelApproverCandidatesQuery()->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'approver_user_ids' => ['Обновите комиссию по умолчанию: в её составе есть недоступный пользователь.'],
            ]);
        }

        return $ids;
    }

    private function findAuthorized(Request $request, int $id): PersonnelRequest
    {
        $query = PersonnelRequest::query();

        if (! $request->user()->isAdmin()) {
            $query->whereHas('approvals', fn ($approvalQuery) => $approvalQuery->where('user_id', $request->user()->id));
        }

        return $query->findOrFail($id);
    }

    private function loadDetails(PersonnelRequest $personnelRequest, ?User $viewer = null): PersonnelRequest
    {
        $personnelRequest->load([
            'department:id,name',
            'position:id,name',
            'targetDepartment:id,name',
            'targetPosition:id,name',
            'faculty:id,name', 'academicUnit:id,name', 'targetFaculty:id,name', 'targetAcademicUnit:id,name',
            'documents:id,personnel_request_id,original_name,mime_type,size,created_at',
            'approvals.user:id,name,email',
        ]);

        $personnelRequest = $this->withoutUserAppends($personnelRequest);

        return $viewer
            ? $this->applyFinancialVisibility($personnelRequest, $viewer)
            : $personnelRequest;
    }

    private function applyFinancialVisibility(PersonnelRequest $personnelRequest, User $viewer): PersonnelRequest
    {
        $canView = $this->canViewFinancialData($viewer);
        $personnelRequest->setAttribute('can_view_financial_data', $canView);

        if (! $canView) {
            $personnelRequest->makeHidden(['salary', 'documents_count']);
            $personnelRequest->unsetRelation('documents');
        }

        return $personnelRequest;
    }

    private function canViewFinancialData(User $user): bool
    {
        return $user->isAdmin() || $user->isEconomist();
    }

    private function withoutUserAppends(PersonnelRequest $personnelRequest): PersonnelRequest
    {
        $personnelRequest->creator?->setAppends([]);
        $personnelRequest->approvals->each(
            fn (PersonnelRequestApproval $approval) => $approval->user?->setAppends([])
        );

        return $personnelRequest;
    }

    private function personnelStructureRules(Request $request, bool $teacher): array
    {
        $transfer = $request->input('action_type') === PersonnelRequest::ACTION_TRANSFER;
        $rules = [];
        foreach (['', 'target_'] as $prefix) {
            $required = $prefix === '' || $transfer;
            $rules[$prefix.'department_id'] = $teacher ? ['prohibited'] : [$required ? 'required' : 'nullable', 'integer', 'exists:departments,id'];
            $rules[$prefix.'position_id'] = [$teacher && $required ? 'required' : 'nullable', 'integer', 'exists:positions,id'];
            $rules[$prefix.'faculty_id'] = $teacher ? [$required ? 'required' : 'nullable', 'integer', Rule::exists('academic_units', 'id')->where('kind', 'faculty')] : ['prohibited'];
            $rules[$prefix.'academic_unit_id'] = $teacher ? [$required ? 'required' : 'nullable', 'integer', Rule::exists('academic_units', 'id')->where('kind', 'chair')] : ['prohibited'];
        }

        return $rules;
    }

    private function validatedPersonnelStructure(array $data, string $type): array
    {
        $structure = ['employee_type' => $type];
        foreach (['', 'target_'] as $prefix) {
            $active = $prefix === '' || $data['action_type'] === PersonnelRequest::ACTION_TRANSFER;
            $teacher = $type === 'pps';
            $structure[$prefix.'department_id'] = $active && ! $teacher ? $data[$prefix.'department_id'] : null;
            $structure[$prefix.'faculty_id'] = $active && $teacher ? $data[$prefix.'faculty_id'] : null;
            $structure[$prefix.'academic_unit_id'] = $active && $teacher ? $data[$prefix.'academic_unit_id'] : null;
            $structure[$prefix.'position_id'] = $active ? ($data[$prefix.'position_id'] ?? null) : null;
            if (! $active) {
                continue;
            }
            if ($teacher) {
                if (! AcademicUnit::whereKey($data[$prefix.'academic_unit_id'])->where('parent_id', $data[$prefix.'faculty_id'])->exists()) {
                    throw ValidationException::withMessages([$prefix.'academic_unit_id' => [__('messages.personnel_chair_mismatch')]]);
                }
                if (! Position::whereKey($data[$prefix.'position_id'])->where('academic_unit_id', $data[$prefix.'academic_unit_id'])->whereNull('department_id')->exists()) {
                    throw ValidationException::withMessages([$prefix.'position_id' => [__('messages.personnel_position_mismatch')]]);
                }
            } else {
                $this->validatePositionDepartment($data[$prefix.'position_id'] ?? null, (int) $data[$prefix.'department_id'], $prefix.'position_id');
            }
        }

        return $structure;
    }

    private function validatePositionDepartment(?int $positionId, int $departmentId, string $field): void
    {
        if ($positionId === null) {
            return;
        }

        if (! Position::query()->whereKey($positionId)->where('department_id', $departmentId)->exists()) {
            throw ValidationException::withMessages([
                $field => ['Выбранная должность не относится к указанному подразделению.'],
            ]);
        }
    }

    private function personnelApproverCandidatesQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Technical email accounts are temporarily eligible for personnel approvals.
        return User::query()
            ->whereNotNull('email')
            ->where(fn ($query) => $query->whereNull('role')->orWhere('role', '!=', 'admin'));
    }

    private function emptyToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function ensureEditable(PersonnelRequest $personnelRequest): void
    {
        $this->ensureActive($personnelRequest);
        if ($personnelRequest->status !== 'pending' || $personnelRequest->approvals()->where('decision', '!=', 'pending')->exists()) {
            throw ValidationException::withMessages(['request' => ['После начала согласования содержание и документы менять нельзя. Создайте новую заявку.']]);
        }
    }

    private function ensureActive(PersonnelRequest $personnelRequest): void
    {
        if ($personnelRequest->archived_at !== null) {
            throw ValidationException::withMessages(['request' => ['Кадровый процесс в архиве. Сначала восстановите его.']]);
        }
    }

    private function storeDocuments(PersonnelRequest $personnelRequest, array $files): void
    {
        foreach ($files as $file) {
            $path = $file->store("personnel-requests/{$personnelRequest->id}", 'documents');

            $personnelRequest->documents()->create([
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }
}
