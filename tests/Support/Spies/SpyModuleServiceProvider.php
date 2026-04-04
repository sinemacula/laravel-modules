<?php

namespace Tests\Support\Spies;

use SineMacula\Laravel\Modules\Providers\ModuleServiceProvider;

/**
 * Spy subclass that records calls to protected ServiceProvider methods rather
 * than invoking the real implementations.
 *
 * @internal
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @phpstan-ignore class.childType
 */
class SpyModuleServiceProvider extends ModuleServiceProvider
{
    /** @var list<array{mixed, mixed}> */
    public array $loadViewsFromCalls = [];

    /** @var list<array{mixed, mixed}> */
    public array $loadTranslationsFromCalls = [];

    /** @var list<array{mixed, mixed, mixed}> */
    public array $optimizesCalls = [];

    /**
     * Record the loadViewsFrom call.
     *
     * @param  mixed  $path
     * @param  mixed  $namespace
     * @return void
     */
    protected function loadViewsFrom(mixed $path, mixed $namespace): void
    {
        $this->loadViewsFromCalls[] = [$path, $namespace];
    }

    /**
     * Record the loadTranslationsFrom call.
     *
     * @param  mixed  $path
     * @param  mixed  $namespace
     * @return void
     */
    protected function loadTranslationsFrom(mixed $path, mixed $namespace = null): void
    {
        $this->loadTranslationsFromCalls[] = [$path, $namespace];
    }

    /**
     * Record the optimizes call.
     *
     * @param  string|null  $optimize
     * @param  string|null  $clear
     * @param  string|null  $key
     * @return void
     */
    protected function optimizes(?string $optimize = null, ?string $clear = null, ?string $key = null): void
    {
        $this->optimizesCalls[] = [$optimize, $clear, $key];
    }
}
