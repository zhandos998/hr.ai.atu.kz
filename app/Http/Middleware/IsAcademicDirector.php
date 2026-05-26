<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsAcademicDirector
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isAcademicDirector()) {
            return response()->json([
                'message' => 'Доступ разрешён только директору Департамента академического развития.',
            ], 403);
        }

        return $next($request);
    }
}
