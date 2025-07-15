<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin АТУ',
            'email' => 'admin@atu.kz',
            'phone' => '+77713985075',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Zhappas Zhandos Adiluly',
            'email' => 'zhandos998@gmail.com',
            'phone' => '+77473186847',
            'password' => Hash::make('123456789'),
        ]);

        // Несколько пользователей
        User::factory()->count(10)->create();
    }
}
