@php
    $code = 503;
    $title = 'Сервис временно недоступен';
    $description = 'Сайт находится на обслуживании или временно перегружен. Попробуйте открыть страницу позже.';
@endphp

@include('errors.layout', compact('code', 'title', 'description'))
