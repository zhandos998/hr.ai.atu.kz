<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsLawyer
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->isLawyer()) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        return $next($request);
    }
}

