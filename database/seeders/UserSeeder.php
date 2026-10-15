<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate([
            'email' => 'admin@atu.kz',
        ], [
            'name' => 'Admin ATU',
            'phone' => null,
            'email_verified_at' => now(),
            'password' => Hash::make('1234'),
            'role' => 'admin',
        ]);

        User::firstOrCreate([
            'email' => 'd.dzhurinskaya@atu.edu.kz',
        ], [
            'name' => 'Джуринская Индира',
            'phone' => null,
            'email_verified_at' => now(),
            'password' => Hash::make('9791'),
            'role' => 'science_director',
        ]);

        User::firstOrCreate([
            'email' => 'a.kalabina@atu.edu.kz',
        ], [
            'name' => 'Калабина Анастасия',
            'phone' => null,
            'email_verified_at' => now(),
            'password' => Hash::make('9564'),
            'role' => 'digital_director',
        ]);

        User::firstOrCreate([
            'email' => 'vasilina.g@atu.edu.kz',
        ], [
            'name' => 'Василина Гулзира',
            'phone' => null,
            'email_verified_at' => now(),
            'password' => Hash::make('4567'),
            'role' => 'strategy_director',
        ]);

        User::firstOrCreate([
            'email' => 'n.ahmetova@atu.edu.kz',
        ], [
            'name' => 'Ахметова Нурсулу',
            'phone' => null,
            'email_verified_at' => now(),
            'password' => Hash::make('4360'),
            'role' => 'academic_director',
        ]);

        User::firstOrCreate([
            'email' => 'b.tolekova@atu.edu.kz',
        ], [
            'name' => 'Толекова Бахыт',
            'phone' => null,
            'email_verified_at' => now(),
            'password' => Hash::make('5677'),
            'role' => 'library_director',
        ]);

        User::firstOrCreate([
            'email' => 'm.serikkyzy@atu.edu.kz',
        ], [
            'name' => 'Серикқызы Мира',
            'phone' => null,
            'email_verified_at' => now(),
            'password' => Hash::make('2001'),
            'role' => 'professional_development_director',
        ]);

        User::firstOrCreate([
            'email' => 'b.esembaeva@atu.edu.kz',
        ], [
            'name' => 'Есембаева Балзада',
            'phone' => null,
            'email_verified_at' => now(),
            'password' => Hash::make('8363'),
            'role' => 'lawyer',
        ]);

        User::firstOrCreate([
            'email' => 'zhandos998@gmail.com',
        ], [
            'name' => 'Zhappas Zhandos Adiluly',
            'phone' => '+77473186847',
            'password' => Hash::make('123456789'),
            'role' => 'user',
        ]);
    }
}
