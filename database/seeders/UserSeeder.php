<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin ATU',
            'email' => 'admin@atu.kz',
            'phone' => '+77713985075',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Lawyer ATU',
            'email' => 'lawyer@atu.kz',
            'phone' => '+77010000000',
            'password' => Hash::make('password'),
            'role' => 'lawyer',
        ]);

        User::create([
            'name' => 'Zhappas Zhandos Adiluly',
            'email' => 'zhandos998@gmail.com',
            'phone' => '+77473186847',
            'password' => Hash::make('123456789'),
            'role' => 'user',
        ]);

        // User::factory()->count(10)->create();
    }
}
