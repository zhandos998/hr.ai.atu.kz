<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16);
            $table->unsignedInteger('number');
            $table->string('title');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['type', 'number']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('competition_id')->nullable()->constrained('competitions')->restrictOnDelete();
            $table->index(['competition_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['competition_id']);
            $table->dropIndex(['competition_id', 'archived_at']);
            $table->dropColumn('competition_id');
        });
        Schema::dropIfExists('competitions');
    }
};
