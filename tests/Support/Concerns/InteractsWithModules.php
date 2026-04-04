<?php

namespace Tests\Support\Concerns;

use SineMacula\Laravel\Modules\Configuration\Modules;

/**
 * Provides module state management utilities for tests.
 *
 * Handles resetting Modules static state, initialising the base path, and
 * creating module directory structures within a temporary directory.
 *
 * @SuppressWarnings("php:S3011")
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
trait InteractsWithModules
{
    /**
     * Reset all static state on the Modules class.
     *
     * @return void
     */
    protected function resetModulesState(): void
    {
        $reflection = new \ReflectionClass(Modules::class);

        $modules = $reflection->getProperty('modules');
        $modules->setValue(null, null);

        $resolvedPaths = $reflection->getProperty('resolvedPaths');
        $resolvedPaths->setValue(null, []);
    }

    /**
     * Initialise the Modules class with the given base path after resetting
     * state.
     *
     * @param  string  $basePath
     * @return void
     */
    protected function initModules(string $basePath): void
    {
        $this->resetModulesState();

        Modules::setBasePath($basePath);
    }

    /**
     * Create a module directory structure within the temporary directory.
     *
     * Each key is a module name, and the value is an array of subdirectory
     * paths to create within that module.
     *
     * @param  array<string, list<string>>  $modules
     * @return void
     */
    protected function createModuleStructure(array $modules): void
    {
        /** @phpstan-ignore property.notFound */
        $base = $this->tempDir;

        $modulesDir = $base . DIRECTORY_SEPARATOR . 'modules';

        if (!is_dir($modulesDir)) {
            mkdir($modulesDir, 0755, true);
        }

        foreach ($modules as $name => $subdirectories) {
            $moduleDir = $modulesDir
                . DIRECTORY_SEPARATOR
                . $name;

            if (!is_dir($moduleDir)) {
                mkdir($moduleDir, 0755, true);
            }

            foreach ($subdirectories as $subdirectory) {
                $path = $moduleDir
                    . DIRECTORY_SEPARATOR
                    . $subdirectory;

                if (!is_dir($path)) {
                    mkdir($path, 0755, true);
                }
            }
        }
    }
}
