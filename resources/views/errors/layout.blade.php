<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $code }} | {{ $title }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            color: #111827;
            font-family: Arial, sans-serif;
        }

        .error {
            text-align: center;
        }

        .line {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
        }

        .code {
            color: #005eb8;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .divider {
            width: 1px;
            height: 28px;
            background: #9ca3af;
        }

        .title {
            color: #111827;
            font-size: 18px;
            font-weight: 400;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .home {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            margin-top: 24px;
            border-radius: 8px;
            padding: 0 16px;
            background: #005eb8;
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <main class="error">
        <div class="line">
            <span class="code">{{ $code }}</span>
            <span class="divider" aria-hidden="true"></span>
            <span class="title">{{ $title }}</span>
        </div>
        <div>
            <a class="home" href="{{ url('/') }}">На главную</a>
        </div>
    </main>
</body>

</html>
