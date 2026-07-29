<?php

declare(strict_types = 1);

namespace Tests\Unit\Console\Commands;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
final class ModuleCacheCommandTest extends TestCase
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
    #[\Override]
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
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleCachesModules(): void
    {
        $app = new Application($this->tempDir);

        $command = new ModuleCacheCommand;
        $command->setLaravel($app);

        $exitCode = $command->run(new ArrayInput([]), new BufferedOutput);

        self::assertSame(ModuleCacheCommand::SUCCESS, $exitCode);

        $cachePath = $this->tempDir
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'modules.php';

        self::assertFileExists($cachePath);

        $modules = require $cachePath;

        self::assertIsArray($modules);
        self::assertArrayHasKey('alpha', $modules);
    }

    /**
     * Test that handle reports a clean error and returns FAILURE when the cache
     * cannot be written.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleReturnsFailureWhenCacheWriteFails(): void
    {
        $app = new Application($this->tempDir);

        $command = new ModuleCacheCommand;
        $command->setLaravel($app);

        $cacheDir = $this->tempDir
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache';

        chmod($cacheDir, 0555);

        $output = new BufferedOutput;

        // Suppress the file_put_contents warning so PHPUnit sees the result.
        set_error_handler(static fn (): bool => true);

        try {
            $exitCode = $command->run(new ArrayInput([]), $output);
        } finally {
            restore_error_handler();

            // Restore permissions so tearDown can clean up.
            chmod($cacheDir, 0755);
        }

        self::assertSame(ModuleCacheCommand::FAILURE, $exitCode);
        self::assertStringContainsString('Failed to write', $output->fetch());
    }

    /**
     * Test that handle reports a clean error and returns FAILURE when the
     * modules directory cannot be read.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleReturnsFailureWhenModulesDirectoryUnreadable(): void
    {
        $modulesDir = $this->tempDir . DIRECTORY_SEPARATOR . 'modules';

        // Remove read permission so module discovery cannot open the directory.
        chmod($modulesDir, 0000);

        $app = new Application($this->tempDir);

        $command = new ModuleCacheCommand;
        $command->setLaravel($app);

        $output = new BufferedOutput;

        try {
            $exitCode = $command->run(new ArrayInput([]), $output);
        } finally {
            chmod($modulesDir, 0755);
        }

        self::assertSame(ModuleCacheCommand::FAILURE, $exitCode);
        self::assertStringContainsString('Failed to read the modules directory', $output->fetch());
    }
}
