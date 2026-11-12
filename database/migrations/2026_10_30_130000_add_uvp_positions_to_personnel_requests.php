<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personnel_requests', function (Blueprint $table) {
            $table->foreignId('uvp_position_id')->nullable()->constrained('uvp_positions')->restrictOnDelete();
            $table->foreignId('target_uvp_position_id')->nullable()->constrained('uvp_positions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('personnel_requests')->whereNotNull('uvp_position_id')->orWhereNotNull('target_uvp_position_id')->exists()) {
            throw new RuntimeException('Cannot remove UVP position references while personnel requests use them.');
        }

        Schema::table('personnel_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uvp_position_id');
            $table->dropConstrainedForeignId('target_uvp_position_id');
        });
    }
};
