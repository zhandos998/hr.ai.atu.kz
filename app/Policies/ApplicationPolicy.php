<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\CommissionMember;
use App\Models\PpsFacultyCommissionMember;
use App\Models\User;

class ApplicationPolicy
{
    public function view(User $user, Application $application): bool
    {
        if ($user->isAdmin() || $user->id === $application->user_id) {
            return true;
        }

        if ($user->isLawyer()) {
            return true;
        }

        $type = $application->vacancy?->type;
        if ($type === 'pps' && in_array($user->role, ['science_director', 'digital_director', 'strategy_director', 'academic_director', 'library_director', 'professional_development_director'], true)) {
            return true;
        }

        if ($application->compliance_status !== 'clear') {
            return false;
        }

        return $application->vacancy?->commissionMembers()->where('users.id', $user->id)->exists()
            || CommissionMember::where('user_id', $user->id)->forVacancyType($type)->exists()
            || ($type === 'pps' && PpsFacultyCommissionMember::where('user_id', $user->id)
                ->where('faculty_name', $application->ppsProfile?->faculty_name)->exists());
    }
}
