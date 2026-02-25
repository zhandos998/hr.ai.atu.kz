<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>АТУ HR Chat</title>
    <link rel="icon" href="{{ asset('favicon.png') }}">
    {{-- опционально, для SVG/PNG и iOS --}}
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.png') }}">
    @vite('resources/js/app.js')
    @vite('resources/css/app.css')
</head>

<body>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div id="app"></div>
{{--    <script src="{{ mix('/js/app.js') }}"></script>--}}
</body>

</html>
