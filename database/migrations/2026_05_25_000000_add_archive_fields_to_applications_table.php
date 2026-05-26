<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('updated_at');
            $table->foreignId('archived_by_user_id')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('archived_by_user_id');
            $table->dropColumn('archived_at');
        });
    }
};
