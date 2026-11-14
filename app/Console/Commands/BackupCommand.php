<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Database backup (step 15, §8.2). PostgreSQL: pg_dump, gzipped. SQLite: copy of the file.
 * Keeps the newest --keep files in storage/app/backups. Copy that folder off the server daily.
 * The password goes to pg_dump through the environment, never on the command line.
 */
class BackupCommand extends Command
{
    protected $signature = 'woodstone:backup {--keep=14 : How many backups to keep}';

    protected $description = 'Back up the database to storage/app/backups and remove old backups';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir, 0700);
        $stamp = now()->format('Ymd-His');
        $connection = (string) config('database.default');
        $cfg = (array) config("database.connections.{$connection}");
        $driver = (string) ($cfg['driver'] ?? '');

        if ($driver === 'sqlite') {
            $db = (string) ($cfg['database'] ?? '');
            if ($db === '' || $db === ':memory:' || ! is_file($db)) {
                $this->error('SQLite database file not found; nothing to back up.');

                return self::FAILURE;
            }
            $target = "{$dir}/woodstone-{$stamp}.sqlite";
            File::copy($db, $target);
        } elseif ($driver === 'pgsql') {
            $target = "{$dir}/woodstone-{$stamp}.sql.gz";
            $result = Process::env(['PGPASSWORD' => (string) ($cfg['password'] ?? '')])
                ->timeout(600)
                ->run(sprintf(
                    'pg_dump --no-owner --no-privileges -h %s -p %s -U %s %s | gzip > %s',
                    escapeshellarg((string) ($cfg['host'] ?? '127.0.0.1')),
                    escapeshellarg((string) ($cfg['port'] ?? '5432')),
                    escapeshellarg((string) ($cfg['username'] ?? '')),
                    escapeshellarg((string) ($cfg['database'] ?? '')),
                    escapeshellarg($target),
                ));
            if (! $result->successful() || ! is_file($target) || filesize($target) < 100) {
                File::delete($target);
                $this->error('pg_dump failed: '.trim($result->errorOutput()));

                return self::FAILURE;
            }
        } else {
            $this->error("Backups are not set up for the {$driver} driver.");

            return self::FAILURE;
        }

        @chmod($target, 0600);
        $this->info('Backup written: '.basename($target).' ('.number_format((int) filesize($target) / 1024, 1).' KB)');

        $files = collect(File::files($dir))->filter(fn (SplFileInfo $f): bool => str_starts_with($f->getFilename(), 'woodstone-'))->sortByDesc(fn (SplFileInfo $f): string => $f->getFilename())->values();
        $keep = max(1, (int) $this->option('keep'));
        foreach ($files->slice($keep) as $old) {
            File::delete($old->getPathname());
            $this->line('Removed old backup '.$old->getFilename());
        }

        return self::SUCCESS;
    }
}
