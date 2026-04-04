<?php

namespace SineMacula\Laravel\Modules\Configuration\Enums;

/**
 * Module path enumeration.
 *
 * Defines the expected directory structure and file paths within each module
 * relative to the module root.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 */
enum ModulePath: string
{
    // The path to the modules directory relative to the base path
    case MODULES = 'modules';

    // The path to the cache file relative to the base path
    case CACHE = 'bootstrap/cache/modules.php';

    // The path to the event listeners within each module
    case LISTENERS = 'Listeners';

    // The path to the commands within each module
    case COMMANDS = 'Console/Commands';

    // The path to the resources within each module
    case RESOURCES = 'Resources';

    // The path to the views within each module
    case VIEWS = 'Resources/views';

    // The path to the translation files within each module
    case LANG = 'Resources/lang';

    // The path to the routes file within each module
    case ROUTES = 'Http/routes.php';

    // The path to the schedule file within each module
    case SCHEDULES = 'Console/schedule.php';
}
