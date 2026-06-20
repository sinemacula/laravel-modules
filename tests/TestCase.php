<?php

declare(strict_types = 1);

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use SineMacula\Laravel\Modules\Providers\ModuleServiceProvider;

/**
 * Base test case for feature and integration tests.
 *
 * Bootstraps a Laravel application via Orchestra Testbench with the
 * ModuleServiceProvider registered.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Get the package providers.
     *
     * @param  mixed  $app
     * @return list<class-string<\Illuminate\Support\ServiceProvider>>
     */
    #[\Override]
    protected function getPackageProviders(mixed $app): array
    {
        return [
            ModuleServiceProvider::class,
        ];
    }
}
