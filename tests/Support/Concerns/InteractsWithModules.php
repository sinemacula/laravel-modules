<?php

declare(strict_types = 1);

namespace Tests\Support\Concerns;

use SineMacula\Laravel\Modules\Configuration\Modules;

/**
 * Provides module state management utilities for tests.
 *
 * Handles resetting Modules static state, initialising the base path, and
 * creating module directory structures within a temporary directory.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
trait InteractsWithModules
{
    /**
     * Return the resolver to its uninitialised state.
     *
     * Discards the base path along with the memoised maps, so a test needing no
     * base path does not depend on which tests the worker ran before it.
     *
     * @return void
     */
    protected function resetModulesState(): void
    {
        Modules::setBasePath(null);
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
        // @phpstan-ignore property.notFound (tempDir from sibling trait)
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

                if (is_dir($path)) {
                    continue;
                }

                mkdir($path, 0755, true);
            }
        }
    }
}
