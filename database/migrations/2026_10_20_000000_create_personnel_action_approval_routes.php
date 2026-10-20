<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Department templates remain intact; existing request approvals are not changed.
        Schema::create('personnel_action_approval_routes', function (Blueprint $table) {
            $table->id();
            $table->string('action_type', 40);
            $table->foreignId('approver_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('approval_order');
            $table->timestamps();
            $table->unique(['action_type', 'approver_user_id'], 'personnel_action_approver_unique');
            $table->index(['action_type', 'approval_order'], 'personnel_action_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel_action_approval_routes');
    }
};
