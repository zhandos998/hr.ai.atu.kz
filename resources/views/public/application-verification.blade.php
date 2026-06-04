<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Проверка заявки №{{ $application->id }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <style>
        :root {
            color-scheme: light;
            --brand: #005eb8;
            --ink: #111827;
            --muted: #6b7280;
            --line: #dbe3ef;
            --panel: #ffffff;
            --page: #f3f6fb;
            --soft: #eef6ff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--page);
            color: var(--ink);
            font-family: Inter, Arial, sans-serif;
            line-height: 1.5;
        }

        .page {
            width: min(960px, calc(100% - 32px));
            margin: 32px auto;
        }

        .topbar {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 18px;
        }

        .logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
        }

        .university {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
        }

        h1 {
            margin: 4px 0 0;
            font-size: 26px;
            line-height: 1.2;
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1px;
            background: var(--line);
            border-bottom: 1px solid var(--line);
        }

        .summary-item {
            background: var(--soft);
            padding: 16px;
        }

        .label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .value {
            margin-top: 6px;
            font-size: 16px;
            font-weight: 700;
            word-break: break-word;
        }

        .content {
            padding: 22px;
        }

        .section-title {
            margin: 0 0 14px;
            color: var(--brand);
            font-size: 18px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        .field {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 14px;
            background: #fff;
        }

        .field-value {
            margin-top: 6px;
            white-space: pre-line;
            word-break: break-word;
        }

        .document {
            margin-top: 18px;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 16px;
            background: #fbfdff;
        }

        .document-text {
            margin-top: 10px;
            white-space: pre-line;
            word-break: break-word;
        }

        .notice {
            margin-top: 18px;
            color: var(--muted);
            font-size: 13px;
        }

        @media (max-width: 760px) {
            .page {
                width: min(100% - 20px, 960px);
                margin: 18px auto;
            }

            .topbar {
                align-items: flex-start;
            }

            .logo {
                width: 56px;
                height: 56px;
            }

            h1 {
                font-size: 21px;
            }

            .summary,
            .grid {
                grid-template-columns: 1fr;
            }

            .content {
                padding: 16px;
            }
        }
    </style>
</head>

<body>
    @php
        $profile = $application->ppsProfile;
        $vacancy = $application->vacancy;
        $typeLabel = $vacancy?->type === 'staff' ? 'АУП' : 'ППС';
    @endphp

    <main class="page">
        <div class="topbar">
            <img class="logo" src="{{ asset('logo-2.png') }}" alt="АТУ">
            <div>
                <p class="university">Алматинский технологический университет</p>
                <h1>Проверка заявки</h1>
            </div>
        </div>

        <section class="panel">
            <div class="summary">
                <div class="summary-item">
                    <div class="label">ID заявки</div>
                    <div class="value">№{{ $application->id }}</div>
                </div>
                <div class="summary-item">
                    <div class="label">Тип</div>
                    <div class="value">{{ $typeLabel }}</div>
                </div>
                <div class="summary-item">
                    <div class="label">Дата подачи</div>
                    <div class="value">{{ optional($application->created_at)->format('d.m.Y H:i') ?: '—' }}</div>
                </div>
            </div>

            <div class="content">
                @foreach ($applicationSections as $section)
                    <div class="document">
                        <h2 class="section-title">{{ $section['title'] }}</h2>
                        <div class="grid">
                            @foreach ($section['fields'] as $field)
                                <div class="field">
                                    <div class="label">{{ $field['label'] }}</div>
                                    <div class="field-value">{{ filled($field['value'] ?? null) ? $field['value'] : '—' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @foreach ($profileSections as $section)
                    <div class="document">
                        <h2 class="section-title">{{ $section['title'] }}</h2>
                        <div class="grid">
                            @foreach ($section['fields'] as $field)
                                <div class="field">
                                    <div class="label">{{ $field['label'] }}</div>
                                    <div class="field-value">{{ filled($field['value'] ?? null) ? $field['value'] : '—' }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <p class="notice">
                    Эта страница доступна только по подписанной ссылке из QR-кода документа.
                </p>
            </div>
        </section>
    </main>
</body>

</html>
