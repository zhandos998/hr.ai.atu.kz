<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('db:copy-mysql-to-sqlite {--path=database/mysql_export.sqlite} {--fresh : Overwrite the target SQLite file}', function () {
    $targetPath = (string) $this->option('path');
    $targetPath = preg_match('/^[A-Za-z]:[\/\\\\]|^\//', $targetPath)
        ? $targetPath
        : base_path($targetPath);

    if (File::exists($targetPath)) {
        if (!$this->option('fresh')) {
            $this->error("SQLite file already exists: {$targetPath}");
            $this->line('Run again with --fresh to overwrite it.');

            return self::FAILURE;
        }

        File::delete($targetPath);
    }

    File::ensureDirectoryExists(dirname($targetPath));
    File::put($targetPath, '');

    Config::set('database.connections.sqlite_export', [
        'driver' => 'sqlite',
        'database' => $targetPath,
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);
    Config::set('database.default', 'sqlite_export');
    Config::set('telescope.storage.database.connection', 'sqlite_export');

    DB::purge('sqlite_export');
    DB::connection('sqlite_export')->getPdo()->exec('PRAGMA foreign_keys = OFF');

    $this->info('Preparing SQLite schema...');
    $migrationCode = $this->call('migrate:fresh', [
        '--database' => 'sqlite_export',
        '--force' => true,
    ]);

    if ($migrationCode !== self::SUCCESS) {
        return $migrationCode;
    }

    DB::connection('sqlite_export')->getPdo()->exec('PRAGMA foreign_keys = OFF');

    $mysqlDatabase = DB::connection('mysql')->getDatabaseName();
    $tables = collect(DB::connection('mysql')->select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"'))
        ->map(fn ($row) => array_values((array) $row)[0])
        ->values();

    $this->info("Copying {$tables->count()} tables from MySQL database [{$mysqlDatabase}]...");

    foreach ($tables as $table) {
        if (!Schema::connection('sqlite_export')->hasTable($table)) {
            $this->warn("Skipping [{$table}]: target table does not exist.");
            continue;
        }

        $sourceColumns = Schema::connection('mysql')->getColumnListing($table);
        $targetColumns = Schema::connection('sqlite_export')->getColumnListing($table);
        $columns = array_values(array_intersect($sourceColumns, $targetColumns));

        if (empty($columns)) {
            $this->warn("Skipping [{$table}]: no matching columns.");
            continue;
        }

        DB::connection('sqlite_export')->table($table)->delete();

        $copied = 0;
        DB::connection('mysql')
            ->table($table)
            ->select($columns)
            ->orderBy($columns[0])
            ->chunk(500, function ($rows) use ($table, $columns, &$copied) {
                $payload = $rows
                    ->map(fn ($row) => collect((array) $row)->only($columns)->all())
                    ->all();

                if (!empty($payload)) {
                    DB::connection('sqlite_export')->table($table)->insert($payload);
                    $copied += count($payload);
                }
            });

        $this->line("Copied [{$table}]: {$copied}");
    }

    $foreignKeyIssues = DB::connection('sqlite_export')->select('PRAGMA foreign_key_check');
    if (!empty($foreignKeyIssues)) {
        $this->warn('SQLite foreign key check found issues. Review copied data before using it.');
    }

    $this->info("Done. SQLite database: {$targetPath}");

    return self::SUCCESS;
})->purpose('Copy current MySQL database data into a standalone SQLite file');
