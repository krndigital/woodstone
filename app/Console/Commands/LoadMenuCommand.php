<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Menu\LoadWoodstoneMenu;
use Illuminate\Console\Command;

class LoadMenuCommand extends Command
{
    protected $signature = 'woodstone:load-menu {--archive-others : Archive menu items that are not on the WoodStone list}';

    protected $description = 'Load WoodStone\'s own menu with prices and photos (safe to run again)';

    public function handle(LoadWoodstoneMenu $load): int
    {
        $c = $load->handle(null, (bool) $this->option('archive-others'));
        $this->info("Menu loaded: {$c['created']} added, {$c['updated']} updated, {$c['archived']} archived.");

        return self::SUCCESS;
    }
}
