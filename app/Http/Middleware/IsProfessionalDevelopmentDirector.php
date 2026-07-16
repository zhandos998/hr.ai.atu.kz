<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsProfessionalDevelopmentDirector
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isProfessionalDevelopmentDirector()) {
            return response()->json([
                'message' => 'Доступ разрешён только ответственному за повышение квалификации.',
            ], 403);
        }

        return $next($request);
    }
}
