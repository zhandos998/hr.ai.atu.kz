@php
    $code = 419;
    $title = 'Сессия истекла';
    $description = 'Обновите страницу и повторите действие. Если вы были авторизованы, может потребоваться войти снова.';
@endphp

@include('errors.layout', compact('code', 'title', 'description'))
