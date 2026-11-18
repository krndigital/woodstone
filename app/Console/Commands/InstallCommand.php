<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Menu\LoadWoodstoneMenu;
use App\Enums\Role;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One command for a fresh or updated server (docs/CPANEL.md, docs/DEPLOY.md):
 * checks PHP, makes the app key if missing, migrates, links storage, caches config/routes/views,
 * and creates the owner on the first run. Safe to run again after every update.
 */
class InstallCommand extends Command
{
    protected $signature = 'woodstone:install {--no-owner : Do not ask to create the owner}';

    protected $description = 'Install or update WoodStone on a server (key, migrate, storage link, caches, owner)';

    public function handle(): int
    {
        $missing = array_values(array_filter(['pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'zip'], fn (string $e): bool => ! extension_loaded($e)));
        if ($missing !== []) {
            $this->error('PHP '.PHP_VERSION.' is missing: '.implode(', ', $missing).'. Turn them on in cPanel → Select PHP Version.');

            return self::FAILURE;
        }

        if ((string) config('app.key') === '') {
            $this->call('key:generate', ['--force' => true]);
            $this->warn('App key created. Run this command once more so the new key is loaded.');

            return self::SUCCESS;
        }

        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->error('Cannot reach the database. Check DB_* in .env. ('.$e->getMessage().')');

            return self::FAILURE;
        }

        $this->call('down', ['--retry' => 15]);
        try {
            $this->call('migrate', ['--force' => true]);
        } finally {
            $this->call('up');
        }

        if (! is_link(public_path('storage')) && ! is_dir(public_path('storage'))) {
            try {
                $this->call('storage:link');
            } catch (Throwable) {
                $this->warn('Could not link public/storage (symlinks off on this host). Photos and receipts will not show; ask the host to allow symlinks.');
            }
        }

        // First install: load WoodStone's own menu with prices and photos.
        if (! MenuItem::query()->exists()) {
            $c = app(LoadWoodstoneMenu::class)->handle();
            $this->info("Menu loaded: {$c['created']} items with prices and photos.");
        }

        $this->call('optimize:clear');
        $this->call('optimize');

        if (! $this->option('no-owner') && $this->input->isInteractive() && ! User::query()->where('role', Role::SuperAdmin->value)->exists()) {
            $this->call('woodstone:create-owner');
        }

        $this->info('WoodStone is ready. Cron needed: * * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1');

        return self::SUCCESS;
    }
}
