<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SineMacula\Laravel\Modules\Configuration\Modules;
use SineMacula\Laravel\Modules\Console\Commands\ModuleCacheCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\Concerns\InteractsWithModules;
use Tests\Support\Concerns\ManagesTemporaryFiles;

/**
 * Unit tests for the ModuleCacheCommand.
 *
 * @SuppressWarnings("php:S4833")
 * @SuppressWarnings("php:S2003")
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
#[CoversClass(ModuleCacheCommand::class)]
class ModuleCacheCommandTest extends TestCase
{
    use InteractsWithModules, ManagesTemporaryFiles;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('cache_cmd_test_');
        $this->createDirectory('modules/alpha');
        $this->createDirectory('bootstrap/cache');

        $this->initModules($this->tempDir);
    }

    /**
     * Tear down the test environment.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->resetModulesState();
        $this->removeTempDirectory();

        parent::tearDown();
    }

    /**
     * Test that handle caches the modules and outputs the success message.
     *
     * @return void
     */
    public function testHandleCachesModules(): void
    {
        $app = new Application($this->tempDir);

        $command = new ModuleCacheCommand;
        $command->setLaravel($app);

        $command->run(new ArrayInput([]), new BufferedOutput);

        $cachePath = $this->tempDir
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'modules.php';

        static::assertFileExists($cachePath);

        $modules = require $cachePath;

        static::assertIsArray($modules);
        static::assertArrayHasKey('alpha', $modules);
    }
}
