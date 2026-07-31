<?php

declare(strict_types = 1);

namespace SineMacula\Laravel\Modules\Console\Commands;

use Illuminate\Console\Command;
use SineMacula\Laravel\Modules\Configuration\Modules;
use SineMacula\Laravel\Modules\Exceptions\ModuleException;

/**
 * List all discovered application modules.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 */
final class ModuleListCommand extends Command
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'module:list';

    /** @var string The console command description. */
    protected $description = 'List all discovered modules and their paths';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        try {
            $modules = Modules::getModules();
        } catch (ModuleException $e) {

            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if (empty($modules)) {

            $this->components->warn('No modules discovered.');

            return self::SUCCESS;
        }

        $this->table(
            ['Module', 'Path'],
            array_map(
                static fn (string $path, string $name): array => [$name, $path],
                $modules,
                array_keys($modules),
            ),
        );

        return self::SUCCESS;
    }
}
