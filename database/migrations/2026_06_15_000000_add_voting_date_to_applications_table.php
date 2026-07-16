<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->date('voting_date')
                ->nullable()
                ->after('hiring_term_years');
        });

        if (Schema::hasTable('application_stage_logs')) {
            DB::table('application_stage_logs')
                ->select('application_id', DB::raw('MIN(created_at) as voting_at'))
                ->where('stage', 'hiring')
                ->where('new_status', 'voting')
                ->groupBy('application_id')
                ->orderBy('application_id')
                ->get()
                ->each(function ($row) {
                    DB::table('applications')
                        ->where('id', $row->application_id)
                        ->whereNull('voting_date')
                        ->update([
                            'voting_date' => substr((string) $row->voting_at, 0, 10),
                        ]);
                });
        }

        if (Schema::hasTable('application_commission_votes')) {
            DB::table('application_commission_votes')
                ->select('application_id', DB::raw('MIN(created_at) as voting_at'))
                ->groupBy('application_id')
                ->orderBy('application_id')
                ->get()
                ->each(function ($row) {
                    DB::table('applications')
                        ->where('id', $row->application_id)
                        ->whereNull('voting_date')
                        ->update([
                            'voting_date' => substr((string) $row->voting_at, 0, 10),
                        ]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('voting_date');
        });
    }
};
