<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();

            // Связь с отделом
            $table->foreignId('department_id')
                ->constrained('departments')
                ->onDelete('cascade');

            // Название должности
            $table->string('name');

            // Обязанности / квалификационные требования
            $table->longText('duties')->nullable();

            // Требуемое образование
            $table->text('qualification')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
