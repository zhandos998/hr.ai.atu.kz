@php
    $code = 429;
    $title = 'Слишком много запросов';
    $description = 'Система временно ограничила частоту запросов. Подождите немного и попробуйте ещё раз.';
@endphp

@include('errors.layout', compact('code', 'title', 'description'))
