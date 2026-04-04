<?php

namespace Tests\Unit\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SineMacula\Laravel\Modules\Configuration\Modules;
use SineMacula\Laravel\Modules\Console\Commands\ModuleCacheCommand;
use SineMacula\Laravel\Modules\Console\Commands\ModuleClearCommand;
use SineMacula\Laravel\Modules\Console\Commands\ModuleListCommand;
use SineMacula\Laravel\Modules\Console\Commands\ModuleMakeCommand;
use SineMacula\Laravel\Modules\Providers\ModuleServiceProvider;
use Tests\Support\Concerns\InteractsWithModules;
use Tests\Support\Concerns\ManagesTemporaryFiles;
use Tests\Support\Spies\SpyModuleServiceProvider;

/**
 * Unit tests for the ModuleServiceProvider class.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @SuppressWarnings("php:S1192")
 *
 * @internal
 */
#[CoversClass(ModuleServiceProvider::class)]
class ModuleServiceProviderTest extends TestCase
{
    use InteractsWithModules, ManagesTemporaryFiles, MockeryPHPUnitIntegration;

    /**
     * Set up the test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('module_sp_test_');

        $this->resetModulesState();
    }

    /**
     * Tear down the test fixtures.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->resetModulesState();
        $this->removeTempDirectory();

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);

        parent::tearDown();
    }

    /**
     * Test that boot registers views from Modules::viewPaths().
     *
     * @return void
     */
    public function testBootRegistersViews(): void
    {
        $this->createModuleStructure(['alpha' => ['Resources/views']]);

        Modules::setBasePath($this->tempDir);

        $viewPaths = Modules::viewPaths();

        $provider = $this->createSpyProvider();

        $provider->boot();

        static::assertContains(
            [$viewPaths['alpha'], 'alpha'],
            $provider->loadViewsFromCalls,
        );
    }

    /**
     * Test that boot registers translations from Modules::langPaths().
     *
     * @return void
     */
    public function testBootRegistersTranslations(): void
    {
        $this->createModuleStructure(['alpha' => ['Resources/lang']]);

        Modules::setBasePath($this->tempDir);

        $langPaths = Modules::langPaths();

        $provider = $this->createSpyProvider();

        $provider->boot();

        static::assertContains(
            [$langPaths['alpha'], 'alpha'],
            $provider->loadTranslationsFromCalls,
        );
    }

    /**
     * Test that boot registers optimization commands with the correct
     * arguments.
     *
     * @return void
     */
    public function testBootRegistersOptimizationCommands(): void
    {
        $this->createModuleStructure([]);

        Modules::setBasePath($this->tempDir);

        $provider = $this->createSpyProvider();

        $provider->boot();

        static::assertCount(1, $provider->optimizesCalls);
        static::assertSame(
            ['module:cache', 'module:clear', 'modules'],
            $provider->optimizesCalls[0],
        );
    }

    /**
     * Test that loadViewsFrom receives arguments in the correct order: path
     * first, module name second.
     *
     * @return void
     */
    public function testViewsLoadedWithCorrectArguments(): void
    {
        $this->createModuleStructure(['beta' => ['Resources/views']]);

        Modules::setBasePath($this->tempDir);

        $viewPaths = Modules::viewPaths();

        $provider = $this->createSpyProvider();

        $provider->boot();

        $call = $provider->loadViewsFromCalls[0];

        static::assertSame($viewPaths['beta'], $call[0]);
        static::assertSame('beta', $call[1]);
    }

    /**
     * Test that loadTranslationsFrom receives arguments in the correct order:
     * path first, module name second.
     *
     * @return void
     */
    public function testTranslationsLoadedWithCorrectArguments(): void
    {
        $this->createModuleStructure(['beta' => ['Resources/lang']]);

        Modules::setBasePath($this->tempDir);

        $langPaths = Modules::langPaths();

        $provider = $this->createSpyProvider();

        $provider->boot();

        $call = $provider->loadTranslationsFromCalls[0];

        static::assertSame($langPaths['beta'], $call[0]);
        static::assertSame('beta', $call[1]);
    }

