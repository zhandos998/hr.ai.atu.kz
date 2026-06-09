<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_ai_results', function (Blueprint $table) {
            $table->dropUnique(['worker_id', 'position_id', 'lang']);

            $table->foreignId('application_id')
                ->nullable()
                ->after('id')
                ->constrained('applications')
                ->cascadeOnDelete();

            $table->unique(['application_id', 'lang']);
        });
    }

    public function down(): void
    {
        Schema::table('candidate_ai_results', function (Blueprint $table) {
            $table->dropUnique(['application_id', 'lang']);
            $table->dropConstrainedForeignId('application_id');

            $table->unique(['worker_id', 'position_id', 'lang']);
        });
    }
};
