<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('application_statuses')->updateOrInsert(
            ['code' => 'not_accepted'],
            ['name' => 'Не принят на вакансию']
        );
    }

    public function down(): void
    {
        DB::table('application_statuses')
            ->where('code', 'not_accepted')
            ->delete();
    }
};