    /**
     * Test that no calls to loadViewsFrom are made when Modules::viewPaths()
     * returns an empty array.
     *
     * @return void
     */
    public function testHandlesNoViewPaths(): void
    {
        $this->createModuleStructure([]);

        Modules::setBasePath($this->tempDir);

        $provider = $this->createSpyProvider();

        $provider->boot();

        static::assertEmpty($provider->loadViewsFromCalls);
    }

    /**
     * Test that no calls to loadTranslationsFrom are made when
     * Modules::langPaths() returns an empty array.
     *
     * @return void
     */
    public function testHandlesNoLangPaths(): void
    {
        $this->createModuleStructure([]);

        Modules::setBasePath($this->tempDir);

        $provider = $this->createSpyProvider();

        $provider->boot();

        static::assertEmpty($provider->loadTranslationsFromCalls);
    }

    /**
     * Test that multiple module view paths are each registered via
     * loadViewsFrom.
     *
     * @return void
     */
    public function testRegistersMultipleModuleViews(): void
    {
        $this->createModuleStructure([
            'alpha' => ['Resources/views'],
            'beta'  => ['Resources/views'],
        ]);

        Modules::setBasePath($this->tempDir);

        $provider = $this->createSpyProvider();

        $provider->boot();

        static::assertCount(2, $provider->loadViewsFromCalls);

        $modules = array_column(
            $provider->loadViewsFromCalls,
            1,
        );

        static::assertContains('alpha', $modules);
        static::assertContains('beta', $modules);
    }

    /**
     * Test that multiple module translation paths are each registered via
     * loadTranslationsFrom.
     *
     * @return void
     */
    public function testRegistersMultipleModuleTranslations(): void
    {
        $this->createModuleStructure([
            'alpha' => ['Resources/lang'],
            'beta'  => ['Resources/lang'],
        ]);

        Modules::setBasePath($this->tempDir);

        $provider = $this->createSpyProvider();

        $provider->boot();

        static::assertCount(2, $provider->loadTranslationsFromCalls);

        $modules = array_column(
            $provider->loadTranslationsFromCalls,
            1,
        );

        static::assertContains('alpha', $modules);
        static::assertContains('beta', $modules);
    }

    /**
     * Test that register calls registerCommands to bind all module commands.
     *
     * @return void
     */
    public function testRegisterBindsCommands(): void
    {
        $this->createModuleStructure([]);

        Modules::setBasePath($this->tempDir);

        $app = new \Illuminate\Foundation\Application($this->tempDir);

        $provider = new ModuleServiceProvider($app);

        $provider->register();

        $resolved = $app->make(
            ModuleCacheCommand::class,
        );

        static::assertInstanceOf(
            ModuleCacheCommand::class,
            $resolved,
        );
    }

    /**
     * Test that register binds all four module commands.
     *
     * @return void
     */
    public function testRegisterBindsAllFourCommands(): void
    {
        $this->createModuleStructure([]);

        Modules::setBasePath($this->tempDir);

        $app = new \Illuminate\Foundation\Application($this->tempDir);

        $provider = new ModuleServiceProvider($app);

        $provider->register();

        static::assertInstanceOf(
            ModuleCacheCommand::class,
            $app->make(ModuleCacheCommand::class),
        );
        static::assertInstanceOf(
            ModuleClearCommand::class,
            $app->make(ModuleClearCommand::class),
        );
        static::assertInstanceOf(
            ModuleListCommand::class,
            $app->make(ModuleListCommand::class),
        );
        static::assertInstanceOf(
            ModuleMakeCommand::class,
            $app->make(ModuleMakeCommand::class),
        );
    }

    /**
     * Create a spy provider that tracks calls to protected methods.
     *
     * @return \Tests\Support\Spies\SpyModuleServiceProvider
     */
    private function createSpyProvider(): SpyModuleServiceProvider
    {
        /** @var \Illuminate\Contracts\Foundation\Application&\Mockery\MockInterface $app */
        $app = \Mockery::mock(Application::class);

        return new SpyModuleServiceProvider($app); // @phpstan-ignore argument.type
    }
}
