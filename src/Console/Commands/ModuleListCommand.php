<?php

namespace SineMacula\Laravel\Modules\Console\Commands;

use Illuminate\Console\Command;
use SineMacula\Laravel\Modules\Configuration\Modules;

/**
 * List all discovered application modules.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 */
class ModuleListCommand extends Command
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'module:list';

    /** @var string The console command description. */
    protected $description = 'List all discovered modules and their paths';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $modules = Modules::getModules();

        if (empty($modules)) {

            $this->components->warn('No modules discovered.');

            return;
        }

        $this->table(
            ['Module', 'Path'],
            array_map(
                static fn (string $path, string $name): array => [$name, $path],
                $modules,
                array_keys($modules),
            ),
        );
    }
}
