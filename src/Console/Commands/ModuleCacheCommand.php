<?php

namespace SineMacula\Laravel\Modules\Console\Commands;

use Illuminate\Console\Command;
use SineMacula\Laravel\Modules\Configuration\Modules;

/**
 * Cache the discovered module paths for faster resolution.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 */
class ModuleCacheCommand extends Command
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'module:cache';

    /** @var string The console command description. */
    protected $description = 'Discover and cache the application\'s module paths';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        Modules::cache();

        $this->components->info('Modules cached successfully.');
    }
}
