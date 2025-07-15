<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApplicationStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            ['code' => 'pending', 'name' => 'На рассмотрении'],
            ['code' => 'resume_rejected', 'name' => 'Резюме отклонено'],
            ['code' => 'resume_accepted', 'name' => 'Резюме принято'],
            ['code' => 'docs_uploaded', 'name' => 'Документы загружены'],
            ['code' => 'docs_rejected', 'name' => 'Документы отклонены'],
            ['code' => 'docs_accepted', 'name' => 'Документы приняты'],
            ['code' => 'completed', 'name' => 'Принят на вакансию'],
        ];

        DB::table('application_statuses')->insert($statuses);
    }
}
