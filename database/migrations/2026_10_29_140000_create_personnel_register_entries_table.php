<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_register_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('employee_name');
            $table->string('position_name', 500);
            $table->string('document_number');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('diagnosis')->nullable();
            $table->string('leave_type')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['type', 'deleted_at', 'id']);
            $table->index(['type', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_register_entries');
    }
};
