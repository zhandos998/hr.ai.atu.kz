<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

use function Vacancy\Docker\phpSecurityIssues;

class DockerPhpSecurityTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2).'/docker/php/check-security.php';
    }

    public function test_only_patched_php_in_the_target_branch_is_accepted(): void
    {
        $settings = ['session.upload_progress.enabled' => '0', 'memory_limit' => '512M'];
        foreach ([80336, 80337] as $version) {
            $this->assertSame([], phpSecurityIssues($version, $settings));
        }
        foreach ([70433, 80131, 80335, 80427] as $version) {
            $this->assertNotEmpty(phpSecurityIssues($version, $settings));
        }
    }

    public function test_upload_progress_cannot_be_enabled_or_omitted(): void
    {
        foreach (['1', 'On', 'yes', 'invalid'] as $value) {
            $this->assertNotEmpty(phpSecurityIssues(80336, ['session.upload_progress.enabled' => $value, 'memory_limit' => '512M']));
        }
        $this->assertNotEmpty(phpSecurityIssues(80336, ['memory_limit' => '512M']));
        $this->assertSame([], phpSecurityIssues(80336, ['session.upload_progress.enabled' => '', 'memory_limit' => '512M']));
    }

    public function test_unlimited_or_missing_memory_limit_is_rejected(): void
    {
        foreach (['-1', '0', '', '2G'] as $value) {
            $this->assertNotEmpty(phpSecurityIssues(80336, ['session.upload_progress.enabled' => '0', 'memory_limit' => $value]));
        }
    }

    public function test_container_configuration_enforces_the_baseline_for_cli_and_fpm(): void
    {
        $root = dirname(__DIR__, 2);
        $ini = parse_ini_file($root.'/docker/php/php.ini', false, INI_SCANNER_RAW);
        $this->assertSame([], phpSecurityIssues(80336, $ini));
        $this->assertSame('60M', $ini['post_max_size']);
        $this->assertSame('50M', $ini['upload_max_filesize']);
        $this->assertSame('/proc/self/fd/2', $ini['error_log']);
        $pool = parse_ini_file($root.'/docker/php/security-fpm.conf', true, INI_SCANNER_RAW)['www'];
        $this->assertSame('off', $pool['php_admin_flag']['session.upload_progress.enabled']);
        $this->assertSame('512M', $pool['php_admin_value']['memory_limit']);
        $this->assertSame('off', $pool['php_admin_flag']['display_errors']);
        $dockerfile = file_get_contents($root.'/Dockerfile');
        $this->assertStringContainsString('FROM php:8.3.36-fpm-bookworm AS app', $dockerfile);
        $this->assertStringContainsString('RUN php /usr/local/bin/check-php-security.php', $dockerfile);
        $entrypoint = file_get_contents($root.'/docker/php/entrypoint.sh');
        $this->assertStringContainsString('php /usr/local/bin/check-php-security.php', $entrypoint);
    }
}
