<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DatabaseBackup extends Command
{
    protected $signature = 'backup:run {--keep=7 : 保持する世代数}';

    protected $description = '日次DBバックアップを実行する（NFR-031）';

    public function handle(): int
    {
        $driver = DB::getDriverName();
        $timestamp = now()->format('Y-m-d_His');
        $backupDir = storage_path('backups');

        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = "backup_{$timestamp}.sql";
        $filepath = "{$backupDir}/{$filename}";

        $exitCode = match ($driver) {
            'mysql' => $this->dumpMysql($filepath),
            'pgsql' => $this->dumpPgsql($filepath),
            'sqlite' => $this->dumpSqlite($filepath),
            default => 1,
        };

        if ($exitCode !== 0) {
            $this->error("バックアップに失敗しました（driver: {$driver}）");
            return self::FAILURE;
        }

        $this->info("バックアップ完了: {$filename}");
        $this->pruneOldBackups($backupDir, (int) $this->option('keep'));

        return self::SUCCESS;
    }

    private function dumpMysql(string $filepath): int
    {
        $config = config('database.connections.mysql');
        $host = $config['host'];
        $port = $config['port'];
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'];

        $cmd = "mysqldump --host={$host} --port={$port} --user={$username}"
            . ($password ? " --password={$password}" : '')
            . " {$database} > {$filepath} 2>/dev/null";

        exec($cmd, $output, $exitCode);
        return $exitCode;
    }

    private function dumpPgsql(string $filepath): int
    {
        $config = config('database.connections.pgsql');
        $host = $config['host'];
        $port = $config['port'];
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'];

        $env = $password ? "PGPASSWORD={$password} " : '';
        $cmd = "{$env}pg_dump --host={$host} --port={$port} --username={$username}"
            . " {$database} > {$filepath} 2>/dev/null";

        exec($cmd, $output, $exitCode);
        return $exitCode;
    }

    private function dumpSqlite(string $filepath): int
    {
        $source = config('database.connections.sqlite.database');
        if (! file_exists($source)) {
            return 1;
        }
        return copy($source, $filepath) ? 0 : 1;
    }

    private function pruneOldBackups(string $dir, int $keep): void
    {
        $files = glob("{$dir}/backup_*.sql");
        if (! $files || count($files) <= $keep) {
            return;
        }
        sort($files);
        $toDelete = array_slice($files, 0, count($files) - $keep);
        foreach ($toDelete as $file) {
            unlink($file);
        }
    }
}
