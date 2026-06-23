<?php

declare(strict_types = 1);

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
 * @phpstan-ignore class.childType (test double subclasses the service provider to record protected calls)
 */
final class SpyModuleServiceProvider extends ModuleServiceProvider
{
    /** @var list<array{string, string}> */
    public array $loadViewsFromCalls = [];

    /** @var list<array{string, string}> */
    public array $loadTranslationsFromCalls = [];

    /** @var list<array{string|null, string|null, string|null}> */
    public array $optimizesCalls = [];

    /**
     * Record the loadViewsFrom call.
     *
     * @param  mixed  $path
     * @param  mixed  $namespace
     * @return void
     */
    #[\Override]
    protected function loadViewsFrom(mixed $path, mixed $namespace): void
    {
        assert(is_string($path) && is_string($namespace));

        $this->loadViewsFromCalls[] = [$path, $namespace];
    }

    /**
     * Record the loadTranslationsFrom call.
     *
     * @param  mixed  $path
     * @param  mixed  $namespace
     * @return void
     */
    #[\Override]
    protected function loadTranslationsFrom(mixed $path, mixed $namespace = null): void
    {
        assert(is_string($path) && is_string($namespace));

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
    #[\Override]
    protected function optimizes(?string $optimize = null, ?string $clear = null, ?string $key = null): void
    {
        $this->optimizesCalls[] = [$optimize, $clear, $key];
    }
}
