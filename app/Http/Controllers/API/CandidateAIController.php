<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\CandidateAIResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use PhpOffice\PhpWord\IOFactory;
use Throwable;

class CandidateAIController extends Controller
{
    private const MAX_DOCUMENTS = 25;
    private const MAX_TEXT_CHARS_PER_DOCUMENT = 12000;
    private const MAX_IMAGE_BYTES = 8_000_000;

    public function analyze(Request $request)
    {
        set_time_limit(180);

        $validated = $request->validate([
            'application_id' => 'required|integer|exists:applications,id',
            'lang' => 'nullable|string|in:ru,kk,en',
            'force' => 'nullable|boolean',
        ]);

        $lang = $validated['lang'] ?? 'ru';
        $force = (bool) ($validated['force'] ?? false);

        $application = Application::with([
            'user:id,name,email,phone',
            'status:id,code,name',
            'vacancy:id,title,description,type,position_id',
            'vacancy.position:id,department_id,name,duties,qualification',
            'vacancy.position.department:id,name,parent_id',
            'resume:id,application_id,file_path',
            'documents:id,application_id,type,file_path',
            'ppsProfile.documents',
            'stageLogs.author:id,name,email',
        ])->findOrFail((int) $validated['application_id']);

        $positionId = $this->resolvePositionId($application);
        $existing = CandidateAIResult::query()
            ->where('application_id', $application->id)
            ->where('lang', $lang)
            ->first();

        if (!$force && $existing && $this->isCompleteResult($existing)) {
            return response()->json($this->resultResponse($existing, $lang, true));
        }

        try {
            $applicationData = $this->buildApplicationData($application);
            $documents = $this->collectApplicationDocuments($application);
            $documentContent = $this->extractDocumentContent($documents);
            $json = $this->analyzeWithOpenAI($applicationData, $documentContent, $lang);
            $summaries = $this->generateSummaries($json['summary'] ?? '');

            $result = CandidateAIResult::query()->updateOrCreate(
                [
                    'application_id' => $application->id,
                    'lang' => $lang,
                ],
                [
                    'worker_id' => $application->user_id,
                    'position_id' => $positionId ?: 0,
                    'score' => $json['score'] ?? null,
                    'decision' => $json['decision'] ?? null,
                    'education_match' => $json['education_match'] ?? null,
                    'experience_match' => $json['experience_match'] ?? null,
                    'soft_skills_match' => $json['soft_skills_match'] ?? null,
                    'summary_ru' => $summaries['ru'] ?? ($json['summary'] ?? null),
                    'summary_kk' => $summaries['kk'] ?? ($json['summary'] ?? null),
                    'summary_en' => $summaries['en'] ?? ($json['summary'] ?? null),
                ],
            );

            return response()->json($this->resultResponse($result, $lang, false));
        } catch (Throwable $exception) {
            Log::error('Candidate AI analysis failed', [
                'application_id' => $application->id,
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Ошибка при генерации AI-анализа.',
            ], 500);
        }
    }

