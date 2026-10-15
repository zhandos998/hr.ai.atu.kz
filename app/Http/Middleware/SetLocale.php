<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = strtolower((string) $request->header('X-Locale', ''));

        if (! in_array($locale, ['ru', 'kk'], true)) {
            $locale = strtolower(substr((string) $request->getPreferredLanguage(['ru', 'kk']), 0, 2));
        }

        App::setLocale(in_array($locale, ['ru', 'kk'], true) ? $locale : 'ru');

        return $next($request);
    }
}
