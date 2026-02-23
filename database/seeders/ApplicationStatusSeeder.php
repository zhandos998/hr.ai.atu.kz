<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApplicationStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['code' => 'pending', 'name' => 'На рассмотрении'],
            ['code' => 'resume_rejected', 'name' => 'Резюме отклонено'],
            ['code' => 'resume_accepted', 'name' => 'Резюме принято'],
            ['code' => 'docs_uploaded', 'name' => 'Документы загружены'],
            ['code' => 'docs_rejected', 'name' => 'Документы отклонены'],
            ['code' => 'docs_accepted', 'name' => 'Документы приняты'],
            ['code' => 'corruption_not_found', 'name' => 'Коррупция: не выявлена'],
            ['code' => 'corruption_found', 'name' => 'Коррупция: выявлена'],
            ['code' => 'completed', 'name' => 'Принят на вакансию'],
            ['code' => 'not_accepted', 'name' => 'Не принят на вакансию'],
        ];

        foreach ($statuses as $status) {
            DB::table('application_statuses')->updateOrInsert(
                ['code' => $status['code']],
                ['name' => $status['name']]
            );
        }
    }
}
