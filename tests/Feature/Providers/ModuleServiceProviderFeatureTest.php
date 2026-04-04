<?php

namespace Tests\Feature\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\Laravel\Modules\Providers\ModuleServiceProvider;
use Tests\Support\Concerns\InteractsWithModules;
use Tests\Support\Concerns\ManagesTemporaryFiles;
use Tests\TestCase;

/**
 * Feature tests for the ModuleServiceProvider.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @SuppressWarnings("php:S1192")
 *
 * @internal
 */
#[CoversClass(ModuleServiceProvider::class)]
class ModuleServiceProviderFeatureTest extends TestCase
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

        $this->createTempDirectory('sp_feature_test_');
        $this->createDirectory('modules');
        $this->createDirectory('bootstrap/cache');

        $this->initModules($this->tempDir);
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
     * Test that the module:cache and module:clear commands are registered.
     *
     * @return void
     */
    public function testOptimizationCommandsRegistered(): void
    {
        $this->artisan('module:cache')
            ->assertExitCode(0); // @phpstan-ignore method.nonObject

        $this->artisan('module:clear')
            ->assertExitCode(0); // @phpstan-ignore method.nonObject
    }

    /**
     * Test that the module:cache command is included in the optimize command
     * list.
     *
     * @return void
     */
    public function testModuleCacheCommandInOptimizeList(): void
    {
        static::assertArrayHasKey(
            'modules',
            ServiceProvider::$optimizeCommands,
        );

        static::assertSame(
            'module:cache',
            ServiceProvider::$optimizeCommands['modules'],
        );
    }

    /**
     * Test that the module:list command is registered.
     *
     * @return void
     */
    public function testModuleListCommandRegistered(): void
    {
        $this->artisan('module:list')
            ->assertExitCode(0); // @phpstan-ignore method.nonObject
    }

    /**
     * Test that the module:make command is registered.
     *
     * @return void
     */
    public function testModuleMakeCommandRegistered(): void
    {
        $this->artisan('module:make', ['name' => 'TestModule'])
            ->assertExitCode(0); // @phpstan-ignore method.nonObject
    }

    /**
     * Test that all four module commands are registered via the service
     * provider.
     *
     * @return void
     */
    public function testAllCommandsRegistered(): void
    {
        $commands = array_keys(Artisan::all());

        static::assertContains('module:cache', $commands);
        static::assertContains('module:clear', $commands);
        static::assertContains('module:list', $commands);
        static::assertContains('module:make', $commands);
    }
}
