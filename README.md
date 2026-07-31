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
├── Foundation/              # Default module - see "Paths and Helpers"
│   ├── Console/             # Commands and schedule
│   └── Providers/           # Service providers
└── Billing/                 # Example domain module
    ├── Console/
    │   ├── Commands/
    │   └── schedule.php
    ├── Events/
    ├── Http/
    │   ├── Controllers/
    │   ├── Requests/
    │   ├── Resources/       # Eloquent API resources - not discovered
    │   └── routes.php
    ├── Listeners/
    ├── Models/
    ├── Observers/
    ├── Policies/
    └── Resources/           # Discovered: views/ and lang/
        ├── lang/
        └── views/
```

Note the two `Resources` directories. `Http/Resources/` holds Eloquent API resource classes and is loaded by PSR-4 like
any other class. The module-root `Resources/` is the one the package discovers, and only its `views/` and `lang/`
subdirectories are read.

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

Discovery is per-path and tolerant: a module without a `Listeners/` directory simply does not appear in the listener
map. Nothing needs to exist beyond the module directory itself.

Service providers work exactly as they do in a standard Laravel app: register them in `bootstrap/providers.php`. The
package does not auto-discover module providers, so you keep full control over their registration order.

### Module Namespaces

A module is addressed by a lowercased form of its directory name. `modules/Billing/` registers the namespace `billing`,
and views and translations are referenced with the `{module}::{path}` convention:

```php
view('billing::invoices.show');
trans('billing::invoices.paid');
```

The namespace is always lowercase, whatever the casing on disk. `view('Billing::invoices.show')` fails with
`No hint path defined for [Billing].`

Classes resolve through PSR-4 instead, using the directory name exactly as it is cased. Because `modules/` takes the
place of `app/`, the `App\` prefix maps straight onto it:

| File                                                       | Class                                              |
|------------------------------------------------------------|----------------------------------------------------|
| `modules/Billing/Models/Invoice.php`                       | `App\Billing\Models\Invoice`                       |
| `modules/Billing/Http/Controllers/StatementController.php` | `App\Billing\Http\Controllers\StatementController` |

### Generating Classes

`make:` generators write into `modules/`, because `app_path()` now points there, but they still qualify a bare class
name against the application root namespace rather than a module. `make:controller ReportController` creates
`modules/Http/Controllers/ReportController.php`, and a path argument does not help - passing
`Billing/Http/Controllers/InvoiceController` creates
`modules/Http/Controllers/Billing/Http/Controllers/InvoiceController.php`.

Pass the fully qualified class name instead:

```bash
php artisan make:controller "App\Billing\Http\Controllers\StatementController"
php artisan make:model "App\Billing\Models\Invoice"
```

Those land at `modules/Billing/Http/Controllers/StatementController.php` and `modules/Billing/Models/Invoice.php`. This
applies to any generator writing under the application directory; generators targeting `database/` (migrations,
factories, seeders) are unaffected and still write to their usual location.

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
├── Console/
│   ├── Commands/
│   └── schedule.php
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── routes.php
├── Listeners/
├── Models/
└── Resources/
    ├── lang/
    └── views/
```

The `Foundation` module is scaffolded without `Resources/`. It is the default module, so a `Resources` directory
there becomes the application's `resource_path()`, and a `Resources/lang` inside it becomes `lang_path()` - which
would move your Vite root and orphan the framework's published translations. Create it by hand if that is what you
intend.

### Module Caching

Module paths are cached to `bootstrap/cache/modules.php` and integrated into Laravel's `optimize` / `optimize:clear`
lifecycle:

```bash
php artisan optimize        # Includes module:cache
php artisan optimize:clear  # Includes module:clear
```

The cache records the listing of the `modules/` directory it was built from, and is ignored once that listing no
longer matches. Laravel caches routes and events *before* any package command runs during `optimize`, so without
this a module added since the last `module:cache` would be missing from the route and event caches while the module
cache itself looked correct.

Validation covers the immediate entries of `modules/` only, not the contents of each module. Adding or removing a
module invalidates the cache; editing a file inside one does not. Any entry appearing or disappearing counts, so a
stray file such as `.DS_Store` also sends the next boot down the discovery path until `module:cache` runs again.

`module:make` does not write the cache. It creates a directory under `modules/`, which the validation above treats as a
change, so the next boot rediscovers and the new module appears without any further step.

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

