<?php

declare(strict_types = 1);

namespace Tests\EndToEnd;

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\Laravel\Modules\Configuration\ModuleManifest;
use SineMacula\Laravel\Modules\Configuration\Modules;
use Tests\EndToEndTestCase;

/**
 * End-to-end regression tests for optimizing after the module set has changed.
 *
 * Laravel caches routes and events before any package optimization command
 * runs, so a manifest written for an earlier module set would be used to build
 * both caches and the newly deployed module would serve nothing.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
#[CoversClass(ModuleManifest::class)]
#[CoversClass(Modules::class)]
final class OptimizeCachesCurrentModuleSetTest extends EndToEndTestCase
{
    /**
     * Test that a module deployed after the manifest was written is routable
     * following a single optimize run.
     *
     * @return void
     */
    public function testOptimizeRoutesAModuleAddedAfterCaching(): void
    {
        $this->stageModule('Reporting');

        $optimize = $this->runFixtureArtisan(['optimize']);

        self::assertSame(
            0,
            $optimize->getExitCode(),
            $optimize->getOutput() . $optimize->getErrorOutput(),
        );

        $routes = $this->runFixtureArtisan(['route:list']);

        self::assertStringContainsString('billing/invoice', $routes->getOutput());
        self::assertStringContainsString('reporting/summary', $routes->getOutput());
    }

    /**
     * Test that a module deployed after the manifest was written has its
     * listeners registered following a single optimize run.
     *
     * @return void
     */
    public function testOptimizeDiscoversListenersOfAModuleAddedAfterCaching(): void
    {
        $this->stageModule('Reporting');

        $this->runFixtureArtisan(['optimize']);

        $events = require $this->fixtureAppPath . '/bootstrap/cache/events.php';

        $discovered = $events[EventServiceProvider::class] ?? [];

        self::assertArrayHasKey('App\Billing\Events\InvoicePaid', $discovered);
        self::assertArrayHasKey('App\Reporting\Events\ReportRequested', $discovered);
    }

    /**
     * Test that the manifest itself is rewritten to include the new module.
     *
     * @return void
     */
    public function testOptimizeRewritesTheManifest(): void
    {
        $this->stageModule('Reporting');

        $this->runFixtureArtisan(['optimize']);

        $manifest = require $this->fixtureAppPath . '/bootstrap/cache/modules.php';

        self::assertArrayHasKey('billing', $manifest['modules']);
        self::assertArrayHasKey('reporting', $manifest['modules']);
    }

    /**
     * Stage only the billing module so a second one can be deployed later.
     *
     * @return list<string>
     */
    #[\Override]
    protected function fixtureModules(): array
    {
        return ['Billing'];
    }

    /**
     * Write a manifest describing the billing module alone.
     *
     * @return void
     */
    #[\Override]
    protected function prepareFixtureApp(): void
    {
        $process = $this->runFixtureArtisan(['module:cache']);

        self::assertSame(
            0,
            $process->getExitCode(),
            $process->getOutput() . $process->getErrorOutput(),
        );
    }
}
