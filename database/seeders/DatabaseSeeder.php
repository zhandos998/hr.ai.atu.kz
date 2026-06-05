<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CommissionCandidateUserSeeder::class,
            DepartmentPositionSeeder::class,
            FacultyDepartmentSeeder::class,
            PpsPositionSeeder::class,
            VacancySeeder::class,
            ApplicationSeeder::class,
            ApplicationStatusSeeder::class,
            PpsCommissionSeeder::class,
            KshitCommissionSeeder::class,
        ]);
    }
}
