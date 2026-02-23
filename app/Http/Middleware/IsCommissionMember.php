<?php

namespace App\Http\Middleware;

use App\Models\CommissionMember;
use App\Models\Vacancy;
use Closure;
use Illuminate\Http\Request;

class IsCommissionMember
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $isMember = Vacancy::query()
            ->whereHas('commissionMembers', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })
            ->exists();

        // Backward compatibility: users added through old global commission list.
        if (!$isMember) {
            $isMember = CommissionMember::query()
                ->where('user_id', $user->id)
                ->exists();
        }

        if (!$isMember) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        return $next($request);
    }
}
