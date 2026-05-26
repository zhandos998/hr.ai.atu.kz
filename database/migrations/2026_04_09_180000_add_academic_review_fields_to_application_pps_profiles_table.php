<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_pps_profiles', function (Blueprint $table) {
            $table->text('open_lesson_quality')->nullable()->after('krk');
            $table->text('taught_disciplines')->nullable()->after('open_lesson_quality');
            $table->text('educational_methodical_literature')->nullable()->after('taught_disciplines');
        });
    }

    public function down(): void
    {
        Schema::table('application_pps_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'open_lesson_quality',
                'taught_disciplines',
                'educational_methodical_literature',
            ]);
        });
    }
};
