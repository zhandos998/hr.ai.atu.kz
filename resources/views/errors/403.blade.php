@php
    $code = 403;
    $exceptionMessage = trim((string) ($exception?->getMessage() ?? ''));
    $isInvalidSignature = $exceptionMessage === 'Invalid signature.';
    $title = $isInvalidSignature ? 'Ссылка недействительна' : 'Доступ запрещён';
    $description = $isInvalidSignature
        ? 'Срок действия ссылки истёк или подпись не совпадает. Откройте документ по новой QR-ссылке или обратитесь к администратору.'
        : 'У вас нет прав для просмотра этой страницы.';
@endphp

@include('errors.layout', compact('code', 'title', 'description'))
