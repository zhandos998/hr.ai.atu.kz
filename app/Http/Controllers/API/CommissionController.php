<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationCommissionVote;
use App\Models\CommissionMember;
use App\Models\PpsFacultyCommissionMember;
use App\Models\User;
use App\Support\ApplicationStageManager;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class CommissionController extends Controller
{
    private array $globalMembersCache = [];
    private array $facultyMembersCache = [];

    public function adminMembers(Request $request)
    {
        $validated = $request->validate([
            'type' => 'nullable|in:pps,staff',
        ]);
        $query = trim((string) $request->query('q', ''));
        $type = $validated['type'] ?? null;

        $members = CommissionMember::query()
            ->forVacancyType($type)
            ->with('user:id,name,email,phone,role')
            ->whereHas('user', function ($builder) use ($query) {
                if ($query === '') {
                    return;
                }

                $builder->where(function ($inner) use ($query) {
                    $inner
                        ->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('role', 'like', "%{$query}%");
                });
            })
            ->latest()
            ->get();

        return response()->json($members);
    }

    public function adminCandidateUsers(Request $request)
    {
        $validated = $request->validate([
            'type' => 'nullable|in:pps,staff',
        ]);
        $query = trim((string) $request->query('q', ''));
        $type = $validated['type'] ?? null;
        $memberUserIds = CommissionMember::query()
            ->forVacancyType($type)
            ->pluck('user_id');

        $users = User::query()
            ->select('id', 'name', 'email', 'phone', 'role')
            ->whereNotIn('id', $memberUserIds)
            ->where(function ($builder) {
                $builder
                    ->whereNull('email')
                    ->orWhere('email', 'not like', 'manual-candidate-%@hr-ai.invalid');
            })
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner
                        ->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%")
                        ->orWhere('role', 'like', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->limit(200)
            ->get();

        return response()->json($users);
    }

    public function adminAddMember(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'type' => 'nullable|in:pps,staff',
        ]);
        $type = $validated['type'] ?? null;
        $user = User::query()->findOrFail($validated['user_id']);

        if ($this->isTechnicalManualCandidate($user)) {
            return response()->json([
                'message' => 'Технического кандидата нельзя добавить в комиссию.',
            ], 422);
        }

        $member = CommissionMember::query()->firstOrNew([
            'user_id' => $validated['user_id'],
        ]);

        if (!$member->exists) {
            $member->is_pps = false;
            $member->is_staff = false;
        }

        if ($type === null) {
            $member->is_pps = true;
            $member->is_staff = true;
        } else {
            $member->{CommissionMember::flagColumnForType($type)} = true;
        }

        $member->save();

        return response()->json([
            'message' => 'Член основной комиссии добавлен.',
            'member' => $member->load('user:id,name,email,phone,role'),
        ], 201);
    }

    public function adminRemoveMember(Request $request, int $userId)
    {
        $validated = $request->validate([
            'type' => 'nullable|in:pps,staff',
        ]);
        $type = $validated['type'] ?? null;
        $member = CommissionMember::query()
            ->where('user_id', $userId)
            ->first();

        if ($member) {
            if ($type === null) {
                $member->delete();
            } else {
                $member->{CommissionMember::flagColumnForType($type)} = false;

                if ($member->isActive()) {
                    $member->save();
                } else {
                    $member->delete();
                }
            }
        }

        return response()->json([
            'message' => 'Член основной комиссии удален.',
        ]);
    }

    public function queue(Request $request)
    {
        $applications = $this->commissionApplicationsQuery($request)
            ->latest()
            ->get();

        $applications->transform(fn (Application $application) => $this->transformApplication(
            $application,
            $request->user()->id,
            $this->globalMembers($application->vacancy?->type)
        ));

        return response()->json($applications);
    }

    public function show(Request $request, int $id)
    {
        $application = $this->commissionApplicationsQuery($request, true, false)->findOrFail($id);

        return response()->json($this->transformApplication(
            $application,
            $request->user()->id,
            $this->globalMembers($application->vacancy?->type)
        ));
    }

    public function vote(Request $request, int $id)
    {
        $validated = $request->validate([
            'decision' => 'required|in:hire,reject',
            'hire_term_years' => 'nullable|integer|in:1,3',
            'comment' => 'nullable|string|max:1000',
        ]);

        $application = Application::query()
            ->notArchived()
            ->with([
                'vacancy.commissionMembers',
                'commissionVotes',
                'ppsProfile:id,application_id,faculty_name',
            ])
            ->findOrFail($id);

        $vacancyType = $application->vacancy?->type;
        $isPpsApplication = $vacancyType === 'pps';

        if ($isPpsApplication && $validated['decision'] === 'hire' && empty($validated['hire_term_years'])) {
            return response()->json([
                'message' => 'Для ППС укажите срок найма: 1 год или 3 года.',
            ], 422);
        }

        if ($application->compliance_status !== 'clear') {
            return response()->json([
                'message' => 'Голосование доступно только после положительной проверки на коррупцию.',
            ], 422);
        }

        if (!in_array($application->hiring_status, ['not_started', 'voting'], true)) {
            return response()->json([
                'message' => 'По этой заявке голосование уже завершено.',
            ], 422);
        }

        $isVacancyMember = $application->vacancy?->commissionMembers
            ?->contains('id', $request->user()->id) ?? false;
        $isGlobalMember = $this->isGlobalCommissionMember($request->user()->id, $vacancyType);
        $isFacultyMember = $this->isPpsFacultyCommissionMember($request->user()->id, $application);

        if (!$isVacancyMember && !$isGlobalMember && !$isFacultyMember) {
            return response()->json([
                'message' => 'Вы не входите в комиссию для этой заявки.',
            ], 403);
        }

        if ($application->hiring_status === 'not_started') {
            $application = ApplicationStageManager::setStage(
                $application,
                'hiring',
                'voting',
                'Идет голосование комиссии.',
                $request->user()?->id
            );
        }

        ApplicationCommissionVote::query()->updateOrCreate(
            [
                'application_id' => $application->id,
                'user_id' => $request->user()->id,
            ],
            [
                'decision' => $validated['decision'],
                'hire_term_years' => $validated['decision'] === 'hire' && $isPpsApplication
                    ? (int) $validated['hire_term_years']
                    : null,
                'comment' => $validated['comment'] ?? null,
            ]
        );

        $application->loadMissing('vacancy.commissionMembers', 'ppsProfile:id,application_id,faculty_name');

        $allowedIds = $this->allowedMemberIds($application, $this->globalMembers($vacancyType));

        $votes = ApplicationCommissionVote::query()
            ->where('application_id', $application->id)
            ->whereIn('user_id', $allowedIds)
            ->get(['decision', 'hire_term_years']);

        $summary = $this->calculateVoteSummary(
            $votes,
            count($allowedIds),
            $isPpsApplication
        );

        if ($summary['result'] === 'approved') {
            $hiringStatus = 'hired';
            if ($isPpsApplication) {
                $hiringStatus = (int) $summary['approved_term_years'] === 3 ? 'hired_3_year' : 'hired_1_year';
            }

            ApplicationStageManager::setStage(
                $application,
                'hiring',
                $hiringStatus,
                $this->voteDecisionComment($summary, $isPpsApplication),
                $request->user()?->id
            );

            Application::query()
                ->whereKey($application->id)
                ->update(['hiring_term_years' => $summary['approved_term_years']]);
        } elseif ($summary['result'] === 'rejected') {
            ApplicationStageManager::setStage(
                $application,
                'hiring',
                'rejected',
                $this->voteDecisionComment($summary, $isPpsApplication),
                $request->user()?->id
            );

            Application::query()
                ->whereKey($application->id)
                ->update(['hiring_term_years' => null]);
        }

        $application = $this->commissionApplicationsQuery($request, true, false)->findOrFail($application->id);

        return response()->json([
            'message' => 'Ваш голос сохранен.',
            'application' => $this->transformApplication(
                $application,
                $request->user()->id,
                $this->globalMembers($application->vacancy?->type)
            ),
        ]);
    }

    private function commissionApplicationsQuery(Request $request, bool $withPpsProfile = false, bool $onlyActive = true)
    {
        $relations = [
            'user:id,name,email,phone',
            'vacancy:id,title,type,position_id',
            'vacancy.position:id,department_id,name',
            'vacancy.position.department:id,name',
            'vacancy.commissionMembers:id,name,email,phone,role',
            'ppsProfile',
            'status:id,code,name',
            'commissionVotes:id,application_id,user_id,decision,hire_term_years,comment,updated_at',
            'resume:id,application_id,file_path',
            'documents:id,application_id,type,file_path',
            'stageLogs.author:id,name',
        ];

        if ($withPpsProfile) {
            $relations[] = 'ppsProfile.documents';
        }

        $query = Application::query()
            ->with($relations)
            ->notArchived()
            ->where('compliance_status', 'clear');

        if ($onlyActive) {
            $query->whereIn('hiring_status', ['not_started', 'voting']);
        }

        $userId = $request->user()->id;
        $globalTypes = $this->globalCommissionTypes($userId);
        $facultyNames = $this->facultyCommissionNames($userId);

        $query->where(function ($builder) use ($userId, $globalTypes, $facultyNames) {
            $builder->whereHas('vacancy.commissionMembers', function ($memberQuery) use ($userId) {
                $memberQuery->where('users.id', $userId);
            });

            if (!empty($globalTypes)) {
                $builder->orWhereHas('vacancy', function ($vacancyQuery) use ($globalTypes) {
                    $vacancyQuery->whereIn('type', $globalTypes);
                });
            }

            if (!empty($facultyNames)) {
                $builder->orWhere(function ($facultyQuery) use ($facultyNames) {
                    $facultyQuery
                        ->whereHas('vacancy', function ($vacancyQuery) {
                            $vacancyQuery->where('type', CommissionMember::TYPE_PPS);
                        })
                        ->whereHas('ppsProfile', function ($profileQuery) use ($facultyNames) {
                            $profileQuery->whereIn('faculty_name', $facultyNames);
                        });
                });
            }
        });

        return $query;
    }

    private function isGlobalCommissionMember(int $userId, ?string $vacancyType = null): bool
    {
        return CommissionMember::query()
            ->where('user_id', $userId)
            ->forVacancyType($vacancyType)
            ->exists();
    }

    private function globalCommissionTypes(int $userId): array
    {
        $member = CommissionMember::query()
            ->where('user_id', $userId)
            ->first(['is_pps', 'is_staff']);

        if (!$member) {
            return [];
        }

        $types = [];

        if ($member->is_pps) {
            $types[] = CommissionMember::TYPE_PPS;
        }

        if ($member->is_staff) {
            $types[] = CommissionMember::TYPE_STAFF;
        }

        return $types;
    }

    private function facultyCommissionNames(int $userId): array
    {
        return PpsFacultyCommissionMember::query()
            ->where('user_id', $userId)
            ->pluck('faculty_name')
            ->filter()
            ->values()
            ->all();
    }

    private function isPpsFacultyCommissionMember(int $userId, Application $application): bool
    {
        if ($application->vacancy?->type !== CommissionMember::TYPE_PPS) {
            return false;
        }

        $facultyName = trim((string) ($application->ppsProfile?->faculty_name ?? ''));
        if ($facultyName === '') {
            return false;
        }

        return PpsFacultyCommissionMember::query()
            ->where('user_id', $userId)
            ->where('faculty_name', $facultyName)
            ->exists();
    }

    private function globalMembers(?string $vacancyType = null): Collection
    {
        $cacheKey = $vacancyType ?: 'all';

        if (array_key_exists($cacheKey, $this->globalMembersCache)) {
            return $this->globalMembersCache[$cacheKey];
        }

        $this->globalMembersCache[$cacheKey] = CommissionMember::query()
            ->forVacancyType($vacancyType)
            ->with('user:id,name,email')
            ->get()
            ->pluck('user')
            ->filter()
            ->values();

        return $this->globalMembersCache[$cacheKey];
    }

    private function allowedMemberIds(Application $application, Collection $globalMembers): array
    {
        return $this->allAssignedMembers($application, $globalMembers)
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
    }

    private function transformApplication(Application $application, int $userId, Collection $globalMembers): Application
    {
        return $this->attachPpsProfileDocumentUrls(
            $this->attachVoteSummary(
                $this->attachDocumentUrls($application),
                $userId,
                $globalMembers
            )
        );
    }

    private function attachVoteSummary(Application $application, int $userId, Collection $globalMembers): Application
    {
        ApplicationStageManager::ensureDefaults($application);

        $allMembers = $this->allAssignedMembers($application, $globalMembers);

        $allowedIds = $allMembers->pluck('id')->all();
        $eligibleVotes = ($application->commissionVotes ?? collect())->whereIn('user_id', $allowedIds);
        $summary = $this->calculateVoteSummary(
            $eligibleVotes,
            count($allowedIds),
            $application->vacancy?->type === 'pps'
        );
        $myVote = $eligibleVotes->firstWhere('user_id', $userId);

        $application->vote_summary = [
            'total_members' => count($allowedIds),
            'hire' => $summary['hire'],
            'hire_1_year' => $summary['hire_1_year'],
            'hire_3_year' => $summary['hire_3_year'],
            'reject' => $summary['reject'],
            'voted' => $eligibleVotes->count(),
            'pending' => max(count($allowedIds) - $eligibleVotes->count(), 0),
            'result' => $summary['result'],
            'approved_term_years' => $summary['approved_term_years'],
            'my_vote' => $myVote ? [
                'decision' => $myVote->decision,
                'hire_term_years' => $myVote->hire_term_years,
                'comment' => $myVote->comment,
                'updated_at' => $myVote->updated_at,
            ] : null,
        ];

        $application->unsetRelation('commissionVotes');

        return $application;
    }

    private function allAssignedMembers(Application $application, Collection $globalMembers): Collection
    {
        $vacancyMembers = collect($application->vacancy?->commissionMembers ?? [])
            ->map(fn ($member) => (object) [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
            ]);
        $facultyMembers = $this->facultyMembers($application)
            ->map(fn ($member) => (object) [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
            ]);
        $globalMembers = $globalMembers
            ->map(fn ($member) => (object) [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
            ]);

        return $vacancyMembers
            ->merge($globalMembers)
            ->merge($facultyMembers)
            ->unique('id')
            ->values();
    }

    private function facultyMembers(Application $application): Collection
    {
        if ($application->vacancy?->type !== CommissionMember::TYPE_PPS) {
            return collect();
        }

        $facultyName = trim((string) ($application->ppsProfile?->faculty_name ?? ''));
        if ($facultyName === '') {
            return collect();
        }

        if (!array_key_exists($facultyName, $this->facultyMembersCache)) {
            $this->facultyMembersCache[$facultyName] = PpsFacultyCommissionMember::query()
                ->where('faculty_name', $facultyName)
                ->with('user:id,name,email')
                ->get()
                ->pluck('user')
                ->filter()
                ->values();
        }

        return $this->facultyMembersCache[$facultyName];
    }

    private function calculateVoteSummary(Collection $votes, int $membersCount, bool $isPpsApplication): array
    {
        $hireVotes = $votes->where('decision', 'hire')->count();
        $hireOneYearVotes = $votes
            ->where('decision', 'hire')
            ->where('hire_term_years', 1)
            ->count();
        $hireThreeYearVotes = $votes
            ->where('decision', 'hire')
            ->where('hire_term_years', 3)
            ->count();
        $rejectVotes = $votes->where('decision', 'reject')->count();
        $half = $membersCount > 0 ? $membersCount / 2 : 0;

        $result = 'pending';
        $approvedTermYears = null;

        if ($isPpsApplication) {
            if ($hireOneYearVotes > $half) {
                $result = 'approved';
                $approvedTermYears = 1;
            } elseif ($hireThreeYearVotes > $half) {
                $result = 'approved';
                $approvedTermYears = 3;
            } elseif ($rejectVotes > $half) {
                $result = 'rejected';
            }
        } else {
            if ($hireVotes > $half) {
                $result = 'approved';
            } elseif ($rejectVotes > $half) {
                $result = 'rejected';
            }
        }

        return [
            'hire' => $hireVotes,
            'hire_1_year' => $hireOneYearVotes,
            'hire_3_year' => $hireThreeYearVotes,
            'reject' => $rejectVotes,
            'result' => $result,
            'approved_term_years' => $approvedTermYears,
        ];
    }

    private function voteDecisionComment(array $summary, bool $isPpsApplication): string
    {
        if ($isPpsApplication) {
            return "Автоматически установлено по итогам голосования комиссии: {$summary['hire_1_year']} за на 1 год, {$summary['hire_3_year']} за на 3 года, {$summary['reject']} против.";
        }

        return "Автоматически установлено по итогам голосования комиссии: {$summary['hire']} за, {$summary['reject']} против.";
    }

    private function attachDocumentUrls(Application $application): Application
    {
        ApplicationStageManager::ensureDefaults($application);

        $application->resume_url = $application->resume ? url(Storage::url($application->resume->file_path)) : null;

        $documents = [];
        foreach ($application->documents as $document) {
            $normalizedType = $this->normalizeDocumentTypeForOutput($document->type);
            if ($normalizedType === null) {
                continue;
            }

            $documents[$normalizedType] = [
                'id' => $document->id,
                'path' => $document->file_path,
                'url' => url(Storage::url($document->file_path)),
            ];
        }

        $application->documents_map = (object) $documents;

        return $application;
    }

    private function attachPpsProfileDocumentUrls(Application $application): Application
    {
        if (!$application->relationLoaded('ppsProfile') || !$application->ppsProfile) {
            return $application;
        }

        foreach ($this->ppsProfileCategoryDocumentConfig() as $responseKey => $config) {
            $application->ppsProfile->{$responseKey} = collect($application->ppsProfile->documents ?? [])
                ->where('category', $config['category'])
                ->values()
                ->map(function ($document) {
                    return [
                        'id' => $document->id,
                        'name' => $document->original_name ?: basename($document->file_path),
                        'url' => url(Storage::url($document->file_path)),
                    ];
                })
                ->all();
        }

        return $application;
    }

    private function ppsProfileCategoryDocumentConfig(): array
    {
        return [
            'basic_education_documents' => ['category' => 'basic_education'],
            'magistracy_documents' => ['category' => 'magistracy'],
            'scientific_degree_documents' => ['category' => 'scientific_degree'],
            'academic_title_documents' => ['category' => 'academic_title'],
            'scientific_works_documents' => ['category' => 'scientific_works'],
            'digital_mooc_documents' => ['category' => 'digital_mooc'],
            'strategy_documents' => ['category' => 'strategy_review'],
            'academic_documents' => ['category' => 'academic_review'],
            'library_documents' => ['category' => 'library_metrics'],
            'compliance_documents' => ['category' => 'compliance_department'],
        ];
    }

    private function isTechnicalManualCandidate(User $user): bool
    {
        return (bool) preg_match('/^manual-candidate-.+@hr-ai\.invalid$/i', (string) $user->email);
    }

    private function normalizeDocumentTypeForOutput(string $type): ?string
    {
        $baseType = preg_replace('/_\d+$/', '', $type);

        if (in_array($baseType, ['id_card', 'address_certificate'], true)) {
            return null;
        }

        if ($baseType === 'articles') {
            $baseType = 'scientific_works';
        }

        if (preg_match('/_(\d+)$/', $type, $matches)) {
            return "{$baseType}_{$matches[1]}";
        }

        if (in_array($baseType, ['diploma', 'recommendation_letter', 'scientific_works', 'other'], true)) {
            return "{$baseType}_1";
        }

        return $baseType;
    }
}