`Modules::setBasePath()` must come first. The application constructor resolves module paths while it binds the
container paths, so calling it later - or not at all - fails with `ModuleException: No base path has been set.`

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

This mapping is load-bearing rather than cosmetic. `Application::path()` points `app_path()` at `modules/`, and Laravel
resolves the application namespace by matching `realpath(app_path())` against the PSR-4 entries in `composer.json`.
Without an entry that resolves to `modules/`, every `make:` command fails with `Unable to detect application namespace.`

### 3. Create the `modules/` directory

Create a `modules/` directory at your project root and add your first module:

```bash
mkdir -p modules/Foundation/Providers
```

### 4. Wire up routing

Each module defines its own routes in `Http/routes.php`. `Modules::routePaths()` discovers them for you; you wire the
result into `withRouting()` in `bootstrap/app.php` (as shown above). Routing stays explicit, exactly like standard
Laravel - the package just saves you from listing each module's route file by hand.

`routePaths()` returns a map of module name to route file, so the snippet above registers every module's routes in the
`api` group. With `apiPrefix: ''` they are served from the root, but they still receive the `api` middleware group -
stateless, with no session and no CSRF. Passing the same map to `web:` as well would register every file twice, so each
module contributes its one route file to exactly one group. Because the map is keyed by module name, it can be split
where an application needs both:

```php
$routes = Modules::routePaths();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: Arr::only($routes, ['storefront']),
        api: Arr::except($routes, ['storefront']),
    )
```

## Paths and Helpers

`modules/` replaces `app/`, so `app_path()` resolves there and `base_path()`, `storage_path()`, `config_path()` and
`database_path()` are unchanged.

A global helper resolves paths inside the modules directory:

```php
module_path();           // <base>/modules
module_path('Billing');  // <base>/modules/Billing
```

The `Modules` resolver is the public surface behind discovery. Every method resolves lazily and drops modules that do
not have the directory or file in question:

| Method                       | Returns                                                |
|------------------------------|--------------------------------------------------------|
| `Modules::getModules()`      | Every module name mapped to its absolute path          |
| `Modules::getModule($name)`  | One module's path, or `null` (the name is lowercased)  |
| `Modules::modulesPath()`     | The `modules/` directory itself                        |
| `Modules::routePaths()`      | Module name to `Http/routes.php`                       |
| `Modules::viewPaths()`       | Module name to `Resources/views/`                      |
| `Modules::langPaths()`       | Module name to `Resources/lang/`                       |
| `Modules::listenerPaths()`   | Module name to `Listeners/`                            |
| `Modules::commandPaths()`    | Module name to `Console/Commands/`                     |
| `Modules::schedulePaths()`   | Module name to `Console/schedule.php`                  |
| `Modules::defaultModule()`   | The module unprefixed resources resolve against        |

State is process-wide: the base path and every resolved map are static and shared by all callers in the process.
`Modules::setBasePath()` discards the resolved maps when the path changes, and `module:cache` / `module:clear` rebuild
them.

### The default module and `resource_path()`

`resource_path()` is module-aware. Given a `{module}::{path}` prefix it resolves inside that module's `Resources/`;
without one it uses the default module, `Foundation`:

```php
resource_path('billing::views');  // <base>/modules/Billing/Resources/views
```

This has a consequence worth knowing before you create `modules/Foundation/Resources/`. Because Laravel binds the
container paths through the same method, that directory becomes the application's resource root as soon as it exists:

| Path                      | Without `Foundation/Resources` | With it                                     |
|---------------------------|--------------------------------|---------------------------------------------|
| `resource_path()`         | `<base>/resources`             | `<base>/modules/Foundation/Resources`       |
| `config('view.paths')[0]` | `<base>/resources/views`       | `<base>/modules/Foundation/Resources/views` |

`lang_path()` follows the same rule but only once `modules/Foundation/Resources/lang/` exists, because Laravel falls
back to `<base>/lang` while that directory is absent. Creating it therefore relocates the framework's published
translations too.

That is the intended behaviour of a default module, but it moves the Vite root and `resources/js` with it, so
`module:make Foundation` deliberately does not create `Resources/`. Add it by hand when you want the application's own
assets to live inside the module.

A module prefix that does not resolve - a typo, or a module with no `Resources/` directory - falls back to the
framework path rather than raising an error.

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
