# Laravel Modules

[![Latest Stable Version](https://img.shields.io/packagist/v/sinemacula/laravel-modules.svg)](https://packagist.org/packages/sinemacula/laravel-modules)
[![Build Status](https://github.com/sinemacula/laravel-modules/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/sinemacula/laravel-modules/actions/workflows/tests.yml)
[![Quality Gates](https://github.com/sinemacula/laravel-modules/actions/workflows/quality-gates.yml/badge.svg?branch=master)](https://github.com/sinemacula/laravel-modules/actions/workflows/quality-gates.yml)
[![Maintainability](https://qlty.sh/gh/sinemacula/projects/laravel-modules/maintainability.svg)](https://qlty.sh/gh/sinemacula/projects/laravel-modules)
[![Code Coverage](https://qlty.sh/gh/sinemacula/projects/laravel-modules/coverage.svg)](https://qlty.sh/gh/sinemacula/projects/laravel-modules)
[![Total Downloads](https://img.shields.io/packagist/dt/sinemacula/laravel-modules.svg)](https://packagist.org/packages/sinemacula/laravel-modules)

A lightweight, convention-driven modular architecture package for Laravel. Replaces the standard `app/` directory with a
`modules/` directory where each subdirectory is a self-contained module following standard Laravel conventions.

Modules are auto-discovered at boot time and cached for performance. All standard Laravel conventions work inside each
module - there is no new API to learn.

## How It Works

Each subdirectory under `modules/` is a self-contained module with its own models, controllers, routes, commands,
listeners, events, observers, policies, and more:

```text
modules/
├── Foundation/              # Core framework module
│   ├── Console/             # Commands and schedule
│   └── Providers/           # Service providers
└── Billing/                 # Example domain module
    ├── Events/
    ├── Http/
    │   ├── Controllers/
    │   ├── Requests/
    │   ├── Resources/
    │   └── routes.php
    ├── Listeners/
    ├── Models/
    ├── Observers/
    └── Policies/
```

### What Gets Discovered

| Convention       | Module Path            | How It's Loaded                                  |
|------------------|------------------------|--------------------------------------------------|
| Console commands | `Console/Commands/`    | Auto-registered via `withCommands()`             |
| Scheduled tasks  | `Console/schedule.php` | Auto-registered via `withCommands()`             |
| Event listeners  | `Listeners/`           | Auto-registered via `withEvents()`               |
| Views            | `Resources/views/`     | Auto-registered in `ModuleServiceProvider`       |
| Translations     | `Resources/lang/`      | Auto-registered in `ModuleServiceProvider`       |
| Routes           | `Http/routes.php`      | Discovered; you wire them in `bootstrap/app.php` |

Everything else - controllers, requests, resources, events, observers, policies, models, jobs, mail, notifications -
works via PSR-4 autoloading. No registration required.

Service providers work exactly as they do in a standard Laravel app: register them in `bootstrap/providers.php`. The
package does not auto-discover module providers, so you keep full control over their registration order.

### Artisan Commands

| Command              | Description                                                 |
|----------------------|-------------------------------------------------------------|
| `module:make {name}` | Scaffold a new module with the standard directory structure |
| `module:list`        | List all discovered modules and their paths                 |
| `module:cache`       | Cache discovered module paths for faster resolution         |
| `module:clear`       | Clear the cached module paths                               |

`module:make Billing` creates:

```text
modules/Billing/
├── Console/Commands/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── routes.php
├── Listeners/
└── Models/
```

### Module Caching

Module paths are cached to `bootstrap/cache/modules.php` and integrated into Laravel's `optimize` / `optimize:clear`
lifecycle:

```bash
php artisan optimize        # Includes module:cache
php artisan optimize:clear  # Includes module:clear
```

## Installation

```bash
composer require sinemacula/laravel-modules
```

### 1. Edit `bootstrap/app.php`

Replace the default Laravel application with the modular variant:

```php
<?php

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use SineMacula\Laravel\Modules\Application;
use SineMacula\Laravel\Modules\Configuration\Modules;

Modules::setBasePath(dirname(__DIR__));

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api      : Modules::routePaths(),
        health   : '/health',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {})
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
```

### 2. Update your autoload mapping

In your application's `composer.json`, point the PSR-4 autoload at the `modules/` directory:

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "modules/"
        }
    }
}
```

Then run `composer dump-autoload`.

### 3. Create the `modules/` directory

Create a `modules/` directory at your project root and add your first module:

```bash
mkdir -p modules/Foundation/Providers
```

### 4. Wire up routing

Each module defines its own routes in `Http/routes.php`. `Modules::routePaths()` discovers them for you; you wire the
result into `withRouting()` in `bootstrap/app.php` (as shown above). Routing stays explicit, exactly like standard
Laravel - the package just saves you from listing each module's route file by hand.

## Requirements

- PHP ^8.3
- Laravel ^12.0 || ^13.0

## Testing

```bash
composer test                # PHPUnit suite in parallel via Paratest
composer test:coverage       # suite with Clover coverage output
composer test:mutation       # Infection mutation gate (min MSI 90)
composer test:mutation:full  # full mutation suite without thresholds
composer check               # static analysis and lint via qlty
composer format              # format via qlty
composer smells              # duplication / complexity smells via qlty
composer bench               # PHPBench suite for the hot paths
composer bench:ci            # PHPBench with CI artifact dump
composer bench:smoke         # single-rev pass to verify every subject runs
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a list of notable changes.

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on branching, commits, code
quality, and pull requests.

## Security

If you discover a security vulnerability, please report it responsibly. See [SECURITY.md](SECURITY.md) for the
disclosure policy and contact details.

## License

Licensed under the [Apache License, Version 2.0](https://www.apache.org/licenses/LICENSE-2.0).
