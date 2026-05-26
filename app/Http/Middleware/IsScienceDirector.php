<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsScienceDirector
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isScienceDirector()) {
            return response()->json([
                'message' => 'Доступ разрешён только директору Департамента науки.',
            ], 403);
        }

        return $next($request);
    }
}
