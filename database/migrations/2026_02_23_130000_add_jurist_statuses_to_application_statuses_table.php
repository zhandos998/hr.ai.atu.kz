<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('application_statuses')->updateOrInsert(
            ['code' => 'corruption_not_found'],
            ['name' => 'Сведения о причастности к коррупционным правонарушениям не выявлены']
        );

        DB::table('application_statuses')->updateOrInsert(
            ['code' => 'corruption_found'],
            ['name' => 'Сведения о причастности к коррупционным правонарушениям выявлены']
        );
    }

    public function down(): void
    {
        DB::table('application_statuses')
            ->whereIn('code', ['corruption_not_found', 'corruption_found'])
            ->delete();
    }
};
