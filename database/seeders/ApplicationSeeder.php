<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Seeder;

class ApplicationSeeder extends Seeder
{
    public function run(): void
    {
        // $users = User::where('role', 'user')->get();

        // foreach ($users as $user) {
        //     Application::create([
        //         'user_id' => $user->id,
        //         'vacancy_title' => 'Программист Laravel',
        //         'status' => 'pending',
        //     ]);

        //     Application::create([
        //         'user_id' => $user->id,
        //         'vacancy_title' => 'Преподаватель математики',
        //         'status' => 'accepted',
        //     ]);
        // }
    }
}
