<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsComplianceDirector
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isComplianceDirector()) {
            return response()->json([
                'message' => 'Доступ разрешён только роли lawyer.',
            ], 403);
        }

        return $next($request);
    }
}
