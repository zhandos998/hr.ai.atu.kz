<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('application_pps_profiles') || !Schema::hasTable('application_pps_profile_documents')) {
            return;
        }

        $columnToCategory = [
            'basic_education_document_path' => 'basic_education',
            'magistracy_document_path' => 'magistracy',
            'scientific_degree_document_path' => 'scientific_degree',
            'academic_title_document_path' => 'academic_title',
            'scientific_works_document_path' => 'scientific_works',
        ];

        $profiles = DB::table('application_pps_profiles')
            ->select(array_merge(['id'], array_keys($columnToCategory)))
            ->get();

        $timestamp = now();

        foreach ($profiles as $profile) {
            $updates = [];

            foreach ($columnToCategory as $column => $category) {
                $path = $profile->{$column} ?? null;
                if (empty($path)) {
                    continue;
                }

                $exists = DB::table('application_pps_profile_documents')
                    ->where('application_pps_profile_id', $profile->id)
                    ->where('category', $category)
                    ->where('file_path', $path)
                    ->exists();

                if (!$exists) {
                    DB::table('application_pps_profile_documents')->insert([
                        'application_pps_profile_id' => $profile->id,
                        'category' => $category,
                        'original_name' => basename($path),
                        'file_path' => $path,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }

                $updates[$column] = null;
            }

            if (!empty($updates)) {
                DB::table('application_pps_profiles')
                    ->where('id', $profile->id)
                    ->update($updates);
            }
        }
    }

    public function down(): void
    {
    }
};
