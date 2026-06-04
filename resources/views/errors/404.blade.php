@php
    $code = 404;
    $title = 'Страница не найдена';
    $description = 'Страница была удалена, перемещена или ссылка указана неверно.';
@endphp

@include('errors.layout', compact('code', 'title', 'description'))
