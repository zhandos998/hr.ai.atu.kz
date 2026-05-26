<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_stage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 32);
            $table->string('old_status', 32)->nullable();
            $table->string('new_status', 32);
            $table->text('comment')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['application_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_stage_logs');
    }
};
