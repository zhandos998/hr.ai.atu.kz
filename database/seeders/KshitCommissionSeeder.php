<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\ApplicationPpsProfile;
use App\Models\CommissionMember;
use App\Models\PpsFacultyCommissionMember;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class KshitCommissionSeeder extends Seeder
{
    private const FACULTY_NAME = 'Другое';
    private const DEPARTMENT_NAME = 'КШИТ';
    private const MEMBER_NAME = 'Пазылхайыр Бауыржан';
    private const MEMBER_EMAIL = 'b.pazylkhaiyr@atu.edu.kz';
    private const MEMBER_PASSWORD = '8111';

    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => self::MEMBER_EMAIL],
            [
                'name' => self::MEMBER_NAME,
                'phone' => null,
                'email_verified_at' => now(),
                'password' => Hash::make(self::MEMBER_PASSWORD),
                'role' => 'user',
            ],
        );

        PpsFacultyCommissionMember::query()->updateOrCreate([
            'faculty_name' => self::FACULTY_NAME,
            'user_id' => $user->id,
        ]);

        $vacancyIds = ApplicationPpsProfile::query()
            ->where('department_name', self::DEPARTMENT_NAME)
            ->whereHas('application.vacancy', function ($query) {
                $query->where('type', CommissionMember::TYPE_PPS);
            })
            ->pluck('application_id')
            ->pipe(function ($applicationIds) {
                return Application::query()
                    ->whereIn('id', $applicationIds)
                    ->pluck('vacancy_id');
            })
            ->filter()
            ->unique()
            ->values();

        Vacancy::query()
            ->withTrashed()
            ->whereIn('id', $vacancyIds)
            ->get()
            ->each(function (Vacancy $vacancy) use ($user) {
                $vacancy->commissionMembers()->syncWithoutDetaching([$user->id]);
            });
    }
}
