<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsLibraryDirector
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isLibraryDirector()) {
            return response()->json([
                'message' => 'Доступ разрешён только директору Научной библиотеки.',
            ], 403);
        }

        return $next($request);
    }
}
