<?php

namespace SineMacula\Laravel\Modules\Console\Commands;

use Illuminate\Console\Command;
use SineMacula\Laravel\Modules\Configuration\Modules;

/**
 * Clear the cached module paths.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 */
class ModuleClearCommand extends Command
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'module:clear';

    /** @var string The console command description. */
    protected $description = 'Clear all cached modules';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        Modules::clearCache();

        $this->components->info('Cached modules cleared successfully.');
    }
}
