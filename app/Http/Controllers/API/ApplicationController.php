<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationStatus;
use App\Models\CandidateAIResult;
use App\Models\CommissionMember;
use App\Models\Resume;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    private $globalCommissionMembersCache = null;

    public function index()
    {
        $applications = Application::with([
            'user',
            'status',
            'vacancy',
            'vacancy.commissionMembers:id,name,email,phone',
            'commissionVotes:id,application_id,user_id,decision,comment,updated_at',
            'resume',
            'documents',
        ])
            ->latest()
            ->get();

        $applications->transform(function ($a) {
            $a = $this->attachDocumentUrls($a);
            $a = $this->attachVoteSummary($a);
            return $this->attachAiResult($a);
        });

        return response()->json($applications);
    }

    public function updateStatus(Request $request, $id)
    {
        $application = Application::findOrFail($id);

        $validated = $request->validate([
            'status_code' => 'required|exists:application_statuses,code',
        ]);

        $status = ApplicationStatus::where('code', $validated['status_code'])->firstOrFail();
        $application->update(['status_id' => $status->id]);

        return response()->json([
            'message' => 'Статус заявки обновлен.',
            'application' => $application->load(['user', 'status']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'vacancy_id' => 'required|exists:vacancies,id',
            'resume' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($validated, $user, $request) {
            $vacancy = Vacancy::findOrFail($validated['vacancy_id']);
            $statusId = ApplicationStatus::where('code', 'pending')->value('id');

            $application = Application::create([
                'user_id' => $user->id,
                'vacancy_id' => $vacancy->id,
                'status_id' => $statusId,
            ]);

            $storedPath = $request->file('resume')->store("resumes/{$application->id}", 'public');

            $resume = Resume::create([
                'application_id' => $application->id,
                'user_id' => $user->id,
                'file_path' => $storedPath,
            ]);

            $application->load('resume');

            return response()->json([
                'message' => 'Вы успешно откликнулись на вакансию.',
                'application' => $application,
                'resume' => $resume,
                'resume_url' => Storage::url($resume->file_path),
            ], 201);
        });
    }

    public function show($id)
    {
        $application = Application::with(['status', 'vacancy', 'resume', 'documents'])->findOrFail($id);
        return response()->json($this->attachDocumentUrls($application));
    }

    public function acceptResume($id)
    {
        return $this->updateStatusCode($id, 'resume_accepted', 'Резюме принято.');
    }

    public function rejectResume($id)
    {
        return $this->updateStatusCode($id, 'resume_rejected', 'Резюме отклонено.');
    }

    public function acceptDocs($id)
    {
        return $this->updateStatusCode($id, 'docs_accepted', 'Документы приняты.');
    }

    public function rejectDocs($id)
    {
        return $this->updateStatusCode($id, 'docs_rejected', 'Документы отклонены.');
    }

    public function complete($id)
    {
        return $this->updateStatusCode($id, 'completed', 'Кандидат принят на вакансию.');
    }

    public function userApplications(Request $request)
    {
        $applications = Application::with([
            'status:id,code,name',
            'vacancy:id,title,type',
            'resume:id,application_id,file_path',
            'documents:id,application_id,type,file_path',
        ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        $applications->transform(fn ($a) => $this->attachDocumentUrls($a));

        return response()->json($applications);
    }

    public function uploadDocs(Request $request, $id)
    {
        $application = Application::with(['vacancy', 'status', 'documents'])
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (!in_array($application->status->code, ['resume_accepted', 'docs_rejected', 'docs_uploaded'])) {
            return response()->json(['message' => 'Сейчас нельзя загрузить/заменить документы.'], 403);
        }

        $expected = ['id_card', 'diploma'];
        if ($application->vacancy->type === 'pps') {
            $expected[] = 'articles';
        }
        if ($application->vacancy->type === 'staff') {
            $expected[] = 'address_certificate';
        }

        $rules = [];
        foreach ($expected as $type) {
            $hasAnyExisting = $application->documents->contains(
                fn ($doc) => $this->documentBaseType($doc->type) === $type
            );
            $base = $hasAnyExisting ? 'sometimes' : 'required';
            $max = ($type === 'articles') ? 5120 : 2048;
            $mimes = ($type === 'articles') ? 'pdf,zip' : 'pdf,jpg,jpeg,png';

            $rules[$type] = "$base|array|min:1";
            $rules["{$type}.*"] = "file|mimes:$mimes|max:$max";
        }

        $request->validate($rules);
        $savedDocs = [];

        DB::transaction(function () use ($request, $expected, $application, &$savedDocs) {
            foreach ($expected as $type) {
                $files = $request->file($type, []);
                if ($files instanceof UploadedFile) {
                    $files = [$files];
                }
                if (!is_array($files) || count($files) === 0) {
                    continue;
                }

                // Append files: keep existing files and add new ones with next suffix.
                $existingTypes = ApplicationDocument::query()
                    ->where('application_id', $application->id)
                    ->pluck('type')
                    ->all();

                $nextIndex = 1;
                foreach ($existingTypes as $existingType) {
                    if ($this->documentBaseType($existingType) !== $type) {
                        continue;
                    }

                    if ($existingType === $type) {
                        $nextIndex = max($nextIndex, 2);
                        continue;
                    }

                    if (preg_match('/^' . preg_quote($type, '/') . '_(\d+)$/', $existingType, $m)) {
                        $nextIndex = max($nextIndex, ((int) $m[1]) + 1);
                    }
                }

                foreach ($files as $file) {
                    $storedType = $nextIndex === 1 ? $type : "{$type}_{$nextIndex}";
                    $ext = $file->getClientOriginalExtension();
                    $dir = "applications/{$application->id}";
                    $path = $file->storeAs($dir, "{$storedType}.{$ext}", 'public');

                    ApplicationDocument::create([
                        'application_id' => $application->id,
                        'type' => $storedType,
                        'file_path' => $path,
                    ]);

                    $savedDocs[$storedType] = [
                        'path' => $path,
                        'url' => url(Storage::url($path)),
                    ];

                    $nextIndex++;
                }
            }

            $statusId = ApplicationStatus::where('code', 'docs_uploaded')->value('id');
            if ($statusId) {
                $application->status_id = $statusId;
                $application->save();
            }
        });

        return response()->json([
            'message' => 'Документы обновлены.',
            'documents_map' => (object) $savedDocs,
        ]);
    }

    public function lawyerQueue()
    {
        $applications = Application::with([
            'user:id,name,email,phone',
            'status:id,code,name',
            'vacancy:id,title,type',
            'resume:id,application_id,file_path',
            'documents:id,application_id,type,file_path',
        ])
            ->whereHas('status', fn ($q) => $q->where('code', 'docs_uploaded'))
            ->latest()
            ->get();

        $applications->transform(fn ($a) => $this->attachDocumentUrls($a));

        return response()->json($applications);
    }

    public function lawyerSetCorruptionStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status_code' => 'required|in:corruption_not_found,corruption_found',
        ]);

        $application = Application::with('status')->findOrFail($id);

        if ($application->status?->code !== 'docs_uploaded') {
            return response()->json([
                'message' => 'Lawyer может проверять только заявки со статусом "Документы загружены".',
            ], 422);
        }

        $statusId = ApplicationStatus::where('code', $validated['status_code'])->value('id');
        if (!$statusId) {
            return response()->json(['message' => 'Статус не найден.'], 422);
        }

        $application->status_id = $statusId;
        $application->save();

        return response()->json([
            'message' => 'Статус проверки lawyer обновлен.',
            'application' => $application->load([
                'user:id,name,email,phone',
                'status:id,code,name',
                'vacancy:id,title,type',
            ]),
        ]);
    }

    private function updateStatusCode($id, $code, $message)
    {
        $application = Application::findOrFail($id);
        $status = ApplicationStatus::where('code', $code)->firstOrFail();
        $application->status_id = $status->id;
        $application->save();

        return response()->json([
            'message' => $message,
            'application' => $application->load(['user', 'status']),
        ]);
    }

    private function attachDocumentUrls(Application $application): Application
    {
        $application->resume_url = $application->resume ? url(Storage::url($application->resume->file_path)) : null;

        $docs = [];
        foreach ($application->documents as $doc) {
            $docs[$doc->type] = [
                'path' => $doc->file_path,
                'url' => url(Storage::url($doc->file_path)),
            ];
        }
        $application->documents_map = (object) $docs;

        return $application;
    }

    private function attachVoteSummary(Application $application): Application
    {
        $globalMembers = $this->globalCommissionMembers();
        $vacancyMembers = collect($application->vacancy?->commissionMembers ?? [])->map(function ($member) {
            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
            ];
        });

        $allMembers = $globalMembers
            ->concat($vacancyMembers)
            ->unique('id')
            ->values()
            ->values();

        $assignedIds = $allMembers->pluck('id')->all();
        $membersCount = count($assignedIds);
        $votes = ($application->commissionVotes ?? collect())
            ->whereIn('user_id', $assignedIds);
        $hireVotes = $votes->where('decision', 'hire')->count();
        $rejectVotes = $votes->where('decision', 'reject')->count();

        $application->vote_summary = [
            'total_members' => $membersCount,
            'hire' => $hireVotes,
            'reject' => $rejectVotes,
            'voted' => $votes->count(),
            'pending' => max($membersCount - $votes->count(), 0),
        ];

        $votesByUserId = $votes->keyBy('user_id');
        $application->vote_details = $allMembers->map(function ($member) use ($votesByUserId) {
            $vote = $votesByUserId->get($member['id']);

            return [
                'user_id' => $member['id'],
                'name' => $member['name'],
                'email' => $member['email'],
                'decision' => $vote?->decision ?? 'pending',
                'comment' => $vote?->comment,
                'voted_at' => $vote?->updated_at,
            ];
        })->all();

        return $application;
    }

    private function documentBaseType(string $type): string
    {
        return preg_replace('/_\\d+$/', '', $type);
    }

    private function attachAiResult(Application $application): Application
    {
        $workerId = $application->user_id;
        $positionId = $application->vacancy?->position_id;

        if (!$workerId || !$positionId) {
            $application->ai_result = null;
            return $application;
        }

        $result = CandidateAIResult::query()
            ->where('worker_id', $workerId)
            ->where('position_id', $positionId)
            ->orderByDesc('updated_at')
            ->first([
                'score',
                'decision',
                'education_match',
                'experience_match',
                'soft_skills_match',
                'summary_ru',
            ]);

        $application->ai_result = $result ? [
            'score' => $result->score,
            'decision' => $result->decision,
            'education_match' => $result->education_match,
            'experience_match' => $result->experience_match,
            'soft_skills_match' => $result->soft_skills_match,
            'summary' => $result->summary_ru,
        ] : null;

        return $application;
    }

    private function globalCommissionMembers()
    {
        if ($this->globalCommissionMembersCache !== null) {
            return $this->globalCommissionMembersCache;
        }

        $ids = CommissionMember::query()->pluck('user_id')->all();

        if (empty($ids)) {
            $this->globalCommissionMembersCache = collect();
            return $this->globalCommissionMembersCache;
        }

        $this->globalCommissionMembersCache = User::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'email'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            });

        return $this->globalCommissionMembersCache;
    }
}
