<?php

declare(strict_types = 1);

namespace Tests\EndToEnd;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\Laravel\Modules\Application;
use SineMacula\Laravel\Modules\Configuration\ApplicationBuilder;
use SineMacula\Laravel\Modules\Configuration\Modules;
use SineMacula\Laravel\Modules\Providers\ModuleServiceProvider;

/**
 * End-to-end tests for a boot that discovers modules from the filesystem.
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
final class ColdModuleBootTest extends ModuleIntegrationTestCase
{
    /**
     * Test that the boot ran without a module cache file present.
     *
     * @return void
     */
    public function testBootRanWithoutACacheFile(): void
    {
        self::assertFileDoesNotExist(
            $this->fixtureAppPath . '/bootstrap/cache/modules.php',
        );
    }
}
