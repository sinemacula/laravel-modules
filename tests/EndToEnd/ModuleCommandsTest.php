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
use Symfony\Component\Process\Process;
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

        $manifest = require $this->fixtureAppPath . '/bootstrap/cache/modules.php';

        self::assertIsArray($manifest);
        self::assertArrayHasKey('billing', $manifest['modules']);
        self::assertArrayHasKey('reporting', $manifest['modules']);
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

    /**
     * Test that the PHP files module:make generates parse cleanly and open with
     * the strict types declaration, so a new module does not start out in
     * breach of the coding standard.
     *
     * @return void
     */
    public function testModuleMakeGeneratesStandardsCompliantFiles(): void
    {
        $this->runFixtureArtisan(['module:make', 'Auditing']);

        $module = $this->fixtureAppPath . '/modules/Auditing';

        foreach (['/Http/routes.php', '/Console/schedule.php'] as $file) {

            $path     = $module . $file;
            $contents = (string) file_get_contents($path);

            self::assertStringStartsWith("<?php\n\ndeclare(strict_types = 1);\n", $contents);
            self::assertDoesNotMatchRegularExpression('/^use /m', $contents);

            $lint = new Process([PHP_BINARY, '-l', $path]);
            $lint->run();

            self::assertSame(0, $lint->getExitCode(), $lint->getOutput());
        }
    }
}
