<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">

    <style type="text/css">
        @page {
            margin: 28px 34px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 0;
            color: #000;
        }

        .header {
            width: 100%;
            margin-bottom: 34px;
        }

        .body {
            width: 100%;
            margin-bottom: 28px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
        }

        .right {
            width: 40%;
            font-size: 11px;
            line-height: 1.35;
            padding-left: 18px;
        }

        .left {
            width: 40%;
            font-size: 11px;
            line-height: 1.35;
            padding-right: 18px;
        }

        .center {
            width: 20%;
            text-align: center;
        }

        .title {
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            margin: 0 0 8px;
        }

        .logo img {
            height: 58px;
        }

        .logo-text {
            text-transform: uppercase;
            font-weight: bold;
            font-size: 13px;
            line-height: 1.2;
            letter-spacing: 0;
        }

        h2 {
            margin: 0 0 8px;
            font-size: 18px;
            text-align: center;
        }

        .subtitle {
            text-align: center;
            font-size: 15px;
            line-height: 1.35;
            margin-bottom: 22px;
        }

        .text {
            text-align: justify;
            font-size: 14px;
            line-height: 1.45;
            margin-bottom: 12px;
        }

        .text p {
            margin: 0 0 12px 0;
            text-indent: 24px;
        }

        .sign-table {
            width: 100%;
            margin-top: 18px;
            font-size: 11px;
            border-collapse: collapse;
            border: 1px solid #000;
        }

        .sign-table td {
            padding: 7px;
            border: 1px solid #000;
            vertical-align: top;
        }

        .footer {
            margin-top: 18px;
            font-size: 11px;
            line-height: 1.35;
        }

        .footer-table {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }

        .footer-table td {
            vertical-align: top;
        }

        .qr-cell {
            width: 120px;
            padding-right: 14px;
        }
    </style>
</head>

<body>
    @php
        $logoPath = public_path('logo-2.png');
        $logoSrc = is_file($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
        $candidateName = $application->user?->name ?: '—';
        $vacancy = $application->vacancy;
        $vacancyTitle = $vacancy?->title ?: '—';
        $isPps = $vacancy?->type === 'pps';
        $departmentName = $isPps
            ? ($application->ppsProfile?->department_name ?: $application->ppsProfile?->faculty_name)
            : ($vacancy?->position?->department?->name ?: '—');
        $positionName = $isPps
            ? ($application->ppsProfile?->desired_position ?: $vacancyTitle)
            : ($vacancy?->position?->name ?: $vacancyTitle);
        $complianceLog = $application->stageLogs
            ->first(fn ($log) => $log->stage === 'compliance' && in_array($log->new_status, ['clear', 'flagged'], true));
        $reviewedAt = $complianceLog?->created_at ?: $application->updated_at;
        $reviewerName = $complianceLog?->author?->name ?: '—';
        $generatedAtFormatted = \Carbon\Carbon::parse($generatedAt)->format('d.m.Y H:i');
        $reviewedAtFormatted = $reviewedAt ? \Carbon\Carbon::parse($reviewedAt)->format('d.m.Y H:i') : '—';
        $verificationUrl = url('/admin/applications/' . $application->id) . '?type=' . ($vacancy?->type ?: 'pps');
    @endphp

    <!-- ШАПКА -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="left">
                    <div class="title" style="">АҚ «Алматы Технологиялық Университеті»</div>
                    Қазақстан Республикасы<br>
                    г. Алматы, ул. Толе би, 100<br>
                    Тел.: +7 (727) 293-52-98, ішкі н.: 111, 129<br>
                    E-mail: oup@atu.kz<br><br>
                    № <span>{{ $application->id }}</span> от
                    <span>{{ $generatedAtFormatted }}</span>
                </td>

                <td class="center">

                    <div class="logo">
                        @if ($logoSrc)
                            <img src="{{ $logoSrc }}">
                        @endif
                    </div>
                    <div class="logo-text">
                        Алматы<br>
                        Технологиялық<br>
                        Университеті
                    </div>
                </td>

                <td class="right">
                    <div class="title">АО «Алматинский Технологический Университет»</div>
                    Республика Казахстан<br>
                    г. Алматы, ул. Толе би, 100<br>
                    Тел.: +7 (727) 293-52-98, вн. н.: 111, 129<br>
                    E-mail: oup@atu.kz<br><br>
                    № <span>{{ $application->id }}</span> от
                    <span>{{ $generatedAtFormatted }}</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- ТЕЛО -->
    <div class="body">
        <h2>ЗАКЛЮЧЕНИЕ</h2>

        <div class="subtitle">
            Департамент правового обеспечения и комплаенса
        </div>

        <div class="text">
            <p>
                Кандидат <span style="text-decoration: underline;">{{ $candidateName }}</span>,
                претендующий на вакансию <span style="text-decoration: underline;">"{{ $vacancyTitle }}"</span>,
                рассмотрен Департаментом правового обеспечения и комплаенса.
            </p>

            <p>
                Подразделение: <span style="text-decoration: underline;">"{{ $departmentName ?: '—' }}"</span>.
                Должность: <span style="text-decoration: underline;">"{{ $positionName ?: '—' }}"</span>.
            </p>

            <p>
                По результатам юридической проверки статус заявки:
                <span style="text-decoration: underline;">{{ $compliance }}</span>.
                {{ $lawyerDecision }}
            </p>
        </div>

        <!-- ПОДПИСЬ -->
        <table class="sign-table">
            <tr>
                <td>Подразделение</td>
                <td>Должность / ФИО</td>
                <td>Статус</td>
                <td>Дата и время</td>
            </tr>
            <tr>
                <td>Департамент правового обеспечения и комплаенса</td>
                <td>Ответственный сотрудник / {{ $reviewerName }}</td>
                <td>{{ $compliance }}</td>
                <td>{{ $reviewedAtFormatted }}</td>
            </tr>
        </table>
    </div>

    <!-- НИЗ + QR -->
    <div class="footer">
        @php
            $qr = base64_encode(
                QrCode::format('svg')
                    ->size(300)
                    ->margin(1)
                    ->generate($verificationUrl),
            );
        @endphp

        <table class="footer-table">
            <tr>
                <td class="qr-cell">
                    <img src="data:image/svg+xml;base64,{{ $qr }}" width="110" height="110">
                </td>
                <td>
                <i>
                    {{--                Данный документ сформирован в системе электронного --}}
                    {{--                документооборота Алматинского технологического университета --}}
                    {{--                и подписан электронной подписью.<br><br> --}}

                    Оригинал документа хранится на сервере университета.
                    Для проверки подлинности отсканируйте QR-код или пройдите по ссылке <a
                        href="{{ $verificationUrl }}">{{ $verificationUrl }}</a>
                </i><br><br>

                <b>ID документа:</b> {{ $application->id }}<br>
                <b>Дата формирования:</b> {{ $generatedAtFormatted }}
                </td>
            </tr>
        </table>
    </div>

</body>

</html>
