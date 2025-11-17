<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use App\Models\Vacancy;
use App\Models\Application;
use App\Models\ApplicationStatus;

class ChatController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        $user = $request->user();

        $system = <<<SYS
            Ты — AI HR-ассистент отдела кадров АТУ.
            Правила:
            - Если пользователь пишет на казахском (даже если без специальных букв, например "Саламатсыз ба"), отвечай на казахском языке.
            - Отвечай кратко (1–3 предложения) и на том же языке, на котором спросили.
            - Про университет говори только хорошее, нейтрально‑позитивным тоном.
            - Если спрашивают про вакансии: зови инструмент get_vacancies (фильтр по тексту и/или типу) и дай короткий список + ссылку на страницу вакансий как кнопку.
            - Если спрашивают про конкретную вакансию: зови get_vacancy_details (id или название) и дай краткое описание + ссылку для отклика как кнопку.
            - Если авторизованный пользователь спрашивает о статусе своей заявки: зови get_my_application_status (по умолчанию самая свежая) и сообщи статус кратко. Если есть загруженные документы — упомяни их кратко.
            - Если инструмент вернул пусто — вежливо скажи, что пока нет данных, и предложи заглянуть позже.
            - Не выдумывай данные — используй только то, что вернули инструменты.
            - Отвечай строго в JSON-формате:
            {
            "text": "Краткий текст ответа",
            "buttons": [
                { "label": "Текст кнопки", "url": "https://..." }
            ]
            }
            Если кнопок нет, передавай "buttons": [].
            Не используй markdown, html и лишние поля.
            SYS;

        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_vacancies',
                    'description' => 'Список вакансий из БД',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Поиск по названию/описанию'],
                            'type'  => ['type' => 'string', 'description' => 'Фильтр по типу вакансии: staff или pps'],
                            'limit' => ['type' => 'integer', 'description' => 'Ограничение, по умолчанию 5'],
                        ],
                        'required' => []
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_vacancy_details',
                    'description' => 'Детали конкретной вакансии',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'id'    => ['type' => 'integer', 'description' => 'ID вакансии'],
                            'title' => ['type' => 'string',  'description' => 'Название, если не знаем id'],
                        ],
                        'required' => []
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_my_application_status',
                    'description' => 'Статус последней (или по vacancy_id) заявки авторизованного пользователя',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'vacancy_id' => ['type' => 'integer', 'description' => 'Опционально — статус по конкретной вакансии'],
                        ],
                        'required' => []
                    ]
                ]
            ],
        ];

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => (string)$request->string('message')],
        ];

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'temperature' => 0.2,
        ]);

        $choice = $response->choices[0];
        $messages[] = $choice->message->toArray();

        if (!empty($choice->message->toolCalls)) {
            foreach ($choice->message->toolCalls as $toolCall) {
                $toolName = $toolCall->function->name;
                $args = $toolCall->function->arguments ? json_decode($toolCall->function->arguments, true) : [];

                $toolResult = match ($toolName) {
                    'get_vacancies' => $this->toolGetVacancies($args),
                    'get_vacancy_details' => $this->toolGetVacancyDetails($user, $args),
                    'get_my_application_status' => $this->toolGetMyApplicationStatus($user, $args),
                    default => ['error' => 'Unknown tool']
                };

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall->id,
                    'name' => $toolName,
                    'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE),
                ];
            }

            $final = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'temperature' => 0.2,
            ]);

            $raw = $final->choices[0]->message->content;
            return response()->json($this->normalizeAssistantPayload($raw));
        }
        $raw = $choice->message->content;
        return response()->json($this->normalizeAssistantPayload($raw));
    }

    // ========= TOOLS =========

    private function toolGetVacancies(array $args): array
    {
        $limit = (isset($args['limit']) && is_int($args['limit'])) ? max(1, min(10, $args['limit'])) : 5;
        $query = trim($args['query'] ?? '');
        $type  = isset($args['type']) ? trim($args['type']) : null; // 'staff' | 'pps'

        $q = Vacancy::query();

        if ($query !== '') {
            $q->where(function ($qq) use ($query) {
                $qq->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            });
        }

        if ($type) {
            $q->where('type', $type);
        }

        $vacancies = $q->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'title', 'type']);

        return [
            'items' => $vacancies->map(function ($v) {
                return [
                    'id'    => $v->id,
                    'title' => $v->title,
                    'type'  => $v->type, // staff | pps
                    'url'       => '/apply?vacancy_id=' . $v->id,
                    'apply_url' => '/profile', // POST с vacancy_id
                    'apply_method' => 'POST',
                    'apply_payload_example' => ['vacancy_id' => $v->id],
                    'list_url' => url('/vacancies'),
                ];
            })->values()->all(),
            'list_url' => url('/vacancies'),
        ];
    }

    private function toolGetVacancyDetails($user, array $args): array
    {
        if (!$user) {
            return [
                'text' => 'Для проверки статуса вашей заявки необходимо авторизоваться.',
                'buttons' => [
                    ['label' => 'Войти', 'url' => url('/login')],
                    ['label' => 'Регистрация', 'url' => url('/register')],
                ]
            ];
        }

        $id    = $args['id'] ?? null;
        $title = $args['title'] ?? null;

        $q = Vacancy::query();

        if ($id) {
            $q->where('id', $id);
        } elseif ($title) {
            $q->where('title', 'like', "%{$title}%");
        } else {
            return ['items' => [], 'error' => 'no identifier'];
        }

        $v = $q->first();
        if (!$v) return ['items' => []];

        return [
            'items' => [[
                'id' => $v->id,
                'title' => $v->title,
                'type' => $v->type, // staff | pps
                'description' => Str::limit(strip_tags($v->description ?? ''), 500),
                'url'       => '/apply?vacancy_id=' . $v->id,
                'apply_url' => '/profile',
                'apply_method' => 'POST',
                'apply_payload_example' => ['vacancy_id' => $v->id],
            ]]
        ];
    }

    private function toolGetMyApplicationStatus($user, array $args): array
    {
        if (!$user) {
            return [
                'text' => 'Для проверки статуса вашей заявки необходимо авторизоваться.',
                'buttons' => [
                    ['label' => 'Войти', 'url' => url('/login')],
                    ['label' => 'Регистрация', 'url' => url('/register')],
                ]
            ];
        }

        $vacancyId = $args['vacancy_id'] ?? null;

        $q = Application::query()
            ->where('user_id', $user->id);

        if ($vacancyId) {
            $q->where('vacancy_id', $vacancyId);
        }

        // ожидаем связи: status() -> belongsTo(ApplicationStatus::class, 'status_id')
        //                vacancy() -> belongsTo(Vacancy::class)
        //                documents() -> hasMany(ApplicationDocument::class)
        $app = $q->orderByDesc('created_at')
            ->with([
                'status:id,code,name',
                'vacancy:id,title',
                'documents:id,application_id,type,file_path'
            ])->first();

        if (!$app) return ['items' => []];

        // сделаем удобные ссылки на документы (если используешь storage:public)
        $docs = $app->documents->map(function ($d) {
            return [
                'type' => $d->type,
                'file' => $d->file_path,
                'url'  => Storage::disk('public')->exists($d->file_path)
                    ? Storage::url($d->file_path)
                    : null,
            ];
        })->all();

        return [
            'items' => [[
                'application_id' => $app->id,
                'vacancy' => $app->vacancy?->title,
                'status_code' => $app->status?->code, // pending, resume_accepted, ...
                'status_name' => $app->status?->name, // «На рассмотрении», ...
                'documents' => $docs, // ['id_card','diploma',...]
                'updated_at' => optional($app->updated_at)->toDateTimeString(),
                'details_url' => '/profile' ?? null,

            ]]
        ];
    }

    private function normalizeAssistantPayload(?string $raw): array
    {
        $raw = $raw ?? '';
        $data = json_decode($raw, true);

        // если модель не прислала валидный JSON — вернем как простой текст
        if (!is_array($data)) {
            return ['text' => $raw, 'buttons' => []];
        }

        // нормализуем к нужной схеме
        return [
            'text' => isset($data['text']) ? (string)$data['text'] : $raw,
            'buttons' => isset($data['buttons']) && is_array($data['buttons']) ? $data['buttons'] : [],
        ];
    }
}
