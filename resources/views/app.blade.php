<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.png') }}">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/inertia.jsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
