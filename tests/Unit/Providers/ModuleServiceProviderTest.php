<?php

declare(strict_types = 1);

namespace Tests\Unit\Providers;

use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Application as FoundationApplication;
use Illuminate\Support\Facades\Facade;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
final class ModuleServiceProviderTest extends TestCase
{
    use InteractsWithModules, ManagesTemporaryFiles, MockeryPHPUnitIntegration;

    /**
     * Set up the test fixtures.
     *
     * @return void
     */
    #[\Override]
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
    #[\Override]
    protected function tearDown(): void
    {
        $this->resetModulesState();
        $this->removeTempDirectory();

        Factory::flushState();

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

        self::assertContains(
            [$viewPaths['alpha'], 'alpha'],
            $provider->loadViewsFromCalls,
        );
    }

    /**
     * Test that boot installs the module factory resolver, so a module model
     * reaches its factory without per-model wiring.
     *
     * @return void
     */
    public function testBootRegistersTheFactoryResolver(): void
    {
        // The application derives its namespace by reading composer.json.
        $this->createFile('composer.json', <<<'JSON'
            {"autoload": {"psr-4": {"App\\": "modules/"}}}
            JSON);

        Modules::setBasePath($this->tempDir);

        self::assertSame(
            'Database\Factories\Billing\Models\InvoiceFactory',
            Factory::resolveFactoryName('App\Billing\Models\Invoice'),
        );

        $this->createSpyProvider()->boot();

        self::assertSame(
            'Database\Factories\Billing\InvoiceFactory',
            Factory::resolveFactoryName('App\Billing\Models\Invoice'),
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

        self::assertContains(
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

        self::assertCount(1, $provider->optimizesCalls);
        self::assertSame(
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

        self::assertSame($viewPaths['beta'], $call[0]);
        self::assertSame('beta', $call[1]);
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

        self::assertSame($langPaths['beta'], $call[0]);
        self::assertSame('beta', $call[1]);
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

        self::assertEmpty($provider->loadViewsFromCalls);
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

        self::assertEmpty($provider->loadTranslationsFromCalls);
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

        self::assertCount(2, $provider->loadViewsFromCalls);

        $modules = array_column(
            $provider->loadViewsFromCalls,
            1,
        );

        self::assertContains('alpha', $modules);
        self::assertContains('beta', $modules);
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

        self::assertCount(2, $provider->loadTranslationsFromCalls);

        $modules = array_column(
            $provider->loadTranslationsFromCalls,
            1,
        );

        self::assertContains('alpha', $modules);
        self::assertContains('beta', $modules);
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

        $app = $this->createApplication(new ConfigRepository);

        $provider = new ModuleServiceProvider($app);

        $provider->register();

        $resolved = $app->make(
            ModuleCacheCommand::class,
        );

        self::assertInstanceOf(
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

        $app = $this->createApplication(new ConfigRepository);

        $provider = new ModuleServiceProvider($app);

        $provider->register();

        self::assertInstanceOf(
            ModuleCacheCommand::class,
            $app->make(ModuleCacheCommand::class),
        );
        self::assertInstanceOf(
            ModuleClearCommand::class,
            $app->make(ModuleClearCommand::class),
        );
        self::assertInstanceOf(
            ModuleListCommand::class,
            $app->make(ModuleListCommand::class),
        );
        self::assertInstanceOf(
            ModuleMakeCommand::class,
            $app->make(ModuleMakeCommand::class),
        );
    }

    /**
     * Test that register sets the module base path from the application, so
     * subsequent path resolution targets the application root.
     *
     * @return void
     */
    #[RunInSeparateProcess]
    public function testRegisterSetsBasePathFromApplication(): void
    {
        $app = $this->createApplication(new ConfigRepository);

        $provider = new ModuleServiceProvider($app);

        $provider->register();

        self::assertSame(
            $this->tempDir . DIRECTORY_SEPARATOR . 'modules',
            Modules::modulesPath(),
        );
    }

    /**
     * Test that register drops a configured view path whose directory does not
     * exist, and reindexes the paths that survive.
     *
     * @return void
     */
    public function testRegisterDropsViewPathsThatDoNotExist(): void
    {
        $this->createDirectory('first');
        $this->createDirectory('last');

        $first   = $this->tempDir . DIRECTORY_SEPARATOR . 'first';
        $last    = $this->tempDir . DIRECTORY_SEPARATOR . 'last';
        $missing = $this->tempDir . DIRECTORY_SEPARATOR . 'missing';

        $config = new ConfigRepository(['view' => ['paths' => [$first, $missing, $last]]]);

        $provider = new ModuleServiceProvider($this->createApplication($config));

        $provider->register();

        self::assertSame([$first, $last], $config->get('view.paths'));
    }

    /**
     * Test that register drops a configured view path that is not a string,
     * which would otherwise fail the directory check outright.
     *
     * @return void
     */
    public function testRegisterDropsViewPathsThatAreNotStrings(): void
    {
        $this->createDirectory('first');

        $first = $this->tempDir . DIRECTORY_SEPARATOR . 'first';

        $config = new ConfigRepository(['view' => ['paths' => [$first, 123]]]);

        $provider = new ModuleServiceProvider($this->createApplication($config));

        $provider->register();

        self::assertSame([$first], $config->get('view.paths'));
    }

    /**
     * Test that register leaves an application with no configured view paths
     * holding an empty list rather than failing.
     *
     * @return void
     */
    public function testRegisterEmptiesTheViewPathsWhenNoneAreConfigured(): void
    {
        $config = new ConfigRepository;

        $provider = new ModuleServiceProvider($this->createApplication($config));

        $provider->register();

        self::assertSame([], $config->get('view.paths'));
    }

    /**
     * Create a spy provider that tracks calls to protected methods.
     *
     * @return \Tests\Support\Spies\SpyModuleServiceProvider
     */
    private function createSpyProvider(): SpyModuleServiceProvider
    {
        $app = \Mockery::mock(Application::class);

        return new SpyModuleServiceProvider($app); // @phpstan-ignore argument.type (the Mockery mock satisfies the Application contract the provider needs at runtime)
    }

    /**
     * Create an application with the given configuration repository bound.
     *
     * @param  \Illuminate\Config\Repository  $config
     * @return \Illuminate\Foundation\Application
     */
    private function createApplication(ConfigRepository $config): FoundationApplication
    {
        $app = new FoundationApplication($this->tempDir);

        $app->instance('config', $config);

        return $app;
    }
}
