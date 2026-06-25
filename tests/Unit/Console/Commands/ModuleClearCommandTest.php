<?php

declare(strict_types = 1);

namespace Tests\Unit\Console\Commands;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SineMacula\Laravel\Modules\Configuration\Modules;
use SineMacula\Laravel\Modules\Console\Commands\ModuleClearCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\Concerns\InteractsWithModules;
use Tests\Support\Concerns\ManagesTemporaryFiles;

/**
 * Unit tests for the ModuleClearCommand.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
#[CoversClass(ModuleClearCommand::class)]
final class ModuleClearCommandTest extends TestCase
{
    use InteractsWithModules, ManagesTemporaryFiles;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('clear_cmd_test_');
        $this->createDirectory('modules/alpha');
        $this->createDirectory('bootstrap/cache');

        $this->initModules($this->tempDir);
    }

    /**
     * Tear down the test environment.
     *
     * @return void
     */
    #[\Override]
    protected function tearDown(): void
    {
        $this->resetModulesState();
        $this->removeTempDirectory();

        parent::tearDown();
    }

    /**
     * Test that handle clears the module cache.
     *
     * @return void
     */
    public function testHandleClearsCache(): void
    {
        Modules::cache();

        $cachePath = $this->tempDir
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'modules.php';

        self::assertFileExists($cachePath);

        $app = new Application($this->tempDir);

        $command = new ModuleClearCommand;
        $command->setLaravel($app);

        $exitCode = $command->run(new ArrayInput([]), new BufferedOutput);

        self::assertSame(ModuleClearCommand::SUCCESS, $exitCode);
        self::assertFileDoesNotExist($cachePath);
    }

    /**
     * Test that handle reports an error and returns FAILURE when the cache file
     * cannot be removed.
     *
     * @return void
     */
    public function testHandleReturnsFailureWhenCacheCannotBeCleared(): void
    {
        Modules::cache();

        $cacheDir = $this->tempDir
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache';

        // Remove write permission so the cache file cannot be unlinked.
        chmod($cacheDir, 0555);

        $app = new Application($this->tempDir);

        $command = new ModuleClearCommand;
        $command->setLaravel($app);

        $output = new BufferedOutput;

        try {
            $exitCode = $command->run(new ArrayInput([]), $output);
        } finally {
            chmod($cacheDir, 0755);
        }

        self::assertSame(ModuleClearCommand::FAILURE, $exitCode);
        self::assertStringContainsString('Failed to clear', $output->fetch());
    }
}
