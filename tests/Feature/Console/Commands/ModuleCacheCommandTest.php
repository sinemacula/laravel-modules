<?php

namespace Tests\Feature\Console\Commands;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\Laravel\Modules\Configuration\Modules;
use SineMacula\Laravel\Modules\Console\Commands\ModuleCacheCommand;
use SineMacula\Laravel\Modules\Console\Commands\ModuleClearCommand;
use Tests\Support\Concerns\InteractsWithModules;
use Tests\Support\Concerns\ManagesTemporaryFiles;
use Tests\TestCase;

/**
 * Feature tests for the module cache and clear commands.
 *
 * These tests share a single file to prevent parallel runner race conditions
 * on the shared bootstrap/cache/modules.php file.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @SuppressWarnings("php:S1192")
 * @SuppressWarnings("php:S4833")
 * @SuppressWarnings("php:S2003")
 *
 * @internal
 */
#[CoversClass(ModuleCacheCommand::class)]
#[CoversClass(ModuleClearCommand::class)]
class ModuleCacheCommandTest extends TestCase
{
    use InteractsWithModules, ManagesTemporaryFiles;

    /** @var string The path to the module cache file. */
    private string $cachePath = '';

    /**
     * Set up the test environment.
     *
     * @return void
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('cmd_test_');
        $this->createDirectory('modules/alpha');
        $this->createDirectory('bootstrap/cache');

        $this->initModules($this->tempDir);

        $this->cachePath = $this->tempDir
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'modules.php';
    }

    /**
     * Clean up the test environment.
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
     * Test that the module:cache command exits with a success code.
     *
     * @return void
     */
    public function testCacheCommandExitsSuccessfully(): void
    {
        $this->artisan('module:cache')
            ->assertExitCode(0); // @phpstan-ignore method.nonObject
    }

    /**
     * Test that the module:cache command outputs the success message.
     *
     * @return void
     */
    public function testCacheCommandOutputsSuccessMessage(): void
    {
        $this->artisan('module:cache')
            ->expectsOutputToContain('Modules cached successfully'); // @phpstan-ignore method.nonObject
    }

    /**
     * Test that the cache file is created on disk.
     *
     * @return void
     */
    public function testCacheFileCreatedOnDisk(): void
    {
        $this->artisan('module:cache');

        static::assertFileExists($this->cachePath);

        $modules = require $this->cachePath;

        static::assertIsArray($modules);
        static::assertArrayHasKey('alpha', $modules);
    }

    /**
     * Test that the module:clear command exits with a success code.
     *
     * @return void
     */
    public function testClearCommandExitsSuccessfully(): void
    {
        $this->artisan('module:cache');
        $this->artisan('module:clear')
            ->assertExitCode(0); // @phpstan-ignore method.nonObject
    }

    /**
     * Test that the module:clear command outputs the success message.
     *
     * @return void
     */
    public function testClearCommandOutputsSuccessMessage(): void
    {
        $this->artisan('module:clear')
            ->expectsOutputToContain('Cached modules cleared successfully'); // @phpstan-ignore method.nonObject
    }

    /**
     * Test that the cache file is removed from disk after clearing.
     *
     * @return void
     */
    public function testCacheFileRemovedFromDisk(): void
    {
        $this->artisan('module:cache');
        static::assertFileExists($this->cachePath);

        $this->artisan('module:clear');
        static::assertFileDoesNotExist($this->cachePath);
    }

    /**
     * Test that the clear command succeeds even when no cache file exists.
     *
     * @return void
     */
    public function testClearCommandSucceedsWithoutExistingCache(): void
    {
        static::assertFileDoesNotExist($this->cachePath);

        $this->artisan('module:clear')
            ->assertExitCode(0); // @phpstan-ignore method.nonObject
    }
}
