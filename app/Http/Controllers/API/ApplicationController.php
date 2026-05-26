<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationPpsProfile;
use App\Models\ApplicationPpsProfileDocument;
use App\Models\ApplicationStatus;
use App\Models\CandidateAIResult;
use App\Models\CommissionMember;
use App\Models\Department;
use App\Models\Position;
use App\Models\PpsFacultyCommissionMember;
use App\Models\Resume;
use App\Models\User;
use App\Models\Vacancy;
use App\Support\ApplicationStageManager;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ApplicationController extends Controller
{
    private const PPS_VACANCY_TITLES = [
        'Ассистент',
        'Ассистент-профессор',
        'Ассистент-профессор-исследователь',
        'Ассоциированный профессор',
        'Ассоциированный профессор – исследователь',
        'Заведующий кафедрой (для выпускающих кафедр)',
        'Заведующий кафедрой (для не выпускающих кафедр)',
        'Сеньор-лектор',
        'Профессор',
        'Профессор-исследователь',
        'Исполняющий обязанности (и.о.) ассоциированного профессора',
        'Исполняющий обязанности (и.о.) ассоциированного профессора – исследователя',
        'Исполняющий обязанности (и.о.) профессора',
        'Исполняющий обязанности (и.о.) профессора-исследователя',
    ];

    private array $globalCommissionMembersCache = [];
    private array $facultyCommissionMembersCache = [];

    public function index(Request $request)
    {
        $applications = $this->adminApplicationsQuery($request->boolean('archived'))
            ->latest()
            ->get();

        $applications->transform(fn ($application) => $this->transformAdminApplication($application));

        return response()->json($applications);
    }

    public function adminShow($id)
    {
        $application = $this->adminApplicationsQuery(null)->findOrFail($id);

        return response()->json($this->transformAdminApplication($application));
    }

    public function archive(Request $request, int $id)
    {
        $application = Application::query()
            ->notArchived()
            ->findOrFail($id);

        $application->forceFill([
            'archived_at' => now(),
            'archived_by_user_id' => $request->user()?->id,
        ])->save();

        return response()->json([
            'message' => 'Заявка архивирована.',
        ]);
    }

    public function unarchive(int $id)
    {
        $application = Application::query()
            ->archived()
            ->findOrFail($id);

        $application->forceFill([
            'archived_at' => null,
            'archived_by_user_id' => null,
        ])->save();

        return response()->json([
            'message' => 'Заявка возвращена в активный список.',
        ]);
    }

    public function adminStore(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'vacancy_id' => 'required|exists:vacancies,id',
            'faculty_name' => 'nullable|string|max:255',
            'department_name' => 'nullable|string|max:255',
            'position_id' => 'nullable|integer|exists:positions,id',
            'resume' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
        ]);

        $vacancy = Vacancy::findOrFail($validated['vacancy_id']);
        $position = !empty($validated['position_id'])
            ? Position::query()->findOrFail((int) $validated['position_id'])
            : null;

        if ($vacancy->type === 'pps' && empty($validated['faculty_name'])) {
            return response()->json([
                'message' => 'Для заявки ППС выберите факультет.',
            ], 422);
        }

        if ($vacancy->type === 'pps' && empty($validated['department_name'])) {
            return response()->json([
                'message' => 'Для заявки ППС выберите кафедру.',
            ], 422);
        }

        if ($vacancy->type === 'pps') {
            $department = Department::query()
                ->where('name', trim((string) $validated['department_name']))
                ->first(['id', 'name']);

            if (!$department) {
                return response()->json([
                    'message' => 'Выбранная кафедра не найдена в справочнике.',
                ], 422);
            }

            if ($position && (int) $position->department_id !== (int) $department->id) {
                return response()->json([
                    'message' => 'Выбранная должность не относится к выбранной кафедре.',
                ], 422);
            }

            if (!$position) {
                if (!$this->isAllowedPpsVacancyTitle((string) $vacancy->title)) {
                    return response()->json([
                        'message' => 'Для заявки ППС выберите вакансию из списка должностей ППС.',
                    ], 422);
                }

                $position = Position::query()->firstOrCreate(
                    [
                        'department_id' => $department->id,
                        'name' => $vacancy->title,
                    ],
                    [
                        'duties' => null,
                        'qualification' => null,
                    ],
                );
            }

            $vacancy = $this->resolveVacancyForPosition($vacancy, $position);
        }

        $application = DB::transaction(function () use ($validated, $request, $vacancy, $position) {
            $user = $this->createManualCandidate($validated['full_name']);

            $application = $this->createApplicationForUser($user, $vacancy, $request->file('resume'));

            if ($vacancy->type === 'pps') {
                ApplicationPpsProfile::query()->updateOrCreate(
                    ['application_id' => $application->id],
                    [
                        'full_name' => trim($validated['full_name']),
                        'desired_position' => $position?->name ?: $vacancy->title,
                        'faculty_name' => $validated['faculty_name'],
                        'department_name' => $validated['department_name'],
                    ],
                );
            }

            $this->attachDefaultCommissionMembers($vacancy, $validated['faculty_name'] ?? null);

            return $application;
        });

        return response()->json([
            'message' => 'Заявка успешно создана.',
            'application' => $this->transformAdminApplication(
                $this->adminApplicationsQuery()->findOrFail($application->id)
            ),
        ], 201);
    }

    public function updatePpsProfile(Request $request, $id)
    {
        $application = Application::with(['vacancy', 'ppsProfile.documents'])->notArchived()->findOrFail($id);

        if ($application->vacancy?->type !== 'pps') {
            return response()->json([
                'message' => 'Данные преподавателя доступны только для заявок ППС.',
            ], 422);
        }

        $validated = $request->validate(array_merge([
            'full_name' => 'nullable|string|max:255',
            'desired_position' => 'nullable|string|max:255',
            'faculty_name' => 'nullable|string|max:255',
            'department_name' => 'nullable|string|max:255',
            'birth_year' => 'nullable|integer|min:1900|max:' . now()->year,
            'basic_education' => 'nullable|string|max:4000',
            'magistracy' => 'nullable|string|max:4000',
            'scientific_degree' => 'nullable|string|max:4000',
            'academic_title' => 'nullable|string|max:4000',
            'work_experience' => 'nullable|string|max:4000',
            'scientific_works' => 'nullable|string|max:8000',
            'digital_mooc' => 'nullable|string|max:8000',
            'final_rating_score' => 'nullable|string|max:255',
            'student_survey_results' => 'nullable|string|max:8000',
            'individual_plan_nonfulfillment' => 'nullable|string|max:8000',
            'krk' => 'nullable|string|max:8000',
            'open_lesson_quality' => 'nullable|string|max:8000',
            'taught_disciplines' => 'nullable|string|max:8000',
            'educational_methodical_literature' => 'nullable|string|max:8000',
            'educational_publication_metrics' => 'nullable|string|max:8000',
            'anti_corruption_survey_results' => 'nullable|string|max:8000',
            'disciplinary_actions_info' => 'nullable|string|max:8000',
        ], $this->ppsProfileCategoryValidationRules()));

        $oldFacultyName = $application->ppsProfile?->faculty_name;
        $profile = $application->ppsProfile ?: new ApplicationPpsProfile([
            'application_id' => $application->id,
        ]);

        foreach ([
            'full_name',
            'desired_position',
            'faculty_name',
            'department_name',
            'birth_year',
            'basic_education',
            'magistracy',
            'scientific_degree',
            'academic_title',
            'work_experience',
            'scientific_works',
            'digital_mooc',
            'final_rating_score',
            'student_survey_results',
            'individual_plan_nonfulfillment',
            'krk',
            'open_lesson_quality',
            'taught_disciplines',
            'educational_methodical_literature',
            'educational_publication_metrics',
            'anti_corruption_survey_results',
            'disciplinary_actions_info',
        ] as $field) {
            if (!$request->exists($field)) {
                continue;
            }

            $profile->{$field} = $field === 'birth_year'
                ? ($validated[$field] ?? null)
                : $this->emptyToNull($validated[$field] ?? null);
        }

        $profile->save();

        if (
            $request->exists('faculty_name')
            && trim((string) $oldFacultyName) !== trim((string) $profile->faculty_name)
        ) {
            $this->attachDefaultCommissionMembers($application->vacancy, $profile->faculty_name, false);
        }

        $this->storePpsProfileCategoryDocuments($request, $profile, $application->id);

        $application = $this->adminApplicationsQuery()->findOrFail($id);

        return response()->json([
            'message' => 'Данные преподавателя обновлены.',
            'application' => $this->transformAdminApplication($application),
        ]);
    }

    public function updateStaffDetails(Request $request, $id)
    {
        $application = Application::with(['user', 'vacancy'])->notArchived()->findOrFail($id);

        if ($application->vacancy?->type !== 'staff') {
            return response()->json([
                'message' => 'Данные ОУП доступны только для заявок ОУП.',
            ], 422);
        }

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'phone')->ignore($application->user_id),
            ],
            'vacancy_id' => [
                'required',
                'integer',
                Rule::exists('vacancies', 'id')->where(fn ($query) => $query->where('type', 'staff')),
            ],
        ]);

        DB::transaction(function () use ($application, $validated) {
            $application->user->update([
                'name' => trim($validated['full_name']),
                'phone' => $this->emptyToNull(trim((string) ($validated['phone'] ?? ''))),
            ]);

            $application->update([
                'vacancy_id' => (int) $validated['vacancy_id'],
            ]);
        });

        return response()->json([
            'message' => 'Данные ОУП обновлены.',
            'application' => $this->transformAdminApplication(
                $this->adminApplicationsQuery()->findOrFail($application->id)
            ),
        ]);
    }

    public function deletePpsProfileDocument(int $applicationId, int $documentId)
    {
        $application = Application::with(['vacancy', 'ppsProfile.documents'])->notArchived()->findOrFail($applicationId);

        if ($application->vacancy?->type !== 'pps') {
            return response()->json([
                'message' => 'Р”РѕРєСѓРјРµРЅС‚ РЅРµ РЅР°Р№РґРµРЅ.',
            ], 404);
        }

        $allowedCategories = collect($this->ppsProfileCategoryDocumentConfig())
            ->pluck('category')
            ->all();

        $document = $application->ppsProfile?->documents
            ?->first(fn ($item) => (int) $item->id === $documentId && in_array($item->category, $allowedCategories, true));

        if (!$document) {
            return response()->json([
                'message' => 'Р”РѕРєСѓРјРµРЅС‚ РїСЂРѕС„РёР»СЏ РџРџРЎ РЅРµ РЅР°Р№РґРµРЅ.',
            ], 404);
        }

        if (!empty($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        $application = $this->adminApplicationsQuery()->findOrFail($applicationId);

        return response()->json([
            'message' => 'Р”РѕРєСѓРјРµРЅС‚ РїСЂРѕС„РёР»СЏ РџРџРЎ СѓРґР°Р»С‘РЅ.',
            'application' => $this->transformAdminApplication($application),
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $application = Application::query()->notArchived()->findOrFail($id);

        $validated = $request->validate([
            'stage' => 'nullable|in:resume,documents,compliance,hiring',
            'stage_status' => 'nullable|string|max:32',
            'status_code' => 'nullable|exists:application_statuses,code',
            'comment' => 'nullable|string|max:2000',
        ]);

        try {
            if (!empty($validated['stage'])) {
                if (empty($validated['stage_status'])) {
                    return response()->json([
                        'message' => 'Для обновления этапа требуется stage_status.',
                    ], 422);
                }

                if (
                    $validated['stage'] === 'documents'
                    && !in_array($validated['stage_status'], ['awaiting_upload', 'accepted', 'rejected'], true)
                ) {
                    return response()->json([
                        'message' => 'Для документов доступны только статусы: ожидается загрузка, приняты, отклонены.',
                    ], 422);
                }

                $application = ApplicationStageManager::setStage(
                    $application,
                    $validated['stage'],
                    $validated['stage_status'],
                    $validated['comment'] ?? null,
                    $request->user()?->id
                );
            } elseif (!empty($validated['status_code'])) {
                $application = ApplicationStageManager::setLegacyStatus(
                    $application,
                    $validated['status_code'],
                    $validated['comment'] ?? null,
                    $request->user()?->id
                );
            } else {
                return response()->json([
                    'message' => 'Укажите stage и stage_status либо status_code.',
                ], 422);
            }
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Статус этапа обновлен.',
            'application' => $this->transformAdminApplication(
                $this->adminApplicationsQuery()->findOrFail($application->id)
            ),
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
            $application = $this->createApplicationForUser($user, $vacancy, $request->file('resume'));
            $resume = $application->resume;

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
        $application = Application::with([
            'status',
            'vacancy',
            'resume',
            'documents',
            'stageLogs.author:id,name',
        ])->notArchived()->findOrFail($id);

        return response()->json($this->attachDocumentUrls($application));
    }

    public function acceptResume($id)
    {
        return $this->updateStatusCode($id, 'resume_accepted', 'Статус резюме обновлен.');
    }

    public function rejectResume($id)
    {
        return $this->updateStatusCode($id, 'resume_rejected', 'Статус резюме обновлен.');
    }

    public function acceptDocs($id)
    {
        return $this->updateStatusCode($id, 'docs_accepted', 'Статус документов обновлен.');
    }

    public function rejectDocs($id)
    {
        return $this->updateStatusCode($id, 'docs_rejected', 'Статус документов обновлен.');
    }

    public function complete($id)
    {
        return $this->updateStatusCode($id, 'completed', 'Финальный статус обновлен.');
    }

    public function userApplications(Request $request)
    {
        $applications = Application::with([
            'status:id,code,name',
            'vacancy:id,title,type',
            'resume:id,application_id,file_path',
            'documents:id,application_id,type,file_path',
            'stageLogs.author:id,name',
        ])
            ->notArchived()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        $applications->transform(fn ($application) => $this->attachDocumentUrls($application));

        return response()->json($applications);
    }

    public function uploadDocs(Request $request, $id)
    {
        $application = Application::with(['vacancy', 'status', 'documents'])
            ->notArchived()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return $this->storeApplicationDocuments(
            $request,
            $application,
            'Кандидат загрузил или обновил документы.'
        );
    }

    public function adminUploadDocs(Request $request, $id)
    {
        $application = Application::with(['vacancy', 'status', 'documents'])->notArchived()->findOrFail($id);

        return $this->storeApplicationDocuments(
            $request,
            $application,
            'Администратор загрузил или обновил документы за пользователя.'
        );
    }

    private function storeApplicationDocuments(Request $request, Application $application, string $stageComment)
    {
        if (!$this->canManageDocuments($application)) {
            return response()->json([
                'message' => 'Сейчас нельзя изменять документы.',
            ], 403);
        }

        $expected = $this->expectedDocumentTypes($application);
        $requiredTypes = $this->requiredDocumentTypes($application);
        $rules = [];

        foreach ($expected as $type) {
            $acceptedTypes = array_map(
                fn ($value) => $this->normalizeDocumentBaseType($value),
                $this->equivalentDocumentTypes($type)
            );
            $hasAnyExisting = $application->documents->contains(function ($document) use ($acceptedTypes) {
                $existingBase = $this->normalizeDocumentBaseType($this->documentBaseType($document->type));

                return in_array($existingBase, $acceptedTypes, true);
            });
            $isRequiredType = in_array($type, $requiredTypes, true);
            $baseRule = ($isRequiredType && !$hasAnyExisting) ? 'required' : 'sometimes';
            $max = in_array($type, ['scientific_works', 'other'], true) ? 5120 : 2048;
            $mimes = match ($type) {
                'scientific_works' => 'pdf,zip',
                'other' => 'pdf,doc,docx,jpg,jpeg,png,zip',
                default => 'pdf,jpg,jpeg,png',
            };

            $rules[$type] = "{$baseRule}|array|min:1";
            $rules["{$type}.*"] = "file|mimes:{$mimes}|max:{$max}";
        }

        $request->validate($rules);

        $hasNewFiles = false;
        foreach ($expected as $type) {
            $files = $request->file($type, []);
            if ($files instanceof UploadedFile) {
                $files = [$files];
            }

            if (is_array($files) && count($files) > 0) {
                $hasNewFiles = true;
                break;
            }
        }

        if (!$hasNewFiles) {
            return response()->json([
                'message' => 'Выберите хотя бы один документ.',
            ], 422);
        }

        $maxByType = [
            'diploma' => 5,
            'recommendation_letter' => 5,
            'scientific_works' => 5,
            'other' => 5,
        ];

        foreach ($expected as $type) {
            $acceptedTypes = array_map(
                fn ($value) => $this->normalizeDocumentBaseType($value),
                $this->equivalentDocumentTypes($type)
            );
            $existingCount = $application->documents->filter(function ($document) use ($acceptedTypes) {
                $existingBase = $this->normalizeDocumentBaseType($this->documentBaseType($document->type));

                return in_array($existingBase, $acceptedTypes, true);
            })->count();

            $newFiles = $request->file($type, []);
            if ($newFiles instanceof UploadedFile) {
                $newFiles = [$newFiles];
            }

            $newCount = is_array($newFiles) ? count($newFiles) : 0;
            $maxAllowed = $maxByType[$type] ?? null;

            if ($maxAllowed !== null && ($existingCount + $newCount) > $maxAllowed) {
                $label = match ($type) {
                    'diploma' => 'документов "дипломы и сертификаты"',
                    'recommendation_letter' => 'рекомендательных писем',
                    'other' => 'документов "другое"',
                    default => 'документов "список научных трудов"',
                };

                return response()->json([
                    'message' => "Можно загрузить не больше {$maxAllowed} {$label}.",
                ], 422);
            }
        }

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

                $existingTypes = ApplicationDocument::query()
                    ->where('application_id', $application->id)
                    ->pluck('type')
                    ->all();

                $acceptedTypes = array_map(
                    fn ($value) => $this->normalizeDocumentBaseType($value),
                    $this->equivalentDocumentTypes($type)
                );
                $nextIndex = 1;

                foreach ($existingTypes as $existingType) {
                    $existingBaseType = $this->normalizeDocumentBaseType($this->documentBaseType($existingType));
                    if (!in_array($existingBaseType, $acceptedTypes, true)) {
                        continue;
                    }

                    if ($existingType === $type) {
                        $nextIndex = max($nextIndex, 2);
                        continue;
                    }

                    if (preg_match('/^' . preg_quote($type, '/') . '_(\d+)$/', $existingType, $matches)) {
                        $nextIndex = max($nextIndex, ((int) $matches[1]) + 1);
                    }
                }

                foreach ($files as $file) {
                    $storedType = $nextIndex === 1 ? $type : "{$type}_{$nextIndex}";
                    $extension = $file->getClientOriginalExtension();
                    $directory = "applications/{$application->id}";
                    $path = $file->storeAs($directory, "{$storedType}.{$extension}", 'public');

                    $document = ApplicationDocument::create([
                        'application_id' => $application->id,
                        'type' => $storedType,
                        'file_path' => $path,
                    ]);

                    $savedDocs[$storedType] = [
                        'id' => $document->id,
                        'path' => $path,
                        'url' => url(Storage::url($path)),
                    ];

                    $nextIndex++;
                }
            }
        });

        $application = ApplicationStageManager::setStage(
            $application,
            'documents',
            'awaiting_upload',
            $stageComment,
            $request->user()?->id
        );

        return response()->json([
            'message' => 'Документы обновлены.',
            'documents_map' => (object) $savedDocs,
            'application' => $application,
        ]);
    }

    public function deleteDocument(Request $request, int $id, int $documentId)
    {
        $application = Application::with(['vacancy', 'status', 'documents', 'resume'])
            ->notArchived()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (!$this->canManageDocuments($application)) {
            return response()->json([
                'message' => 'Сейчас нельзя удалить документ.',
            ], 403);
        }

        $document = $application->documents->firstWhere('id', $documentId);
        if (!$document) {
            return response()->json([
                'message' => 'Документ не найден.',
            ], 404);
        }

        $baseType = $this->normalizeDocumentBaseType($this->documentBaseType($document->type));
        if (in_array($baseType, $this->requiredDocumentTypes($application), true)) {
            $count = $application->documents
                ->filter(fn ($item) => $this->normalizeDocumentBaseType($this->documentBaseType($item->type)) === $baseType)
                ->count();

            if ($count <= 1) {
                $label = $baseType === 'other' ? 'другое' : 'дипломы и сертификаты';

                return response()->json([
                    'message' => "Нельзя удалить последний обязательный документ: {$label}.",
                ], 422);
            }
        }

        if (!empty($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        $application = $application->fresh(['vacancy', 'status', 'documents', 'resume']);
        $application = $this->attachDocumentUrls($application);

        return response()->json([
            'message' => 'Документ удален.',
            'documents_map' => $application->documents_map,
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
            'stageLogs.author:id,name',
        ])
            ->notArchived()
            ->where('documents_status', 'accepted')
            ->latest()
            ->get();

        $applications->transform(fn ($application) => $this->attachDocumentUrls($application));

        return response()->json($applications);
    }

    public function lawyerSetCorruptionStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status_code' => 'required|string|max:32',
            'comment' => 'nullable|string|max:2000',
        ]);

        $application = Application::with('status')->notArchived()->findOrFail($id);

        $statusCode = match ($validated['status_code']) {
            'corruption_not_found', 'clear' => 'clear',
            'corruption_found', 'flagged' => 'flagged',
            default => null,
        };

        if ($statusCode === null) {
            return response()->json([
                'message' => 'Недопустимый статус проверки на коррупцию.',
            ], 422);
        }

        if ($application->documents_status !== 'accepted') {
            return response()->json([
                'message' => 'Юридическая проверка доступна только после принятия документов.',
            ], 422);
        }

        $application = ApplicationStageManager::setStage(
            $application,
            'compliance',
            $statusCode,
            $validated['comment'] ?? null,
            $request->user()?->id
        );

        return response()->json([
            'message' => 'Статус проверки на коррупцию обновлен.',
            'application' => $application->load([
                'user:id,name,email,phone',
                'status:id,code,name',
                'vacancy:id,title,type',
                'stageLogs.author:id,name',
            ]),
        ]);
    }

    public function adminLawyerResponsePdf(int $id)
    {
        $application = Application::with([
            'user:id,name,email,phone',
            'vacancy:id,title,type,position_id',
            'vacancy.position:id,department_id,name',
            'vacancy.position.department:id,name',
            'ppsProfile:id,application_id,desired_position,faculty_name,department_name',
            'status:id,code,name',
            'stageLogs.author:id,name',
        ])->notArchived()->findOrFail($id);

        if (!in_array($application->compliance_status, ['clear', 'flagged'], true)) {
            return response()->json([
                'message' => 'PDF ответа юриста доступен только после завершения юридической проверки.',
            ], 422);
        }

        $compliance = $application->compliance_status === 'clear' ? 'Соответствует' : 'Не соответствует';
        $lawyerDecision = $application->compliance_status === 'clear'
            ? 'Коррупционные риски не выявлены.'
            : 'Выявлены коррупционные риски.';

        $html = view('pdf.lawyer-response', [
            'application' => $application,
            'compliance' => $compliance,
            'lawyerDecision' => $lawyerDecision,
            'generatedAt' => now()->format('Y-m-d H:i:s'),
        ])->render();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = "lawyer_response_application_{$application->id}.pdf";

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    private function updateStatusCode($id, $code, $message)
    {
        $application = Application::query()->notArchived()->findOrFail($id);
        $application = ApplicationStageManager::setLegacyStatus($application, $code, null, auth()->id());

        return response()->json([
            'message' => $message,
            'application' => $application->load(['user', 'status', 'stageLogs.author:id,name']),
        ]);
    }

    private function createApplicationForUser(User $user, Vacancy $vacancy, ?UploadedFile $resumeFile): Application
    {
        return DB::transaction(function () use ($user, $vacancy, $resumeFile) {
            $statusId = ApplicationStatus::query()->where('code', 'resume_accepted')->value('id');

            $application = Application::create([
                'user_id' => $user->id,
                'vacancy_id' => $vacancy->id,
                'status_id' => $statusId,
                'resume_status' => 'accepted',
                'documents_status' => 'awaiting_upload',
                'compliance_status' => 'not_started',
                'hiring_status' => 'not_started',
            ]);

            if ($resumeFile) {
                $storedPath = $resumeFile->store("resumes/{$application->id}", 'public');

                Resume::create([
                    'application_id' => $application->id,
                    'user_id' => $user->id,
                    'file_path' => $storedPath,
                ]);
            }

            $this->attachDefaultCommissionMembers($vacancy);

            return $application->fresh(['resume']);
        });
    }

    private function createManualCandidate(string $fullName): User
    {
        $email = sprintf(
            'manual-candidate-%s-%s@hr-ai.invalid',
            now()->format('YmdHis'),
            Str::lower(Str::random(10))
        );

        $user = User::create([
            'name' => trim($fullName),
            'email' => $email,
            'password' => Str::random(32),
            'role' => 'user',
        ]);

        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function adminApplicationsQuery(?bool $archived = false)
    {
        $query = Application::query();

        if ($archived === true) {
            $query->archived();
        } elseif ($archived === false) {
            $query->notArchived();
        }

        return $query->with([
            'user',
            'archivedBy:id,name,email',
            'status',
            'vacancy',
            'vacancy.position:id,department_id,name',
            'vacancy.commissionMembers:id,name,email,phone',
            'commissionVotes:id,application_id,user_id,decision,hire_term_years,comment,updated_at',
            'resume',
            'documents',
            'ppsProfile.documents',
            'stageLogs.author:id,name',
        ]);
    }

    private function transformAdminApplication(Application $application): Application
    {
        $application = $this->attachDocumentUrls($application);
        $application = $this->attachPpsProfileDocumentUrls($application);
        $application = $this->attachVoteSummary($application);

        return $this->attachAiResult($application);
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

    private function attachVoteSummary(Application $application): Application
    {
        $vacancyMembers = collect($application->vacancy?->commissionMembers ?? [])->map(function ($member) {
            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
            ];
        });

        $allMembers = $vacancyMembers
            ->unique('id')
            ->values();

        $assignedIds = $allMembers->pluck('id')->all();
        $membersCount = count($assignedIds);
        $votes = ($application->commissionVotes ?? collect())->whereIn('user_id', $assignedIds);
        $summary = $this->calculateVoteSummary(
            $votes,
            $membersCount,
            $application->vacancy?->type === 'pps',
            $application->hiring_term_years
        );

        $application->vote_summary = [
            'total_members' => $membersCount,
            'hire' => $summary['hire'],
            'hire_1_year' => $summary['hire_1_year'],
            'hire_3_year' => $summary['hire_3_year'],
            'reject' => $summary['reject'],
            'voted' => $votes->count(),
            'pending' => max($membersCount - $votes->count(), 0),
            'approved_term_years' => $summary['approved_term_years'],
        ];

        $votesByUserId = $votes->keyBy('user_id');
        $application->vote_details = $allMembers->map(function ($member) use ($votesByUserId) {
            $vote = $votesByUserId->get($member['id']);

            return [
                'user_id' => $member['id'],
                'name' => $member['name'],
                'email' => $member['email'],
                'decision' => $vote?->decision ?? 'pending',
                'hire_term_years' => $vote?->hire_term_years,
                'comment' => $vote?->comment,
                'voted_at' => $vote?->updated_at,
            ];
        })->all();

        return $application;
    }

    private function attachDefaultCommissionMembers(Vacancy $vacancy, ?string $facultyName = null, bool $includeMain = true): void
    {
        $memberIds = $includeMain
            ? CommissionMember::query()
                ->forVacancyType($vacancy->type)
                ->pluck('user_id')
            : collect();

        if ($vacancy->type === CommissionMember::TYPE_PPS && filled($facultyName)) {
            $facultyMemberIds = PpsFacultyCommissionMember::query()
                ->where('faculty_name', trim((string) $facultyName))
                ->pluck('user_id');

            $memberIds = $memberIds->merge($facultyMemberIds);
        }

        $memberIds = $memberIds
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (count($memberIds) === 0) {
            return;
        }

        $vacancy->commissionMembers()->syncWithoutDetaching($memberIds);
    }

    private function calculateVoteSummary($votes, int $membersCount, bool $isPpsApplication, ?int $currentHiringTermYears = null): array
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
        $approvedTermYears = null;

        if ($isPpsApplication) {
            if ($hireOneYearVotes > $half) {
                $approvedTermYears = 1;
            } elseif ($hireThreeYearVotes > $half) {
                $approvedTermYears = 3;
            } elseif ($currentHiringTermYears) {
                $approvedTermYears = $currentHiringTermYears;
            }
        }

        return [
            'hire' => $hireVotes,
            'hire_1_year' => $hireOneYearVotes,
            'hire_3_year' => $hireThreeYearVotes,
            'reject' => $rejectVotes,
            'approved_term_years' => $approvedTermYears,
        ];
    }

    private function documentBaseType(string $type): string
    {
        return preg_replace('/_\d+$/', '', $type);
    }

    private function equivalentDocumentTypes(string $type): array
    {
        if ($type === 'scientific_works') {
            return ['scientific_works', 'articles'];
        }

        return [$type];
    }

    private function normalizeDocumentTypeForOutput(string $type): ?string
    {
        $baseType = $this->normalizeDocumentBaseType($this->documentBaseType($type));

        if (in_array($baseType, ['id_card', 'address_certificate'], true)) {
            return null;
        }

        if (preg_match('/_(\d+)$/', $type, $matches)) {
            return "{$baseType}_{$matches[1]}";
        }

        if (in_array($baseType, ['diploma', 'recommendation_letter', 'scientific_works', 'other'], true)) {
            return "{$baseType}_1";
        }

        return $baseType;
    }

    private function normalizeDocumentBaseType(string $type): string
    {
        return $type === 'articles' ? 'scientific_works' : $type;
    }

    private function expectedDocumentTypes(Application $application): array
    {
        if ($application->vacancy->type === 'pps') {
            return ['recommendation_letter', 'other'];
        }

        return ['diploma', 'recommendation_letter', 'other'];
    }

    private function requiredDocumentTypes(Application $application): array
    {
        return [];
    }

    private function canManageDocuments(Application $application): bool
    {
        ApplicationStageManager::ensureDefaults($application);

        return $application->resume_status === 'accepted'
            && in_array($application->documents_status, ['awaiting_upload', 'uploaded', 'rejected'], true)
            && $application->compliance_status === 'not_started'
            && !in_array($application->hiring_status, ['hired', 'rejected'], true);
    }

    private function ppsProfileCategoryDocumentConfig(): array
    {
        return [
            'basic_education_documents' => [
                'category' => 'basic_education',
                'directory' => 'basic-education',
            ],
            'magistracy_documents' => [
                'category' => 'magistracy',
                'directory' => 'magistracy',
            ],
            'scientific_degree_documents' => [
                'category' => 'scientific_degree',
                'directory' => 'scientific-degree',
            ],
            'academic_title_documents' => [
                'category' => 'academic_title',
                'directory' => 'academic-title',
            ],
            'scientific_works_documents' => [
                'category' => 'scientific_works',
                'directory' => 'scientific-works',
            ],
            'digital_mooc_documents' => [
                'category' => 'digital_mooc',
                'directory' => 'digital-mooc',
            ],
        ];
    }

    private function ppsProfileCategoryValidationRules(): array
    {
        $rules = [];

        foreach (array_keys($this->ppsProfileCategoryDocumentConfig()) as $field) {
            $rules[$field] = 'nullable|array';
            $rules["{$field}.*"] = 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240';
        }

        return $rules;
    }

    private function storePpsProfileCategoryDocuments(Request $request, ApplicationPpsProfile $profile, int $applicationId): void
    {
        foreach ($this->ppsProfileCategoryDocumentConfig() as $requestField => $config) {
            $files = $request->file($requestField, []);
            if ($files instanceof UploadedFile) {
                $files = [$files];
            }

            if (!is_array($files)) {
                continue;
            }

            foreach ($files as $index => $file) {
                $path = $this->storePpsProfileCategoryDocument(
                    $file,
                    $applicationId,
                    $profile->id,
                    $config['directory'],
                    $index
                );

                ApplicationPpsProfileDocument::create([
                    'application_pps_profile_id' => $profile->id,
                    'category' => $config['category'],
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                ]);
            }
        }
    }

    private function storePpsProfileCategoryDocument(
        UploadedFile $file,
        int $applicationId,
        int $profileId,
        string $directoryName,
        int $index
    ): string {
        $extension = $file->getClientOriginalExtension();
        $directory = "applications/{$applicationId}/pps-profile/{$directoryName}";
        $filename = now()->format('YmdHisv') . "-{$profileId}-{$index}.{$extension}";

        return $file->storeAs($directory, $filename, 'public');
    }

    private function replacePpsProfileDocument(?string $oldPath, UploadedFile $file, int $applicationId, string $field): string
    {
        if (!empty($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $extension = $file->getClientOriginalExtension();
        $directory = "applications/{$applicationId}/pps-profile";

        return $file->storeAs($directory, "{$field}.{$extension}", 'public');
    }

    private function emptyToNull($value)
    {
        return $value === '' ? null : $value;
    }

    private function attachAiResult(Application $application): Application
    {
        $workerId = $application->user_id;
        $positionId = $this->resolveApplicationPositionId($application);
        $application->ai_position_id = $positionId;

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

    private function isAllowedPpsVacancyTitle(string $title): bool
    {
        $normalizedTitle = $this->normalizePpsVacancyTitle($title);

        foreach (self::PPS_VACANCY_TITLES as $allowedTitle) {
            if ($normalizedTitle === $this->normalizePpsVacancyTitle($allowedTitle)) {
                return true;
            }
        }

        return false;
    }

    private function normalizePpsVacancyTitle(string $title): string
    {
        $title = str_replace(['«', '»', '"', "'", '`'], '', $title);

        return Str::lower((string) preg_replace('/\s+/u', ' ', trim($title)));
    }

    private function resolveVacancyForPosition(Vacancy $templateVacancy, Position $position): Vacancy
    {
        if ((int) $templateVacancy->position_id === (int) $position->id) {
            return $templateVacancy;
        }

        return Vacancy::query()->firstOrCreate(
            [
                'title' => $position->name,
                'type' => $templateVacancy->type,
                'position_id' => $position->id,
            ],
            [
                'description' => $templateVacancy->description,
            ],
        );
    }

    private function resolveApplicationPositionId(Application $application): ?int
    {
        if (!empty($application->vacancy?->position_id)) {
            return (int) $application->vacancy->position_id;
        }

        if ($application->vacancy?->type !== 'pps' || !$application->ppsProfile) {
            return null;
        }

        $departmentName = trim((string) $application->ppsProfile->department_name);
        $positionName = trim((string) ($application->ppsProfile->desired_position ?: $application->vacancy?->title));

        if ($departmentName === '' || $positionName === '') {
            return null;
        }

        $departmentId = Department::query()
            ->where('name', $departmentName)
            ->value('id');

        if (!$departmentId) {
            return null;
        }

        $query = Position::query()->where('department_id', $departmentId);
        $position = (clone $query)->where('name', $positionName)->first(['id']);

        if (!$position) {
            $position = (clone $query)->where('name', 'like', "{$positionName}%")->first(['id']);
        }

        if (!$position) {
            $position = (clone $query)->where('name', 'like', "%{$positionName}%")->first(['id']);
        }

        return $position?->id ? (int) $position->id : null;
    }

    private function globalCommissionMembers(?string $vacancyType = null)
    {
        $cacheKey = $vacancyType ?: 'all';

        if (array_key_exists($cacheKey, $this->globalCommissionMembersCache)) {
            return $this->globalCommissionMembersCache[$cacheKey];
        }

        $ids = CommissionMember::query()
            ->forVacancyType($vacancyType)
            ->pluck('user_id')
            ->all();

        if (empty($ids)) {
            $this->globalCommissionMembersCache[$cacheKey] = collect();

            return $this->globalCommissionMembersCache[$cacheKey];
        }

        $this->globalCommissionMembersCache[$cacheKey] = User::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'email'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            });

        return $this->globalCommissionMembersCache[$cacheKey];
    }

    private function facultyCommissionMembers(Application $application)
    {
        if ($application->vacancy?->type !== CommissionMember::TYPE_PPS) {
            return collect();
        }

        $facultyName = trim((string) ($application->ppsProfile?->faculty_name ?? ''));
        if ($facultyName === '') {
            return collect();
        }

        if (!array_key_exists($facultyName, $this->facultyCommissionMembersCache)) {
            $this->facultyCommissionMembersCache[$facultyName] = PpsFacultyCommissionMember::query()
                ->where('faculty_name', $facultyName)
                ->with('user:id,name,email')
                ->get()
                ->pluck('user')
                ->filter()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ];
                })
                ->values();
        }

        return $this->facultyCommissionMembersCache[$facultyName];
    }
}
