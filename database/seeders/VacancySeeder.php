<?php

namespace Database\Seeders;

use App\Models\Vacancy;
use Illuminate\Database\Seeder;

class VacancySeeder extends Seeder
{
    public function run(): void
    {
        Vacancy::create([
            'title' => 'Программист Laravel',
            'description' => 'Разработка и поддержка HR системы АТУ.',
            'type' => 'staff',
        ]);

        Vacancy::create([
            'title' => 'Преподаватель математики',
            'description' => 'Чтение лекций и проведение практических занятий.',
            'type' => 'pps',
        ]);

        Vacancy::create([
            'title' => 'Системный администратор',
            'description' => 'Обслуживание серверов и оборудования университета.',
            'type' => 'staff',
        ]);
    }
}
