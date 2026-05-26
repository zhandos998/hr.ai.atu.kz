<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsStrategyDirector
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isStrategyDirector()) {
            return response()->json([
                'message' => 'Доступ разрешён только директору Департамента стратегического развития.',
            ], 403);
        }

        return $next($request);
    }
}
