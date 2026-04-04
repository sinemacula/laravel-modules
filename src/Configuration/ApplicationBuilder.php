<?php

namespace SineMacula\Laravel\Modules\Configuration;

use Illuminate\Foundation\Configuration\ApplicationBuilder as BaseApplicationBuilder;

/**
 * Build the configuration for the modularised Laravel application.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 */
class ApplicationBuilder extends BaseApplicationBuilder
{
    /**
     * Register module-aware services for the application.
     *
     * Discovers and registers event listeners, console commands, schedule
     * files, and service providers from each module using native glob-based
     * discovery.
     *
     * @return static
     */
    public function withModules(): static
    {
        return $this
            ->withKernels()
            ->withEvents(array_values(Modules::listenerPaths()))
            ->withCommands([
                ...array_values(Modules::schedulePaths()),
                ...array_values(Modules::commandPaths()),
            ])
            ->withProviders();
    }
}
