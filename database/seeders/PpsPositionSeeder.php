<?php

namespace Database\Seeders;

use App\Models\Department;
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
            'Сеньор-лектор',
            'Профессор',
            'Профессор-исследователь',
            'Исполняющий обязанности (и.о.) ассоциированного профессора',
            'Исполняющий обязанности (и.о.) ассоциированного профессора – исследователя',
            'Исполняющий обязанности (и.о.) профессора',
            'Исполняющий обязанности (и.о.) профессора-исследователя',
        ];

        $departments = Department::query()
            ->orderBy('name')
            ->get()
            ->filter(fn (Department $department) => (
                trim((string) $department->description) === 'Кафедра'
                || str_starts_with(trim((string) $department->name), 'Кафедра')
            ));

        foreach ($departments as $department) {
            foreach ($positionNames as $positionName) {
                Position::query()->firstOrCreate(
                    [
                        'department_id' => $department->id,
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
