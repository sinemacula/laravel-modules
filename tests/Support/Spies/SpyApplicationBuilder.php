<?php

namespace Tests\Support\Spies;

use SineMacula\Laravel\Modules\Configuration\ApplicationBuilder;

/**
 * Spy subclass that captures arguments passed to the builder chain methods
 * without invoking real Laravel service registration.
 *
 * @internal
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @phpstan-ignore class.childType
 */
class SpyApplicationBuilder extends ApplicationBuilder
{
    /** @var array<int, string> Captured event discovery paths. */
    public array $capturedEvents = [];

    /** @var array<int, string> Captured command paths. */
    public array $capturedCommands = [];

    /** @var bool Whether withKernels was called. */
    public bool $withKernelsCalled = false;

    /** @var bool Whether withProviders was called. */
    public bool $withProvidersCalled = false;

    /**
     * Record that withKernels was called.
     *
     * @return static
     */
    public function withKernels(): static // @phpstan-ignore method.childReturnType
    {
        $this->withKernelsCalled = true;

        return $this;
    }

    /**
     * Capture event discovery paths.
     *
     * @param  bool|iterable<string>  $discover
     * @return static
     */
    public function withEvents(bool|iterable $discover = true): static // @phpstan-ignore method.childReturnType
    {
        if (is_iterable($discover)) {
            $this->capturedEvents = [...$discover];
        }

        return $this;
    }

    /**
     * Capture command paths.
     *
     * @param  array<int, string>  $commands
     * @return static
     */
    public function withCommands(array $commands = []): static // @phpstan-ignore method.childReturnType, method.childParameterType
    {
        $this->capturedCommands = $commands;

        return $this;
    }

    /**
     * Record that withProviders was called.
     *
     * @param  array<int, string>  $providers
     * @param  bool  $withBootstrapProviders
     * @return static
     */
    public function withProviders(array $providers = [], bool $withBootstrapProviders = true): static // @phpstan-ignore method.childReturnType, method.childParameterType
    {
        $this->withProvidersCalled = true;

        return $this;
    }
}