    private function buildApplicationData(Application $application): array
    {
        $position = $application->vacancy?->position;
        $department = $position?->department;
        $ppsProfile = $application->ppsProfile;

        return [
            'application' => [
                'id' => $application->id,
                'created_at' => optional($application->created_at)->toDateTimeString(),
                'updated_at' => optional($application->updated_at)->toDateTimeString(),
                'hiring_term_years' => $application->hiring_term_years,
            ],
            'candidate' => [
                'name' => $application->user?->name,
                'email' => $application->user?->email,
                'phone' => $application->user?->phone,
            ],
            'vacancy' => [
                'type' => $application->vacancy?->type,
                'title' => $application->vacancy?->title,
                'description' => $application->vacancy?->description,
                'position' => [
                    'name' => $position?->name,
                    'duties' => $position?->duties,
                    'qualification' => $position?->qualification,
                ],
                'department' => [
                    'name' => $department?->name,
                ],
            ],
            'pps_profile' => $ppsProfile ? [
                'full_name' => $ppsProfile->full_name,
                'desired_position' => $ppsProfile->desired_position,
                'faculty_name' => $ppsProfile->faculty_name,
                'department_name' => $ppsProfile->department_name,
                'birth_year' => $ppsProfile->birth_year,
                'basic_education' => $ppsProfile->basic_education,
                'magistracy' => $ppsProfile->magistracy,
                'scientific_degree' => $ppsProfile->scientific_degree,
                'academic_title' => $ppsProfile->academic_title,
                'scientific_works' => $ppsProfile->scientific_works,
                'science_conclusion' => $ppsProfile->science_conclusion,
                'digital_mooc' => $ppsProfile->digital_mooc,
                'final_rating_score' => $ppsProfile->final_rating_score,
                'student_survey_results' => $ppsProfile->student_survey_results,
                'krk' => $ppsProfile->krk,
                'open_lesson_quality' => $ppsProfile->open_lesson_quality,
                'taught_disciplines' => $ppsProfile->taught_disciplines,
                'educational_methodical_literature' => $ppsProfile->educational_methodical_literature,
                'academic_conclusion' => $ppsProfile->academic_conclusion,
                'educational_publication_metrics' => $ppsProfile->educational_publication_metrics,
                'anti_corruption_survey_results' => $ppsProfile->anti_corruption_survey_results,
                'disciplinary_actions_info' => $ppsProfile->disciplinary_actions_info,
                'work_experience' => $ppsProfile->work_experience,
            ] : null,
            'workflow' => [
                'current_status' => [
                    'code' => $application->status?->code,
                    'name' => $application->status?->name,
                ],
                'resume_status' => $application->resume_status,
                'resume_comment' => $application->resume_comment,
                'documents_status' => $application->documents_status,
                'documents_comment' => $application->documents_comment,
                'compliance_status' => $application->compliance_status,
                'compliance_comment' => $application->compliance_comment,
                'hiring_status' => $application->hiring_status,
                'hiring_comment' => $application->hiring_comment,
            ],
            'stage_logs' => $application->stageLogs
                ->take(20)
                ->map(fn ($log) => [
                    'stage' => $log->stage,
                    'old_status' => $log->old_status,
                    'new_status' => $log->new_status,
                    'comment' => $log->comment,
                    'author' => $log->author?->name,
                    'created_at' => optional($log->created_at)->toDateTimeString(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function collectApplicationDocuments(Application $application): array
    {
        $documents = [];

        if ($application->resume?->file_path) {
            $documents[] = [
                'source' => 'Резюме',
                'type' => 'resume',
                'name' => basename($application->resume->file_path),
                'path' => $application->resume->file_path,
            ];
        }

        foreach ($application->documents as $document) {
            $documents[] = [
                'source' => 'Документы кандидата',
                'type' => $document->type,
                'name' => basename($document->file_path),
                'path' => $document->file_path,
            ];
        }

        foreach ($application->ppsProfile?->documents ?? [] as $document) {
            $documents[] = [
                'source' => 'Профиль ППС',
                'type' => $document->category,
                'name' => $document->original_name ?: basename($document->file_path),
                'path' => $document->file_path,
            ];
        }

        return array_slice($documents, 0, self::MAX_DOCUMENTS);
    }

    private function extractDocumentContent(array $documents): array
    {
        $items = [];
        $images = [];

        foreach ($documents as $document) {
            $item = [
                'source' => $document['source'],
                'type' => $document['type'],
                'name' => $document['name'],
                'status' => 'available',
                'text' => null,
            ];

            if (!Storage::disk('public')->exists($document['path'])) {
                $item['status'] = 'missing_in_storage';
                $items[] = $item;
                continue;
            }

            $absolutePath = Storage::disk('public')->path($document['path']);
            $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
            $mime = Storage::disk('public')->mimeType($document['path']) ?: $this->mimeFromExtension($extension);

            try {
                if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                    $image = $this->buildImageContent($absolutePath, $mime, $document['name']);
                    if ($image) {
                        $images[] = $image;
                        $item['status'] = 'sent_as_image';
                    } else {
                        $item['status'] = 'image_too_large_or_unreadable';
                    }
                } elseif ($extension === 'pdf') {
                    $item['text'] = $this->limitText(
                        $this->extractPdfTextWithOpenAI($absolutePath, $document['name'])
                    );
                    $item['status'] = filled($item['text']) ? 'text_extracted' : 'no_readable_text';
                } elseif (in_array($extension, ['doc', 'docx'], true)) {
                    $item['text'] = $this->limitText($this->docxToText($absolutePath));
                    $item['status'] = filled($item['text']) ? 'text_extracted' : 'no_readable_text';
                } else {
                    $item['text'] = $this->limitText($this->readPlainTextFile($absolutePath));
                    $item['status'] = filled($item['text']) ? 'text_extracted' : 'unsupported_file_type';
                }
            } catch (Throwable $exception) {
                Log::warning('Candidate AI document extraction failed', [
                    'path' => $document['path'],
                    'message' => $exception->getMessage(),
                ]);
                $item['status'] = 'extraction_failed';
            }

            $items[] = $item;
        }

        return [
            'items' => $items,
            'images' => $images,
        ];
    }

    private function analyzeWithOpenAI(array $applicationData, array $documentContent, string $lang): array
    {
        $text = [
            'Проанализируй кандидата только по данным этой заявки и приложенным локальным документам.',
            'Не обращайся к внешним сайтам и не придумывай отсутствующие сведения.',
            'Если данных недостаточно, прямо укажи это в summary и снижай соответствующие оценки.',
            'Оценки должны быть числами от 0 до 10.',
            'Верни только JSON по заданной схеме.',
            '',
            'Данные заявки:',
            json_encode($applicationData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            '',
            'Документы заявки:',
            json_encode($documentContent['items'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ];

        $content = [
            [
                'type' => 'text',
                'text' => implode("\n", $text),
            ],
        ];

        foreach ($documentContent['images'] as $image) {
            $content[] = [
                'type' => 'text',
                'text' => "Изображение документа: {$image['name']}",
            ];
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $image['data_url'],
                ],
            ];
        }

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt($lang),
                ],
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
            'temperature' => 0.2,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'candidate_application_analysis',
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 10],
                            'decision' => ['type' => 'string'],
                            'education_match' => ['type' => 'number', 'minimum' => 0, 'maximum' => 10],
                            'experience_match' => ['type' => 'number', 'minimum' => 0, 'maximum' => 10],
                            'soft_skills_match' => ['type' => 'number', 'minimum' => 0, 'maximum' => 10],
                            'summary' => ['type' => 'string'],
                        ],
                        'required' => [
                            'score',
                            'decision',
                            'education_match',
                            'experience_match',
                            'soft_skills_match',
                            'summary',
                        ],
                    ],
                ],
            ],
        ]);

        $json = json_decode($response->choices[0]->message->content ?? '', true);

        if (!is_array($json)) {
            throw new \RuntimeException('OpenAI returned invalid JSON for candidate analysis.');
        }

        return $json;
    }

    private function extractPdfTextWithOpenAI(string $absolutePath, string $filename): ?string
    {
        if (!is_readable($absolutePath)) {
            return null;
        }

        $raw = file_get_contents($absolutePath);
        if ($raw === false || $raw === '') {
            return null;
        }

        $response = OpenAI::responses()->create([
            'model' => 'gpt-4o-mini',
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => 'Извлеки весь читаемый текст из PDF. Верни только текст, без комментариев.',
                        ],
                        [
                            'type' => 'input_file',
                            'filename' => $filename ?: 'document.pdf',
                            'file_data' => 'data:application/pdf;base64,' . base64_encode($raw),
                        ],
                    ],
                ],
            ],
        ]);

        return $response->outputText ?? null;
    }

    private function generateSummaries(string $summary): array
    {
        if (trim($summary) === '') {
            return ['ru' => null, 'kk' => null, 'en' => null];
        }

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Ты переводчик и редактор HR-отчетов. Верни только JSON.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Переведи текст на русский, казахский и английский. Верни JSON структуры {\"ru\":\"...\",\"kk\":\"...\",\"en\":\"...\"}.\n\nТекст:\n{$summary}",
                    ],
                ],
                'temperature' => 0.1,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'summaries',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'ru' => ['type' => 'string'],
                                'kk' => ['type' => 'string'],
                                'en' => ['type' => 'string'],
                            ],
                            'required' => ['ru', 'kk', 'en'],
                        ],
                    ],
                ],
            ]);

            $json = json_decode($response->choices[0]->message->content ?? '', true);

            return is_array($json)
                ? $json
                : ['ru' => $summary, 'kk' => $summary, 'en' => $summary];
        } catch (Throwable $exception) {
            Log::warning('Candidate AI summary translation failed', [
                'message' => $exception->getMessage(),
            ]);

            return ['ru' => $summary, 'kk' => $summary, 'en' => $summary];
        }
    }

    private function systemPrompt(string $lang): string
    {
        $language = match ($lang) {
            'kk' => 'казахском',
            'en' => 'английском',
            default => 'русском',
        };

        return <<<PROMPT
Ты HR-эксперт АТУ. Твоя задача - оценить соответствие кандидата заявленной должности по данным заявки.
Используй только данные, переданные в запросе: карточка заявки, профиль ППС/АУП, статусы, комментарии и локальные документы.
Не используй внешние сайты, не предполагай факты и не добавляй сведения, которых нет в заявке.
Оцени образование, опыт и soft skills по шкале 0-10. Итоговый score тоже 0-10.
decision верни кратко: "подходит", "рассмотреть" или "не подходит".
summary напиши на {$language} языке.
PROMPT;
    }

    private function resultResponse(CandidateAIResult $result, string $lang, bool $cached): array
    {
        $summary = match ($lang) {
            'kk' => $result->summary_kk,
            'en' => $result->summary_en,
            default => $result->summary_ru,
        };

        return [
            'cached' => $cached,
            'score' => $result->score,
            'decision' => $result->decision,
            'education_match' => $result->education_match,
            'experience_match' => $result->experience_match,
            'soft_skills_match' => $result->soft_skills_match,
            'summary' => $summary,
        ];
    }

    private function isCompleteResult(CandidateAIResult $result): bool
    {
        return $result->score !== null
            && $result->decision !== null
            && ($result->summary_ru !== null || $result->summary_kk !== null || $result->summary_en !== null);
    }

    private function resolvePositionId(Application $application): ?int
    {
        if ($application->vacancy?->position_id) {
            return (int) $application->vacancy->position_id;
        }

        return null;
    }

    private function buildImageContent(string $absolutePath, string $mime, string $name): ?array
    {
        if (!is_readable($absolutePath) || filesize($absolutePath) > self::MAX_IMAGE_BYTES) {
            return null;
        }

        $raw = file_get_contents($absolutePath);
        if ($raw === false || $raw === '') {
            return null;
        }

        return [
            'name' => $name,
            'data_url' => 'data:' . ($mime ?: 'image/jpeg') . ';base64,' . base64_encode($raw),
        ];
    }

    private function docxToText(string $absolutePath): ?string
    {
        $phpWord = IOFactory::load($absolutePath);
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }

        return trim($text);
    }

    private function readPlainTextFile(string $absolutePath): ?string
    {
        if (!is_readable($absolutePath)) {
            return null;
        }

        $raw = file_get_contents($absolutePath);
        if ($raw === false) {
            return null;
        }

        if (!mb_detect_encoding($raw, 'UTF-8', true)) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'auto');
        }

        return trim($raw);
    }

    private function limitText(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, self::MAX_TEXT_CHARS_PER_DOCUMENT);
    }

    private function mimeFromExtension(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            default => 'text/plain',
        };
    }
}
