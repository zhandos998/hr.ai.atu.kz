<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_unique');
        });
    }

    public function down(): void
    {
        // Phone numbers are intentionally non-unique now. Recreating the index
        // can fail after duplicate phone numbers have been saved.
    }
};
