<?php

namespace App\Support;

use App\Models\Application;
use App\Models\ApplicationStageLog;
use App\Models\ApplicationStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApplicationStageManager
{
    public const STAGE_FIELDS = [
        'resume' => 'resume_status',
        'documents' => 'documents_status',
        'compliance' => 'compliance_status',
        'hiring' => 'hiring_status',
    ];

    public const COMMENT_FIELDS = [
        'resume' => 'resume_comment',
        'documents' => 'documents_comment',
        'compliance' => 'compliance_comment',
        'hiring' => 'hiring_comment',
    ];

    public const STAGE_OPTIONS = [
        'resume' => ['pending', 'accepted', 'rejected'],
        'documents' => ['not_requested', 'awaiting_upload', 'uploaded', 'accepted', 'rejected'],
        'compliance' => ['not_started', 'clear', 'flagged'],
        'hiring' => ['not_started', 'voting', 'hired_1_year', 'hired_3_year', 'hired', 'rejected'],
    ];

    private static array $legacyStatusIds = [];

    public static function ensureDefaults(Application $application): void
    {
        foreach (array_keys(self::STAGE_FIELDS) as $stage) {
            $field = self::STAGE_FIELDS[$stage];
            if (!$application->{$field}) {
                $application->{$field} = self::defaultStatus($stage);
            }
        }
    }

    public static function defaultStatus(string $stage): string
    {
        return match ($stage) {
            'resume' => 'pending',
            'documents' => 'not_requested',
            'compliance' => 'not_started',
            'hiring' => 'not_started',
            default => throw new InvalidArgumentException("Unknown stage [{$stage}]"),
        };
    }

    public static function stageField(string $stage): string
    {
        return self::STAGE_FIELDS[$stage]
            ?? throw new InvalidArgumentException("Unknown stage [{$stage}]");
    }

    public static function commentField(string $stage): string
    {
        return self::COMMENT_FIELDS[$stage]
            ?? throw new InvalidArgumentException("Unknown stage [{$stage}]");
    }

    public static function validateStageStatus(string $stage, string $status): void
    {
        $allowed = self::STAGE_OPTIONS[$stage] ?? null;

        if (!$allowed) {
            throw new InvalidArgumentException("Unknown stage [{$stage}]");
        }

        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException("Invalid status [{$status}] for stage [{$stage}]");
        }
    }

    public static function setLegacyStatus(Application $application, string $legacyCode, ?string $comment = null, ?int $authorId = null): Application
    {
        $mapping = self::legacyCodeToStageUpdate($legacyCode);

        if (!$mapping) {
            throw new InvalidArgumentException("Unknown legacy status [{$legacyCode}]");
        }

        return self::setStage($application, $mapping['stage'], $mapping['status'], $comment, $authorId);
    }

    public static function legacyCodeToStageUpdate(string $legacyCode): ?array
    {
        return match ($legacyCode) {
            'pending' => ['stage' => 'resume', 'status' => 'pending'],
            'resume_rejected' => ['stage' => 'resume', 'status' => 'rejected'],
            'resume_accepted' => ['stage' => 'resume', 'status' => 'accepted'],
            'docs_uploaded' => ['stage' => 'documents', 'status' => 'uploaded'],
            'docs_rejected' => ['stage' => 'documents', 'status' => 'rejected'],
            'docs_accepted' => ['stage' => 'documents', 'status' => 'accepted'],
            'corruption_not_found' => ['stage' => 'compliance', 'status' => 'clear'],
            'corruption_found' => ['stage' => 'compliance', 'status' => 'flagged'],
            'completed' => ['stage' => 'hiring', 'status' => 'hired'],
            'not_accepted' => ['stage' => 'hiring', 'status' => 'rejected'],
            default => null,
        };
    }

    public static function setStage(
        Application $application,
        string $stage,
        string $status,
        ?string $comment = null,
        ?int $authorId = null
    ): Application {
        self::validateStageStatus($stage, $status);
        $comment = self::normalizeComment($comment);
        $stageUpdate = self::normalizeStageUpdate($stage, $status);

        return DB::transaction(function () use ($application, $stage, $stageUpdate, $comment, $authorId) {
            $application = $application->fresh() ?? $application;
            self::ensureDefaults($application);
            $status = $stageUpdate['status'];
            self::assertPrerequisites($application, $stage, $status);

            $logs = [];
            self::recordStageChange($application, $stage, $status, $comment, $authorId, $logs);
            self::applyStageSideEffects($application, $stage, $stageUpdate);
            self::cascadeStageDependencies($application, $stage, $status, $authorId, $logs);
            self::syncLegacyStatus($application);

            if ($application->isDirty()) {
                $application->save();
            }

            foreach ($logs as $log) {
                ApplicationStageLog::create($log);
            }

            return $application->fresh([
                'status',
                'stageLogs.author:id,name',
            ]);
        });
    }

    private static function normalizeStageUpdate(string $stage, string $status): array
    {
        if ($stage !== 'hiring') {
            return ['status' => $status];
        }

        return match ($status) {
            'hired_1_year' => [
                'status' => 'hired',
                'hiring_term_years' => 1,
                'update_hiring_term_years' => true,
            ],
            'hired_3_year' => [
                'status' => 'hired',
                'hiring_term_years' => 3,
                'update_hiring_term_years' => true,
            ],
            'not_started', 'voting', 'rejected' => [
                'status' => $status,
                'hiring_term_years' => null,
                'update_hiring_term_years' => true,
            ],
            default => ['status' => $status],
        };
    }

    private static function applyStageSideEffects(Application $application, string $stage, array $stageUpdate): void
    {
        if ($stage !== 'hiring' || !array_key_exists('update_hiring_term_years', $stageUpdate)) {
            return;
        }

        $application->hiring_term_years = $stageUpdate['hiring_term_years'];
    }

    public static function syncLegacyStatus(Application $application): void
    {
        $legacyCode = self::legacyStatusCode($application);
        $application->status_id = self::legacyStatusId($legacyCode);
    }

    public static function legacyStatusCode(Application $application): string
    {
        self::ensureDefaults($application);

        return match (true) {
            $application->hiring_status === 'hired' => 'completed',
            $application->hiring_status === 'rejected' => 'not_accepted',
            $application->compliance_status === 'clear' => 'corruption_not_found',
            $application->compliance_status === 'flagged' => 'corruption_found',
            $application->documents_status === 'accepted' => 'docs_accepted',
            $application->documents_status === 'rejected' => 'docs_rejected',
            $application->documents_status === 'uploaded' => 'docs_uploaded',
            $application->resume_status === 'accepted' => 'resume_accepted',
            $application->resume_status === 'rejected' => 'resume_rejected',
            default => 'pending',
        };
    }

    private static function legacyStatusId(string $code): ?int
    {
        if (!array_key_exists($code, self::$legacyStatusIds)) {
            self::$legacyStatusIds[$code] = ApplicationStatus::query()
                ->where('code', $code)
                ->value('id');
        }

        return self::$legacyStatusIds[$code];
    }

    private static function normalizeComment(?string $comment): ?string
    {
        $comment = trim((string) ($comment ?? ''));

        return $comment === '' ? null : $comment;
    }

    private static function assertPrerequisites(Application $application, string $stage, string $status): void
    {
        if ($stage === 'documents' && $status !== 'not_requested' && $application->resume_status !== 'accepted') {
            throw new InvalidArgumentException('Этап документов доступен только после принятия резюме.');
        }

        if ($stage === 'compliance' && $status !== 'not_started' && $application->documents_status !== 'accepted') {
            throw new InvalidArgumentException('Юридическая проверка доступна только после принятия документов.');
        }

        if ($stage === 'hiring' && $status !== 'not_started' && $application->compliance_status !== 'clear') {
            throw new InvalidArgumentException('Финальное решение доступно только после положительной проверки на коррупцию.');
        }
    }

    private static function recordStageChange(
        Application $application,
        string $stage,
        string $status,
        ?string $comment,
        ?int $authorId,
        array &$logs
    ): void {
        $statusField = self::stageField($stage);
        $commentField = self::commentField($stage);
        $oldStatus = $application->{$statusField} ?: self::defaultStatus($stage);
        $oldComment = $application->{$commentField};

        if ($stage === 'hiring' && $status !== 'hired') {
            $application->hiring_term_years = null;
        }

        if ($oldStatus === $status && $oldComment === $comment) {
            return;
        }

        $application->{$statusField} = $status;
        $application->{$commentField} = $comment;

        $logs[] = [
            'application_id' => $application->id,
            'stage' => $stage,
            'old_status' => $oldStatus,
            'new_status' => $status,
            'comment' => $comment,
            'author_id' => $authorId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private static function cascadeStageDependencies(
        Application $application,
        string $stage,
        string $status,
        ?int $authorId,
        array &$logs
    ): void {
        if ($stage === 'resume') {
            if ($status === 'accepted') {
                if ($application->documents_status === 'not_requested') {
                    self::recordStageChange(
                        $application,
                        'documents',
                        'awaiting_upload',
                        null,
                        $authorId,
                        $logs
                    );
                }
                return;
            }

            self::recordStageChange(
                $application,
                'documents',
                'not_requested',
                null,
                $authorId,
                $logs
            );
            self::recordStageChange(
                $application,
                'compliance',
                'not_started',
                null,
                $authorId,
                $logs
            );
            self::recordStageChange(
                $application,
                'hiring',
                'not_started',
                null,
                $authorId,
                $logs
            );
            return;
        }

        if ($stage === 'documents') {
            if ($status === 'accepted') {
                self::recordStageChange(
                    $application,
                    'compliance',
                    'not_started',
                    null,
                    $authorId,
                    $logs
                );
                self::recordStageChange(
                    $application,
                    'hiring',
                    'not_started',
                    null,
                    $authorId,
                    $logs
                );
                return;
            }

            self::recordStageChange(
                $application,
                'compliance',
                'not_started',
                null,
                $authorId,
                $logs
            );
            self::recordStageChange(
                $application,
                'hiring',
                'not_started',
                null,
                $authorId,
                $logs
            );
            return;
        }

        if ($stage === 'compliance') {
            if ($status === 'clear' && $application->hiring_status === 'not_started') {
                self::recordStageChange(
                    $application,
                    'hiring',
                    'voting',
                    null,
                    $authorId,
                    $logs
                );
                return;
            }

            if ($status !== 'clear') {
                self::recordStageChange(
                    $application,
                    'hiring',
                    'not_started',
                    null,
                    $authorId,
                    $logs
                );
            }
        }
    }
}
