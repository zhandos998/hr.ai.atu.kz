<?php

declare(strict_types=1);

namespace Vacancy\Docker;

function phpSecurityIssues(int $versionId, array $settings): array
{
    $issues = [];
    if ($versionId < 80336 || $versionId >= 80400) {
        $issues[] = 'This image requires patched PHP 8.3.36 or newer within the 8.3 branch.';
    }
    if (filter_var($settings['session.upload_progress.enabled'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false
        || ! array_key_exists('session.upload_progress.enabled', $settings)) {
        $issues[] = 'session.upload_progress.enabled must be disabled in the startup configuration.';
    }
    if (! in_array(strtolower(trim((string) ($settings['memory_limit'] ?? ''))), ['512m', '536870912'], true)) {
        $issues[] = 'memory_limit must remain at the configured 512M baseline; unlimited memory is not permitted.';
    }

    return $issues;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $issues = phpSecurityIssues(PHP_VERSION_ID, [
        'session.upload_progress.enabled' => ini_get('session.upload_progress.enabled'),
        'memory_limit' => ini_get('memory_limit'),
    ]);
    if ($issues !== []) {
        fwrite(STDERR, 'PHP security baseline failed: '.implode(' ', $issues).PHP_EOL);
        exit(1);
    }
    fwrite(STDOUT, 'PHP security baseline OK: PHP '.PHP_VERSION.'; session upload progress disabled; memory limit 512M.'.PHP_EOL);
}
