<?php

declare(strict_types = 1);

namespace Tests\EndToEnd;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\Laravel\Modules\Application;
use SineMacula\Laravel\Modules\Configuration\ApplicationBuilder;
use SineMacula\Laravel\Modules\Configuration\Modules;
use SineMacula\Laravel\Modules\Providers\ModuleServiceProvider;

/**
 * End-to-end tests for a boot served from the module cache file.
 *
 * Inherits the same matrix as the cold boot so a divergence between discovery
 * and the cache fails on one side only.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
#[CoversClass(Application::class)]
#[CoversClass(ApplicationBuilder::class)]
#[CoversClass(Modules::class)]
#[CoversClass(ModuleServiceProvider::class)]
final class CachedModuleBootTest extends ModuleIntegrationTestCase
{
    /**
     * Test that the boot was served from the module cache file.
     *
     * @return void
     */
    public function testBootWasServedFromTheCacheFile(): void
    {
        self::assertFileExists(
            $this->fixtureAppPath . '/bootstrap/cache/modules.php',
        );

        $manifest = require $this->fixtureAppPath . '/bootstrap/cache/modules.php';

        self::assertIsArray($manifest);
        self::assertArrayHasKey('billing', $manifest['modules']);
        self::assertArrayHasKey('reporting', $manifest['modules']);
    }

    /**
     * Write the module cache before the application is created.
     *
     * @return void
     */
    #[\Override]
    protected function prepareFixtureApp(): void
    {
        Modules::setBasePath($this->fixtureAppPath);
        Modules::cache();

        $this->resetModulesState();
    }
}
