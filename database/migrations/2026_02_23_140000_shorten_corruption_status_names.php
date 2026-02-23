<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('application_statuses')
            ->where('code', 'corruption_not_found')
            ->update(['name' => 'Коррупция: не выявлена']);

        DB::table('application_statuses')
            ->where('code', 'corruption_found')
            ->update(['name' => 'Коррупция: выявлена']);
    }

    public function down(): void
    {
        DB::table('application_statuses')
            ->where('code', 'corruption_not_found')
            ->update(['name' => 'Сведения о причастности к коррупционным правонарушениям не выявлены']);

        DB::table('application_statuses')
            ->where('code', 'corruption_found')
            ->update(['name' => 'Сведения о причастности к коррупционным правонарушениям выявлены']);
    }
};
