<?php

declare(strict_types = 1);

namespace Tests\EndToEnd;

use Illuminate\Contracts\Console\Kernel;
use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\Laravel\Modules\Console\Commands\ModuleCacheCommand;
use SineMacula\Laravel\Modules\Console\Commands\ModuleClearCommand;
use SineMacula\Laravel\Modules\Console\Commands\ModuleListCommand;
use SineMacula\Laravel\Modules\Console\Commands\ModuleMakeCommand;
use SineMacula\Laravel\Modules\Providers\ModuleServiceProvider;
use Tests\EndToEndTestCase;

/**
 * End-to-end tests for the package console commands against a real modular
 * application.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
#[CoversClass(ModuleCacheCommand::class)]
#[CoversClass(ModuleClearCommand::class)]
#[CoversClass(ModuleListCommand::class)]
#[CoversClass(ModuleMakeCommand::class)]
#[CoversClass(ModuleServiceProvider::class)]
final class ModuleCommandsTest extends EndToEndTestCase
{
    /**
     * Test that the service provider registers every package command on the
     * booted application.
     *
     * @return void
     */
    public function testPackageCommandsAreRegistered(): void
    {
        $commands = array_keys($this->modularApplication()->make(Kernel::class)->all());

        self::assertContains('module:cache', $commands);
        self::assertContains('module:clear', $commands);
        self::assertContains('module:list', $commands);
        self::assertContains('module:make', $commands);
    }

    /**
     * Test that module:list reports every discovered module.
     *
     * @return void
     */
    public function testModuleListReportsDiscoveredModules(): void
    {
        $process = $this->runFixtureArtisan(['module:list']);

        self::assertSame(0, $process->getExitCode());
        self::assertStringContainsString('billing', $process->getOutput());
        self::assertStringContainsString('reporting', $process->getOutput());
    }

    /**
     * Test that module:cache writes a cache containing every module.
     *
     * @return void
     */
    public function testModuleCacheWritesEveryModule(): void
    {
        $process = $this->runFixtureArtisan(['module:cache']);

        self::assertSame(0, $process->getExitCode());

        $cached = require $this->fixtureAppPath . '/bootstrap/cache/modules.php';

        self::assertIsArray($cached);
        self::assertArrayHasKey('billing', $cached);
        self::assertArrayHasKey('reporting', $cached);
    }

    /**
     * Test that module:clear removes an existing cache file.
     *
     * @return void
     */
    public function testModuleClearRemovesTheCacheFile(): void
    {
        $this->runFixtureArtisan(['module:cache']);

        self::assertFileExists($this->fixtureAppPath . '/bootstrap/cache/modules.php');

        $process = $this->runFixtureArtisan(['module:clear']);

        self::assertSame(0, $process->getExitCode());
        self::assertFileDoesNotExist($this->fixtureAppPath . '/bootstrap/cache/modules.php');
    }

    /**
     * Test that module:make scaffolds a new module inside the modules
     * directory.
     *
     * @return void
     */
    public function testModuleMakeScaffoldsANewModule(): void
    {
        $process = $this->runFixtureArtisan(['module:make', 'Auditing']);

        self::assertSame(0, $process->getExitCode());

        $module = $this->fixtureAppPath . '/modules/Auditing';

        self::assertDirectoryExists($module . '/Listeners');
        self::assertFileExists($module . '/Http/routes.php');
    }
}
