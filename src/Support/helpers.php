<?php

declare(strict_types = 1);

/*
|-------------------------------------------------------------------------------
| Global Helper Functions
|-------------------------------------------------------------------------------
|
| Functions defined here are auto-loaded via the composer.json files directive
| and available throughout the application.
|
*/

use SineMacula\Laravel\Modules\Configuration\Modules;

// Composer loads this file before coverage collection starts, so the guard and
// the declaration can never be recorded; only the body is measurable.
// @codeCoverageIgnoreStart
if (!function_exists('module_path')) {
    /**
     * Get the path to the modules directory.
     *
     * @param  string  $path
     * @return string
     *
     * @SuppressWarnings("php:S100")
     */
    function module_path(string $path = ''): string // phpcs:ignore Squiz.NamingConventions.ValidFunctionName.NotCamelCaps
    {
        // @codeCoverageIgnoreEnd
        return Modules::modulesPath() . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}
