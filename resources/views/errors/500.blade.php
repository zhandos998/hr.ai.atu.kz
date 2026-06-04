@php
    $code = 500;
    $title = 'Ошибка сервера';
    $description = 'Не удалось обработать запрос. Попробуйте повторить действие позже или сообщите администратору.';
@endphp

@include('errors.layout', compact('code', 'title', 'description'))
