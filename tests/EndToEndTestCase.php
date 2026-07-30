<?php

declare(strict_types = 1);

namespace Tests;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use SineMacula\Laravel\Modules\Application;
use Tests\Support\Concerns\InteractsWithModules;
use Tests\Support\Concerns\ManagesTemporaryFiles;
use Tests\Support\Concerns\StagesFixtureApplication;

/**
 * Base test case for the end-to-end tier.
 *
 * Boots a real modular application through the package's own
 * Application::configure() entry point against a staged fixture application,
 * rather than the framework application Testbench builds by default.
 *
 * The lifecycle hooks are final because module state must be prepared before
 * the application is created; a subclass that set its state up after
 * parent::setUp() would silently test nothing.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
abstract class EndToEndTestCase extends BaseTestCase
{
    use InteractsWithModules, ManagesTemporaryFiles, StagesFixtureApplication;

    /**
     * Set up the staged fixture application.
     *
     * @return void
     */
    #[\Override]
    final protected function setUp(): void
    {
        $this->stageFixtureApp($this->fixtureModules());

        $this->prepareFixtureApp();

        parent::setUp();
    }

    /**
     * Tear down the staged fixture application and all static state it touched.
     *
     * @return void
     */
    #[\Override]
    final protected function tearDown(): void
    {
        parent::tearDown();

        EventServiceProvider::setEventDiscoveryPaths([]);
        RouteServiceProvider::loadRoutesUsing(null);

        $this->resetModulesState();

        $this->removeFixtureApp();
    }

    /**
     * Return the modules the fixture application should be staged with.
     *
     * @return list<string>
     */
    protected function fixtureModules(): array
    {
        return ['Billing', 'Reporting'];
    }

    /**
     * Prepare the staged application before it is created.
     *
     * @return void
     */
    protected function prepareFixtureApp(): void {}

    /**
     * Return the base path of the staged fixture application.
     *
     * @return string
     */
    #[\Override]
    protected function getApplicationBasePath(): string
    {
        return $this->fixtureAppPath;
    }

    /**
     * Resolve the application through the package's own entry point.
     *
     * @return \Illuminate\Foundation\Application
     */
    #[\Override]
    protected function resolveApplication()
    {
        $this->resetModulesState();

        return require $this->fixtureAppPath . '/bootstrap/app.php';
    }

    /**
     * Return the booted application, narrowed to the package application type.
     *
     * @return \SineMacula\Laravel\Modules\Application
     */
    protected function modularApplication(): Application
    {
        static::assertInstanceOf(Application::class, $this->app);

        return $this->app;
    }
}
