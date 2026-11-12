<?php

namespace Database\Seeders;

use App\Models\UvpPosition;
use Illuminate\Database\Seeder;

class UvpPositionSeeder extends Seeder
{
    public function run(): void
    {
        $positionNames = [
            'Заведующий лабораторией',
            'Мастер производственного обучения',
            'Старший лаборант',
            'Лаборант',
            'Механик',
            'Инженер',
            'Инженер ВКК',
        ];

        foreach ($positionNames as $name) {
            UvpPosition::query()->firstOrCreate(['name' => $name]);
        }
    }
}
