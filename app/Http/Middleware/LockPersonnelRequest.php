<?php

namespace App\Http\Middleware;

use App\Models\PersonnelRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class LockPersonnelRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        return DB::transaction(function () use ($request, $next) {
            PersonnelRequest::query()->whereKey($request->route('id'))->lockForUpdate()->firstOrFail();

            return $next($request);
        }, 3);
    }
}
