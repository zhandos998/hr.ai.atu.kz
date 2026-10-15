<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_approval_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('approval_order')->default(1);
            $table->timestamps();

            $table->unique(['department_id', 'approver_user_id']);
        });

        Schema::create('personnel_requests', function (Blueprint $table) {
            $table->id();
            $table->string('employee_name');
            $table->string('action_type', 40);
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('target_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('target_position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->decimal('salary', 14, 2)->nullable();
            $table->string('work_experience')->nullable();
            $table->text('details')->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['department_id', 'action_type']);
        });

        Schema::create('personnel_request_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_request_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });

        Schema::create('personnel_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('approval_order')->default(1);
            $table->string('decision', 20)->default('pending');
            $table->text('comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['personnel_request_id', 'user_id']);
            $table->index(['user_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_request_approvals');
        Schema::dropIfExists('personnel_request_documents');
        Schema::dropIfExists('personnel_requests');
        Schema::dropIfExists('personnel_approval_routes');
    }
};
