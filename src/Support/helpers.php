<?php

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

if (!function_exists('module_path')) { // @codeCoverageIgnoreStart
    /**
     * Get the path to the modules directory.
     *
     * @param  string  $path
     * @return string
     *
     * @SuppressWarnings("php:S100")
     */
    function module_path(string $path = ''): string
    {
        return Modules::modulesPath().($path ? DIRECTORY_SEPARATOR.$path : '');
    }
} // @codeCoverageIgnoreEnd
