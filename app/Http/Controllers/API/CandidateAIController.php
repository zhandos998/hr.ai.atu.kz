<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CandidateAIResult;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class CandidateAIController extends Controller
{
    public function analyze(Request $request)
    {
        set_time_limit(180);
        $request->validate([
            'worker_id' => 'required|integer',
            'position_id' => 'required|integer|exists:positions,id',
            'lang'      => 'nullable|string|in:ru,kk,en',
        ]);

        $workerId = $request->worker_id;
        $positionId = $request->position_id;
        $lang = $request->input('lang', 'ru');

        $existing = CandidateAIResult::where('worker_id', $workerId)
            ->where('position_id', $positionId)
            ->first();

        if ($existing) {
            return response()->json([
                'cached' => true,
                'score' => $existing->score,
                'decision' => $existing->decision,
                'education_match' => $existing->education_match,
                'experience_match' => $existing->experience_match,
                'soft_skills_match' => $existing->soft_skills_match,
                'summary' => $existing->{"summary_$lang"}
            ]);
        }

        $positionModel = Position::findOrFail($positionId);

        // реальная вакансия для анализа
        $positionName = $positionModel->name;
        $duties = $positionModel->duties;
        $qualification = $positionModel->qualification;

        // ==== 1. Описание инструмента (function calling) ====
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_candidate',
                    'description' => 'Анализ кандидата по данным с konkurs.atu.kz',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'worker_id' => ['type' => 'integer'],
                            'position_id' => ['type' => 'integer'],
                            'position' => ['type' => 'string'],
                            'duties' => ['type' => 'string'],
                            'qualification' => ['type' => 'string']
                        ],
                        'required' => ['worker_id', 'position_id', 'position']
                    ]
                ]
            ]
        ];

        // ==== 2. System prompt ====
        $system = <<<SYS
        Ты — эксперт отдела кадров АТУ.
        Ты анализируешь документы кандидатов и даёшь объективную оценку по шкале 0–10.
        SYS;

        // ==== 3. Message для AI ====
        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => json_encode([
                'worker_id'   => $workerId,
                'position_id' => $positionId,
                'position'    => $positionName,
                'duties'      => $duties,
                'qualification' => $qualification
            ], JSON_UNESCAPED_UNICODE)],
        ];

        // Первый вызов — модель выбирает инструмент
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'temperature' => 0.2
        ]);

        $choice = $response->choices[0];

        // ==== 4. Если AI вызвал инструмент ====
        if (!empty($choice->message->toolCalls)) {
            $toolCall = $choice->message->toolCalls[0];
            $args = json_decode($toolCall->function->arguments, true);

            $result = $this->toolCheckCandidate($args);

            // отправляем результат в модель
            $messages[] = $choice->message->toArray();
            $messages[] = [
                'role' => 'tool',
                'tool_call_id' => $toolCall->id,
                'name' => 'check_candidate',
                'content' => json_encode($result, JSON_UNESCAPED_UNICODE)
            ];
            $messages[] = [
                'role' => 'system',
                'content' => $this->getLanguageInstruction($lang)
            ];


            // итоговый генеративный ответ
            $final = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'temperature' => 0.2,
                'response_format' => [
                    "type" => "json_schema",
                    "json_schema" => [
                        "name" => "final_response",
                        "schema" => [
                            "type" => "object",
                            "properties" => [
                                "score" => ["type" => "number"],
                                "decision" => [
                                    "type" => "string",
                                    "description" => "Решение по кандидату",
                                ],
                                "education_match" => ["type" => "number"],
                                "experience_match" => ["type" => "number"],
                                "soft_skills_match" => ["type" => "number"],
                                "summary" => ["type" => "string"]
                            ],
                            "required" => ["score", "decision", "summary"]
                        ]
                    ]
                ]
            ]);

            Log::info("FINAL MESSAGE:", [$final->choices[0]->message->content]);
            $raw = $final->choices[0]->message->content;
            $json = json_decode($raw, true);

            if (!$json) {
                return response()->json([
                    'error' => 'invalid_json',
                    'raw' => $raw
                ]);
            }

            $summaries = $this->generateSummaries($json['summary']);
            // print_r($summaries);
            CandidateAIResult::updateOrCreate(
                [
                    'worker_id'   => $workerId,
                    'position_id' => $positionId
                ],
                [
                    'score'             => $json['score'],
                    'decision'          => $json['decision'], // на русском
                    'education_match'   => $json['education_match'] ?? null,
                    'experience_match'  => $json['experience_match'] ?? null,
                    'soft_skills_match' => $json['soft_skills_match'] ?? null,
                    'summary_ru'        => $summaries['ru'],
                    'summary_kk'        => $summaries['kk'],
                    'summary_en'        => $summaries['en'],
                ]
            );

            switch ($lang) {
                case 'kk':
                    $json['summary'] = $summaries['kk'];
                    break;
                case 'en':
                    $json['summary'] = $summaries['en'];
                    break;
                default:
                    $json['summary'] = $summaries['ru'];
                    break;
            }
            return response()->json($json);
        }

        return ['error' => 'Tool was not called'];
    }


    // =======================================================================
    // ========================== ИНСТРУМЕНТ ================================
    // =======================================================================

    private function toolCheckCandidate(array $args): array
    {
        $workerId = $args['worker_id'];
        $positionName = $args['position'];
        $duties = $args['duties'] ?? '';
        $qualification = $args['qualification'] ?? '';

        // 1) Загружаем HTML блоки
        $conclusion = $this->loadPage("https://konkurs.atu.kz/?page=application/conclusion&worker_id={$workerId}");
        $protocol   = $this->loadPage("https://konkurs.atu.kz/?page=application/protocol&worker_id={$workerId}");

        // 2) Находим все документы
        $documents = $this->autoScanDocuments($workerId);

        // 3) Vision анализ документов
        $aiResult = $this->visionAnalyze(
            $positionName,
            $duties,
            $qualification,
            $conclusion,
            $protocol,
            $documents
        );

        Log::info("TOOL RESULT:", $aiResult);
        // 4) Финальный расчёт score (0–10)
        $score = $this->calculateScore($aiResult);

        return [
            'score' => round($score, 2),
            'match_details' => [
                'education_match'   => $aiResult['education_match'],
                'experience_match'  => $aiResult['experience_match'],
                'soft_skills_match' => $aiResult['soft_skills_match'],
            ],
            'decision' => $aiResult['final_decision'],
            'summary'  => $aiResult['summary'],
        ];
    }

    private function loadPage(string $url): string
    {
        $res = Http::get($url);
        return $res->successful() ? $res->body() : "";
    }

    private function autoScanDocuments(int $workerId): array
    {
        $url = "https://konkurs.atu.kz/uploads/{$workerId}/";
        $html = Http::get($url)->body();

        preg_match_all('/href="([^"]+)"/', $html, $m);

        $files = [];

        foreach ($m[1] as $f) {
            if (preg_match('/\.(jpeg|jpg|png|pdf|docx)$/i', $f)) {
                $files[] = $url . $f;
            }
        }

        return $files;
    }

    private function visionAnalyze(string $position, string $duties, string $qualification, string $conclusion, string $protocol, array $documents): array
    {
        $content = [];

        // текстовая часть
        $content[] = [
            "type" => "text",
            "text" => "Должность: {$position}\n\n" .
                "Должностные обязанности:\n{$duties}\n\n" .
                "Требования к квалификации:\n{$qualification}\n\n" .
                "Заключение комиссии:\n{$conclusion}\n\n" .
                "Протокол:\n{$protocol}"
        ];

        // изображения (base64)
        foreach ($documents as $doc) {
            $base64 = $this->fetchImageAsBase64($doc);

            if ($base64) {
                $content[] = [
                    "type" => "image_url",
                    "image_url" => [
                        "url" => $base64
                    ]
                ];
            }
        }

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    "role" => "system",
                    "content" => "Проанализируй документы кандидата и выдай JSON."
                ],
                [
                    "role" => "user",
                    "content" => $content
                ]
            ],
            'response_format' => [
                "type" => "json_schema",
                "json_schema" => [
                    "name" => "analysis",
                    "schema" => [
                        "type" => "object",
                        "properties" => [
                            "education_match" => ["type" => "number"],
                            "experience_match" => ["type" => "number"],
                            "soft_skills_match" => ["type" => "number"],
                            "final_decision" => ["type" => "string"],
                            "summary" => ["type" => "string"]
                        ],
                        "required" => [
                            "education_match",
                            "experience_match",
                            "soft_skills_match",
                            "final_decision",
                            "summary"
                        ]
                    ]
                ]
            ]
        ]);
        Log::channel('single')->info("VISION RAW: " . $response->choices[0]->message->content);
        return json_decode($response->choices[0]->message->content, true);
    }

    private function calculateScore(array $a): float
    {
        return
            $a['education_match']   * 0.4 +
            $a['experience_match']  * 0.3 +
            $a['soft_skills_match'] * 0.1;
    }

    private function fetchImageAsBase64(string $url): ?string
    {
        try {
            $res = Http::timeout(15)->get($url);

            if (!$res->successful()) {
                return null;
            }

            $content = $res->body();
            $mime = $res->header('Content-Type') ?? 'image/jpeg';
            $base64 = base64_encode($content);

            return "data:{$mime};base64,{$base64}";
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getLanguageInstruction(string $lang): string
    {
        return 'Отвечай строго на русском языке. Все JSON-поля (summary, decision, и другие) должны быть на русском.';
        // return match ($lang) {
        //     'kk' => 'Барлық жауапты қазақ тілінде бер. JSON ішіндегі summary, decision және басқа мәтіндер қазақ тілінде болсын.',
        //     'en' => 'Respond strictly in English. All JSON fields such as summary and decision must be in English.',
        //     default => 'Отвечай строго на русском языке. Все JSON-поля (summary, decision, и другие) должны быть на русском.',
        // };
    }

    private function generateSummaries(string $summary): array
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    "role" => "system",
                    "content" => "Ты переводчик и редактор HR-отчётов. Переведи текст на три языка."
                ],
                [
                    "role" => "user",
                    "content" => "Переведи текст на три языка. ВЕРНИ ТОЛЬКО JSON строго такой структуры:
                    {\"ru\": \"...\", \"kk\": \"...\", \"en\": \"...\"}\n\nТекст:\n$summary"
                ]
            ],
            'response_format' => [
                "type" => "json_schema",
                "json_schema" => [
                    "name" => "summaries",
                    "schema" => [
                        "type" => "object",
                        "properties" => [
                            "ru" => ["type" => "string"],
                            "kk" => ["type" => "string"],
                            "en" => ["type" => "string"]
                        ],
                        "required" => ["ru", "kk", "en"]
                    ]
                ]
            ]
        ]);

        return json_decode($response->choices[0]->message->content, true);
    }
}
