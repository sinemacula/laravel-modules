<?php

declare(strict_types = 1);

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
final class ModuleMakeCommand extends Command
{
    /** @var list<string> The directories to create within every new module. */
    private const array DIRECTORIES = [
        'Console/Commands',
        'Http/Controllers',
        'Http/Requests',
        'Listeners',
        'Models',
    ];

    /** @var list<string> The directories to create only outside the default module. */
    private const array RESOURCE_DIRECTORIES = [
        'Resources/lang',
        'Resources/views',
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

        $name = Str::studly($argument);

        if (preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $name) !== 1) {

            $this->components->error("Invalid module name [{$argument}].");

            return self::FAILURE;
        }

        return $this->scaffold($filesystem, $name);
    }

    /**
     * Determine whether a module with the given name already exists.
     *
     * Discovery keys modules by their lowercased name, so a differently cased
     * sibling would collide on a case-sensitive filesystem. The modules
     * directory is scanned directly rather than resolved, because a cached
     * manifest can predate the module being checked for.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $filesystem
     * @param  string  $modulesPath
     * @param  string  $name
     * @return bool
     */
    private function hasModuleNamed(Filesystem $filesystem, string $modulesPath, string $name): bool
    {
        if (!$filesystem->isDirectory($modulesPath)) {
            return false;
        }

        foreach ($filesystem->directories($modulesPath) as $directory) {
            if (strtolower(basename($directory)) === strtolower($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scaffold the module directory structure on disk.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $filesystem
     * @param  string  $name
     * @return int
     */
    private function scaffold(Filesystem $filesystem, string $name): int
    {
        $modulesPath = Modules::modulesPath();
        $modulePath  = $modulesPath . DIRECTORY_SEPARATOR . $name;

        if ($this->hasModuleNamed($filesystem, $modulesPath, $name)) {

            $this->components->error("Module [{$name}] already exists.");

            return self::FAILURE;
        }

        $isDefault = strtolower($name) === Modules::defaultModule();

        $directories = $isDefault
            ? self::DIRECTORIES
            : [...self::DIRECTORIES, ...self::RESOURCE_DIRECTORIES];

        try {

            foreach ($directories as $directory) {

                $path = $modulePath . DIRECTORY_SEPARATOR . $directory;

                $filesystem->ensureDirectoryExists($path);
                $filesystem->put($path . DIRECTORY_SEPARATOR . '.gitkeep', '');
            }

            $filesystem->put(
                $modulePath . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'routes.php',
                "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n",
            );

            $filesystem->put(
                $modulePath . DIRECTORY_SEPARATOR . 'Console' . DIRECTORY_SEPARATOR . 'schedule.php',
                "<?php\n\nuse Illuminate\\Support\\Facades\\Schedule;\n",
            );
        } catch (\Throwable $exception) { // @phpstan-ignore catch.neverThrown

            $this->components->error("Failed to create module [{$name}]: " . $exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Module [{$name}] created successfully.");

        if ($isDefault) {
            $this->components->info(
                "No Resources directory was created for [{$name}]. Adding one to the "
                . 'default module repoints resource_path() and lang_path() into it.',
            );
        }

        return self::SUCCESS;
    }
}
