<?php

declare(strict_types = 1);

namespace SineMacula\Laravel\Modules\Configuration;

use Illuminate\Foundation\Configuration\ApplicationBuilder as BaseApplicationBuilder;

/**
 * Build the configuration for the modularised Laravel application.
 *
 * @inheritable Subclassed by the test spy to capture registration calls.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 */
class ApplicationBuilder extends BaseApplicationBuilder
{
    /**
     * Register module-aware services for the application.
     *
     * Registers the event listener, console command and schedule paths each
     * module resolves to, then loads the application's configured service
     * providers.
     *
     * @return static
     *
     * @throws \SineMacula\Laravel\Modules\Exceptions\ModuleException
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
