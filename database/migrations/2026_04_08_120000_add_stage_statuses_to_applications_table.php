<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('resume_status', 32)->default('pending')->after('status_id');
            $table->text('resume_comment')->nullable()->after('resume_status');
            $table->string('documents_status', 32)->default('not_requested')->after('resume_comment');
            $table->text('documents_comment')->nullable()->after('documents_status');
            $table->string('compliance_status', 32)->default('not_started')->after('documents_comment');
            $table->text('compliance_comment')->nullable()->after('compliance_status');
            $table->string('hiring_status', 32)->default('not_started')->after('compliance_comment');
            $table->text('hiring_comment')->nullable()->after('hiring_status');
        });

        $applications = DB::table('applications')
            ->leftJoin('application_statuses', 'applications.status_id', '=', 'application_statuses.id')
            ->select('applications.id', 'application_statuses.code')
            ->get();

        foreach ($applications as $application) {
            $state = match ($application->code) {
                'resume_rejected' => [
                    'resume_status' => 'rejected',
                ],
                'resume_accepted' => [
                    'resume_status' => 'accepted',
                    'documents_status' => 'awaiting_upload',
                ],
                'docs_uploaded' => [
                    'resume_status' => 'accepted',
                    'documents_status' => 'uploaded',
                ],
                'docs_rejected' => [
                    'resume_status' => 'accepted',
                    'documents_status' => 'rejected',
                ],
                'docs_accepted' => [
                    'resume_status' => 'accepted',
                    'documents_status' => 'accepted',
                ],
                'corruption_not_found' => [
                    'resume_status' => 'accepted',
                    'documents_status' => 'accepted',
                    'compliance_status' => 'clear',
                    'hiring_status' => 'voting',
                ],
                'corruption_found' => [
                    'resume_status' => 'accepted',
                    'documents_status' => 'accepted',
                    'compliance_status' => 'flagged',
                ],
                'completed' => [
                    'resume_status' => 'accepted',
                    'documents_status' => 'accepted',
                    'compliance_status' => 'clear',
                    'hiring_status' => 'hired',
                ],
                'not_accepted' => [
                    'resume_status' => 'accepted',
                    'documents_status' => 'accepted',
                    'compliance_status' => 'clear',
                    'hiring_status' => 'rejected',
                ],
                default => [
                    'resume_status' => 'pending',
                    'documents_status' => 'not_requested',
                    'compliance_status' => 'not_started',
                    'hiring_status' => 'not_started',
                ],
            };

            DB::table('applications')
                ->where('id', $application->id)
                ->update($state);
        }
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'resume_status',
                'resume_comment',
                'documents_status',
                'documents_comment',
                'compliance_status',
                'compliance_comment',
                'hiring_status',
                'hiring_comment',
            ]);
        });
    }
};
