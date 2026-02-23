<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationCommissionVote;
use App\Models\ApplicationStatus;
use App\Models\CommissionMember;
use App\Models\User;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function adminMembers(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $members = CommissionMember::query()
            ->with('user:id,name,email,phone,role')
            ->whereHas('user', function ($query) use ($q) {
                if ($q === '') {
                    return;
                }

                $query->where(function ($inner) use ($q) {
                    $inner
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('role', 'like', "%{$q}%");
                });
            })
            ->latest()
            ->get();

        return response()->json($members);
    }

    public function adminCandidateUsers(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $membersUserIds = CommissionMember::query()->pluck('user_id');

        $users = User::query()
            ->select('id', 'name', 'email', 'phone', 'role')
            ->whereNotIn('id', $membersUserIds)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner
                        ->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('role', 'like', "%{$q}%");
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
        ]);

        $member = CommissionMember::query()->firstOrCreate([
            'user_id' => $validated['user_id'],
        ]);

        return response()->json([
            'message' => 'Член основной комиссии добавлен.',
            'member' => $member->load('user:id,name,email,phone,role'),
        ], 201);
    }

    public function adminRemoveMember(int $userId)
    {
        CommissionMember::query()->where('user_id', $userId)->delete();

        return response()->json([
            'message' => 'Член основной комиссии удален.',
        ]);
    }

    public function queue(Request $request)
    {
        $isGlobalMember = CommissionMember::query()
            ->where('user_id', $request->user()->id)
            ->exists();

        $statusId = ApplicationStatus::query()
            ->where('code', 'corruption_not_found')
            ->value('id');

        $applicationsQuery = Application::query()
            ->with([
                'user:id,name,email,phone',
                'vacancy:id,title,type',
                'vacancy.commissionMembers:id,name,email,phone,role',
                'status:id,code,name',
                'commissionVotes.user:id,name,email,role',
            ])
            ->where('status_id', $statusId);

        if (!$isGlobalMember) {
            $applicationsQuery->whereHas('vacancy.commissionMembers', function ($query) use ($request) {
                $query->where('users.id', $request->user()->id);
            });
        }

        $applications = $applicationsQuery
            ->latest()
            ->get();

        $globalMemberIds = CommissionMember::query()->pluck('user_id')->all();

        $applications->transform(function (Application $application) use ($request, $globalMemberIds) {
            $vacancyMemberIds = collect($application->vacancy?->commissionMembers ?? [])->pluck('id')->all();
            $allowedIds = collect(array_merge($globalMemberIds, $vacancyMemberIds))
                ->unique()
                ->values()
                ->all();

            $membersCount = count($allowedIds);
            $eligibleVotes = $application->commissionVotes->whereIn('user_id', $allowedIds);
            $hireVotes = $eligibleVotes->where('decision', 'hire')->count();
            $rejectVotes = $eligibleVotes->where('decision', 'reject')->count();

            $half = $membersCount > 0 ? $membersCount / 2 : 0;
            $result = 'pending';
            if ($hireVotes > $half) {
                $result = 'approved';
            } elseif ($rejectVotes > $half) {
                $result = 'rejected';
            }

            $myVote = $eligibleVotes->firstWhere('user_id', $request->user()->id);

            $application->vote_summary = [
                'total_members' => $membersCount,
                'hire' => $hireVotes,
                'reject' => $rejectVotes,
                'voted' => $eligibleVotes->count(),
                'pending' => max($membersCount - $eligibleVotes->count(), 0),
                'result' => $result,
                'my_vote' => $myVote ? [
                    'decision' => $myVote->decision,
                    'comment' => $myVote->comment,
                    'updated_at' => $myVote->updated_at,
                ] : null,
            ];

            return $application;
        });

        return response()->json($applications);
    }

    public function vote(Request $request, int $id)
    {
        $validated = $request->validate([
            'decision' => 'required|in:hire,reject',
            'comment' => 'nullable|string|max:1000',
        ]);

        $statusId = ApplicationStatus::query()
            ->where('code', 'corruption_not_found')
            ->value('id');

        $application = Application::query()->with('vacancy')->findOrFail($id);
        if ((int) $application->status_id !== (int) $statusId) {
            return response()->json([
                'message' => 'Голосование доступно только для заявок со статусом "Коррупция: не выявлена".',
            ], 422);
        }

        $isGlobalMember = CommissionMember::query()
            ->where('user_id', $request->user()->id)
            ->exists();

        $isVacancyMember = $application->vacancy()
            ->whereHas('commissionMembers', function ($query) use ($request) {
                $query->where('users.id', $request->user()->id);
            })
            ->exists();

        if (!$isGlobalMember && !$isVacancyMember) {
            return response()->json([
                'message' => 'Вы не входите в комиссию для этой заявки.',
            ], 403);
        }

        ApplicationCommissionVote::query()->updateOrCreate(
            [
                'application_id' => $application->id,
                'user_id' => $request->user()->id,
            ],
            [
                'decision' => $validated['decision'],
                'comment' => $validated['comment'] ?? null,
            ]
        );

        $globalMemberIds = CommissionMember::query()->pluck('user_id')->all();
        $vacancyMemberIds = $application->vacancy()
            ->firstOrFail()
            ->commissionMembers()
            ->pluck('users.id')
            ->all();
        $allowedIds = collect(array_merge($globalMemberIds, $vacancyMemberIds))
            ->unique()
            ->values()
            ->all();

        $membersCount = count($allowedIds);
        $votes = ApplicationCommissionVote::query()
            ->where('application_id', $application->id)
            ->whereIn('user_id', $allowedIds)
            ->get(['decision']);

        $hireVotes = $votes->where('decision', 'hire')->count();
        $rejectVotes = $votes->where('decision', 'reject')->count();
        $half = $membersCount > 0 ? $membersCount / 2 : 0;

        if ($hireVotes > $half) {
            $completedId = ApplicationStatus::query()->where('code', 'completed')->value('id');
            if ($completedId) {
                $application->update(['status_id' => $completedId]);
            }
        } elseif ($rejectVotes > $half) {
            $notAcceptedId = ApplicationStatus::query()->where('code', 'not_accepted')->value('id');
            if ($notAcceptedId) {
                $application->update(['status_id' => $notAcceptedId]);
            }
        }

        return response()->json([
            'message' => 'Ваш голос сохранен.',
        ]);
    }
}
