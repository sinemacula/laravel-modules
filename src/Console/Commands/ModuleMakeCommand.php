<?php

namespace SineMacula\Laravel\Modules\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use SineMacula\Laravel\Modules\Configuration\Modules;

/**
 * Scaffold a new module with the standard directory structure.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 */
class ModuleMakeCommand extends Command
{
    /** @var list<string> The directories to create within a new module. */
    private const array DIRECTORIES = [
        'Console/Commands',
        'Http/Controllers',
        'Http/Requests',
        'Listeners',
        'Models',
    ];

    /** @var string The name and signature of the console command. */
    protected $signature = 'module:make {name : The name of the module}';

    /** @var string The console command description. */
    protected $description = 'Create a new module directory structure';

    /**
     * Execute the console command.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $filesystem
     * @return int
     */
    public function handle(Filesystem $filesystem): int
    {
        $argument = $this->argument('name');

        if (!is_string($argument)) { // @codeCoverageIgnore

            $this->components->error('Invalid module name.'); // @codeCoverageIgnore

            return self::FAILURE; // @codeCoverageIgnore
        }

        $name       = Str::studly($argument);
        $modulePath = Modules::modulesPath() . DIRECTORY_SEPARATOR . $name;

        if ($filesystem->isDirectory($modulePath)) {

            $this->components->error("Module [{$name}] already exists.");

            return self::FAILURE;
        }

        foreach (self::DIRECTORIES as $directory) {

            $path = $modulePath . DIRECTORY_SEPARATOR . $directory;

            $filesystem->ensureDirectoryExists($path);
            $filesystem->put($path . DIRECTORY_SEPARATOR . '.gitkeep', '');
        }

        $filesystem->put(
            $modulePath . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'routes.php',
            "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n",
        );

        $this->components->info("Module [{$name}] created successfully.");

        return self::SUCCESS;
    }
}
