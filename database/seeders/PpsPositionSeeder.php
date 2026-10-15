<?php

namespace Database\Seeders;

use App\Models\AcademicUnit;
use App\Models\Position;
use Illuminate\Database\Seeder;

class PpsPositionSeeder extends Seeder
{
    public function run(): void
    {
        $positionNames = [
            'Ассистент',
            'Ассистент-профессор',
            'Ассистент-профессор-исследователь',
            'Ассоциированный профессор',
            'Ассоциированный профессор – исследователь',
            'Заведующий кафедрой (для выпускающих кафедр)',
            'Заведующий кафедрой (для не выпускающих кафедр)',
            'Исполняющий обязанности (и.о.) заведующий кафедрой',
            'Сеньор-лектор',
            'Профессор',
            'Профессор-исследователь',
            'Исполняющий обязанности (и.о.) ассоциированного профессора',
            'Исполняющий обязанности (и.о.) ассоциированного профессора – исследователя',
            'Исполняющий обязанности (и.о.) профессора',
            'Исполняющий обязанности (и.о.) профессора-исследователя',
        ];

        $departments = AcademicUnit::query()->where('kind', 'chair')->orderBy('name')->get();

        foreach ($departments as $department) {
            foreach ($positionNames as $positionName) {
                Position::query()->firstOrCreate(
                    [
                        'academic_unit_id' => $department->id,
                        'name' => $positionName,
                    ],
                    [
                        'duties' => null,
                        'qualification' => null,
                    ],
                );
            }
        }
    }
}
