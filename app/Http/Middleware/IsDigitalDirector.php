<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsDigitalDirector
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isDigitalDirector()) {
            return response()->json([
                'message' => 'Доступ разрешён только директору Центра искусственного интеллекта и цифрового развития.',
            ], 403);
        }

        return $next($request);
    }
}
