<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pps_faculty_commission_members', function (Blueprint $table) {
            $table->id();
            $table->string('faculty_name');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['faculty_name', 'user_id'], 'pps_faculty_commission_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pps_faculty_commission_members');
    }
};
