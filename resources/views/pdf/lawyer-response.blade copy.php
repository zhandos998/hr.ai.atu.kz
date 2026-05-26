<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 25px;
            margin: 50px 25px;
            color: #000;
        }

        .header {
            width: 100%;
            margin-bottom: 100px;
        }

        .body {
            width: 100%;
            margin-bottom: 100px;
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
            font-size: 20px;
            line-height: 1.4;
            padding-left: 50px;
        }

        .left {
            width: 40%;
            font-size: 20px;
            line-height: 1.4;
            padding-right: 50px;
        }

        .center {
            width: 20%;
            text-align: center;
        }

        .title {
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            margin: 6px;
        }

        .logo img {
            height: 75px;
        }

        .logo-text {
            text-transform: uppercase;
            font-weight: bold;
            font-size: 25px;
            letter-spacing: 1px;
        }

        h2 {
            margin: 25px 0 10px 0;
            font-size: 30px;
            text-align: center;
        }

        .subtitle {
            text-align: center;
            font-size: 30px;
            margin-bottom: 20px;
        }

        .text {
            text-align: justify;
            font-size: 30px;
            margin-bottom: 15px;
        }

        .text p {
            margin: 0 0 20px 0;
            /* расстояние между абзацами */
            text-indent: 40px;
            /* 🔥 красная строка */
        }

        .sign-table {
            width: 100%;
            margin-top: 30px;
            font-size: 30px;
            border-collapse: collapse;
            border: 2px solid #000;
        }

        .sign-table td {
            padding: 15px;
            border: 2px solid #000;
        }

        .footer {
            margin-top: 30px;
            font-size: 30px;
        }
    </style>
</head>

<body>

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
                    № <span>{{ $document->id }}</span> от
                    <span>{{ $document->created_at }}</span>
                </td>

                <td class="center">

                    <div class="logo">
                        <img src="/logo-2.png">
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
                    № <span>{{ $document->id }}</span> от
                    <span>{{ $document->created_at }}</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- ТЕЛО -->
    <div class="body">
        <h2>ЗАКЛЮЧЕНИЕ</h2>

        <div class="subtitle">
            управления правового обеспечения и противодействия коррупции(УПОиПК)
        </div>

        <div class="text">
            <p>
                <span style="text-decoration: underline;">{{ $document->full_name }}</span>
                претендуемый на должность {{$application->vacancy}}
                в подразделении <span style="text-decoration: underline;">"{{ $document->department }}"</span>
                в должности <span style="text-decoration: underline;">"{{ $document->position }}"</span>
                @if ($document->is_staff === null)
                —
                @elseif($document->is_staff)
                (штатный)
                @else
                (совместитель)
                @endif с
                {{ $document->work_start_date ? \Carbon\Carbon::parse($document->work_start_date)->format('d.m.Y') : '—' }}
                по настоящее время.
            </p>

            <p>
                Справка выдана для предъявления по месту требования в <span
                    style="text-transform: lowercase;">{{ $document->destination ?? '—' }}</span>.
            </p>
            <p>
                Справка действительна на 10 дней.
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
                <td>Департамент развития человеческих ресурсов</td>
                <td>Директор / Медеубаева Жанар Мирасовна</td>
                <td>Подписан</td>
                <td>{{ $document->updated_at }}</td>
            </tr>
        </table>
    </div>

    <!-- НИЗ + QR -->
    <div class="footer">
        @php
        $qr = base64_encode(
        QrCode::format('png')
        ->size(300)
        ->margin(1)
        ->generate(route('application.documents.show', $document->id)),
        );
        @endphp

        <div style="display:flex; gap:20px; margin-top:20px;">
            <img src="data:image/png;base64,{{ $qr }}" width="300" height="300">

            <div>
                <i>
                    {{-- Данный документ сформирован в системе электронного --}}
                    {{-- документооборота Алматинского технологического университета --}}
                    {{-- и подписан электронной подписью.<br><br> --}}

                    Оригинал документа хранится на сервере университета.
                    Для проверки подлинности отсканируйте QR-код или пройдите по ссылке <a
                        href="https://anketa.atu.kz/application/documents/{{ $document->id }}">https://anketa.atu.kz/application/documents/{{ $document->id }}</a>
                </i><br><br>

                <b>ID документа:</b> {{ $document->id }}<br>
                <b>Дата формирования:</b> {{ $document->created_at }}
            </div>
        </div>
    </div>

</body>

</html>