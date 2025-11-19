<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CandidateAIResult;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use PhpOffice\PhpWord\IOFactory;

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
            if ($existing->score == null) {
                return response()->json([
                    'cached' => false,
                    'score' => $existing->score,
                    'decision' => $existing->decision,
                    'education_match' => $existing->education_match,
                    'experience_match' => $existing->experience_match,
                    'soft_skills_match' => $existing->soft_skills_match,
                    'summary' => $existing->{"summary_$lang"}
                ]);
            }
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

        CandidateAIResult::updateOrCreate(
            [
                'worker_id'   => $workerId,
                'position_id' => $positionId
            ],
            []
        );

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
        Ни при каких условиях не выдумывай данные.
        Все оценки выводи только числами (без %), строго в диапазоне 0–10.
        Если soft-skills явно не указаны, но могут быть логически подразумеваемы для должности, оценивай их как 3–6.
        Если полностью отсутствуют любые намёки — ставь 0.
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
                                "score" => [
                                    "type" => "number",
                                    "minimum" => 0,
                                    "maximum" => 10
                                ],
                                "decision" => [
                                    "type" => "string",
                                    "description" => "Краткое решение (только: подходит / рассмотреть / не подходит)",
                                ],
                                "education_match" => [
                                    "type" => "number",
                                    "minimum" => 0,
                                    "maximum" => 10
                                ],
                                "experience_match" => [
                                    "type" => "number",
                                    "minimum" => 0,
                                    "maximum" => 10
                                ],
                                "soft_skills_match" => [
                                    "type" => "number",
                                    "minimum" => 0,
                                    "maximum" => 10
                                ],
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
        $conclusion = $this->loadPage("https://konkurs.atu.kz/application/conclusion.php?worker_id={$workerId}");

        // $protocol   = $this->loadPage("https://konkurs.atu.kz/application/protocol.php?worker_id={$workerId}");

        // Log::info("FINAL MESSAGE:", [$conclusion]);
        // Log::info("FINAL MESSAGE:", [$protocol]);
        // 2) Находим все документы
        $documents = $this->autoScanDocuments($workerId);
        Log::info("documents:", [count($documents)]);
        if (count($documents) === 0) {
            return [
                'education_match' => 0,
                'experience_match' => 0,
                'soft_skills_match' => 0,
                'final_decision' => 'не подходит',
                'summary' => 'Кандидат не предоставил документы. Анализ невозможен.'
            ];
        }

        // 3) Vision анализ документов
        $aiResult = $this->visionAnalyze(
            $positionName,
            $duties,
            $qualification,
            $conclusion,
            // $protocol,
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
            // if (preg_match('/\.(jpeg|jpg|png)$/i', $f)) {
            //     $files[] = $url . $f;
            // }
            if (preg_match('/\.(jpeg|jpg|png|pdf|docx)$/i', $f)) {
                $files[] = $url . $f;
            }
        }

        return $files;
    }

    private function visionAnalyze(string $position, string $duties, string $qualification, string $conclusion, array $documents): array
    {
        $content = [];

        $content[] = [
            "type" => "text",
            "text" => "Должность: {$position}\n\n" .
                "Должностные обязанности:\n{$duties}\n\n" .
                "Требования к квалификации:\n{$qualification}\n\n" .
                "Заключение комиссии:\n{$conclusion}"
        ];

        $hasRealData = false;

        foreach ($documents as $doc) {

            $res = Http::timeout(20)->get($doc);

            if (!$res->successful()) continue;

            $mime = $res->header('Content-Type');
            Log::info("MIME:", [$mime]);
            // images
            if (str_starts_with($mime, 'image/')) {
                $hasRealData = true;
                $base64 = "data:$mime;base64," . base64_encode($res->body());
                $content[] = [
                    "type" => "image_url",
                    "image_url" => ["url" => $base64]
                ];
            }

            // PDF
            elseif ($mime === 'application/pdf') {

                $raw = $res->body();

                Log::info("PDF SIZE:", [strlen($raw)]);
                Log::info("PDF HEADERS:", [$res->headers()]);
                // 2) Если текст пустой или каша -> запускаем AI OCR
                Log::info("PDF: fallback to AI OCR");
                $text = $this->aiExtractPdfText($raw);

                // if (!$text || mb_strlen($text) < 10) {
                //     Log::info("PDF: fallback to AI OCR");
                //     $text = $this->aiExtractPdfText($raw);
                // }

                if ($text && mb_strlen($text) > 10) {
                    $hasRealData = true;
                    $content[] = ["type" => "text", "text" => "PDF OCR:\n$text"];
                    Log::info("PDF OCR TEXT:", [$text]);
                } else {
                    Log::info("PDF: no readable text");
                }
            }

            // DOCX
            elseif ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {

                $path = storage_path('temp.docx');
                file_put_contents($path, $res->body());

                $text = $this->cleanText($this->docxToText($path));
                if (!$text || mb_strlen($text) < 10) continue;
                // $text = $this->toUTF8($text);
                unlink($path);

                if (trim($text) !== '') {
                    $hasRealData = true;
                    $content[] = ["type" => "text", "text" => "DOCX:\n$text"];
                    Log::info("Docx:", [$text]);
                }
                Log::info("Docx:", ['none']);
            }
        }

        // Если нет ни одного реального документа
        if (!$hasRealData) {
            return [
                "education_match" => 0,
                "experience_match" => 0,
                "soft_skills_match" => 0,
                "final_decision" => "не подходит",
                "summary" => "Кандидат не предоставил документы. Анализ невозможен."
            ];
        }

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    "role" => "system",
                    "content" => "Ты HR-эксперт АТУ.

                    Правила анализа:

                    1) НЕ выдумывай новые данные.
                    2) Если документ явно является дипломом/сертификатом образования
                    (например, файл называется 'diplom', 'diploma', 'диплом', 'аттестат',
                    или содержит слова 'бакалавр', 'магистр', 'университет'),
                    ставь education_match = 5–10, даже если текст распознан частично.
                    3) Если документ явно является справкой о стаже, сертификатом курсов, резюме
                    или содержит слова 'опыт', 'работал', 'стаж', 'должность', 'год',
                    ставь experience_match = 5–10.
                    4) Если документ — изображение диплома, но текст не распознан — всё равно
                    считай образование подтверждённым.
                    5) Если документ содержит признаки soft-skills (коммуникация, ответственность,
                    организация, аналитика, дисциплина) — оценивай soft_skills_match = 3–6.
                    6) Если нет ни одного признака — ставь 0.
                    7) НИКОГДА не придумывай образование/опыт, если документ явно НЕ является дипломом
                    или справкой.

                    Выдай JSON."
                ],
                ["role" => "user", "content" => $content]
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
                        "required" => ["education_match", "experience_match", "soft_skills_match", "final_decision", "summary"]
                    ]
                ]
            ]
        ]);

        return json_decode($response->choices[0]->message->content, true);
    }

    private function calculateScore(array $a): float
    {
        return
            $a['education_match']   * 0.4 +
            $a['experience_match']  * 0.3 +
            $a['soft_skills_match'] * 0.1;
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

    private function docxToText($filePath)
    {
        $phpWord = IOFactory::load($filePath);
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . "\n";
                }
            }
        }

        return $text;
    }

    private function cleanText($text)
    {
        // удаляем управляющие символы кроме табуляции и перевода строки
        // $text = preg_replace('/[\x00-\x1F\x80-\x9F]/u', '', $text);

        // конвертируем в UTF-8
        if (!mb_detect_encoding($text, 'UTF-8', true)) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }

        return trim($text);
    }

    private function aiExtractPdfText(string $rawPdf): ?string
    {
        try {
            $base64 = base64_encode($rawPdf);

            $dataUrl = "data:application/pdf;base64," . $base64;

            $response = OpenAI::responses()->create([
                "model" => "gpt-4o-mini",
                "input" => [
                    [
                        "role" => "user",
                        "content" => [
                            [
                                "type" => "input_text",
                                "text" => "Извлеки весь текст из PDF и верни только текст."
                            ],
                            [
                                "type" => "input_file",
                                "filename" => "document.pdf",
                                "file_data" => $dataUrl
                            ]
                        ]
                    ]
                ]
            ]);

            // return $response->output[0]->content[0]->text ?? null;
            return $response->outputText ?? null;
        } catch (\Exception $e) {
            Log::error("AI PDF OCR ERROR", [$e->getMessage()]);
            return null;
        }
    }
}
