<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate([
            'email' => 'admin@atu.kz',
        ], [
            'name' => 'Admin ATU',
            'phone' => null,
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        User::updateOrCreate([
            'email' => 'd.dzhurinskaya@atu.edu.kz',
        ], [
            'name' => 'Джуринская Индира',
            'phone' => null,
            'password' => Hash::make('password'),
            'role' => 'science_director',
        ]);

        User::updateOrCreate([
            'email' => 'a.kalabina@atu.edu.kz',
        ], [
            'name' => 'Калабина Анастасия',
            'phone' => null,
            'password' => Hash::make('password'),
            'role' => 'digital_director',
        ]);

        User::updateOrCreate([
            'email' => 'vasilina.g@atu.edu.kz',
        ], [
            'name' => 'Василина Гулзира',
            'phone' => null,
            'password' => Hash::make('password'),
            'role' => 'strategy_director',
        ]);

        User::updateOrCreate([
            'email' => 'n.ahmetova@atu.edu.kz',
        ], [
            'name' => 'Ахметова Нурсулу',
            'phone' => null,
            'password' => Hash::make('password'),
            'role' => 'academic_director',
        ]);

        User::updateOrCreate([
            'email' => 'b.tolekova@atu.edu.kz',
        ], [
            'name' => 'Толекова Бахыт',
            'phone' => null,
            'password' => Hash::make('password'),
            'role' => 'library_director',
        ]);

        User::updateOrCreate([
            'email' => 'b.esembaeva@atu.edu.kz',
        ], [
            'name' => 'Балзада Есембаева',
            'phone' => null,
            'password' => Hash::make('password'),
            'role' => 'lawyer',
        ]);

        // User::updateOrCreate([
        //     'email' => 'zhandos998@gmail.com',
        // ], [
        //     'name' => 'Zhappas Zhandos Adiluly',
        //     'phone' => '+77473186847',
        //     'password' => Hash::make('123456789'),
        //     'role' => 'user',
        // ]);
    }
}
