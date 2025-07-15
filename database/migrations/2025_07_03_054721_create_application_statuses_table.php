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
        Schema::create('application_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // например, 'pending'
            $table->string('name'); // например, 'На рассмотрении'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_statuses');
    }
};
