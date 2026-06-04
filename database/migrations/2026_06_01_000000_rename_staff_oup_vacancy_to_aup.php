<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('vacancies')
            ->where('type', 'staff')
            ->whereNull('position_id')
            ->where('title', 'ОУП')
            ->update(['title' => 'АУП']);
    }

    public function down(): void
    {
        DB::table('vacancies')
            ->where('type', 'staff')
            ->whereNull('position_id')
            ->where('title', 'АУП')
            ->update(['title' => 'ОУП']);
    }
};
