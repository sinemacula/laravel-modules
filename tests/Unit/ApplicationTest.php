<?php

declare(strict_types = 1);

namespace Tests\Unit;

use Illuminate\Config\Repository as ConfigRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SineMacula\Laravel\Modules\Application;
use SineMacula\Laravel\Modules\Configuration\ApplicationBuilder;
use SineMacula\Laravel\Modules\Configuration\Modules;
use Tests\Support\Concerns\InteractsWithModules;
use Tests\Support\Concerns\ManagesTemporaryFiles;

/**
 * Unit tests for the Application class.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @SuppressWarnings("php:S1192")
 * @SuppressWarnings("php:S3011")
 *
 * @internal
 */
#[CoversClass(Application::class)]
final class ApplicationTest extends TestCase
{
    use InteractsWithModules, ManagesTemporaryFiles;

    /**
     * Set up the test fixtures.
     *
     * @return void
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('app_test_');

        $this->createDirectory('modules/foundation/Resources');

        Modules::setBasePath($this->tempDir);
    }

    /**
     * Tear down the test fixtures.
     *
     * @return void
     */
    #[\Override]
    protected function tearDown(): void
    {
        $this->removeTempDirectory();
        $this->resetModulesState();

        parent::tearDown();
    }

    /**
     * Test that configure returns an ApplicationBuilder instance.
     *
     * @return void
     */
    public function testConfigureReturnsApplicationBuilder(): void
    {
        $builder = Application::configure($this->tempDir);

        self::assertInstanceOf(ApplicationBuilder::class, $builder);
    }

    /**
     * Test that configure uses an explicit base path when provided.
     *
     * @return void
     */
    public function testConfigureWithExplicitBasePath(): void
    {
        $builder = Application::configure($this->tempDir);

        $app = $this->extractAppFromBuilder($builder);

        self::assertSame($this->tempDir, $app->basePath());
    }

    /**
     * Test that configure with null infers the base path without throwing.
     *
     * @return void
     */
    public function testConfigureWithNullInfersBasePath(): void
    {
        $builder = Application::configure(null);

        $app = $this->extractAppFromBuilder($builder);

        // The inferred base path must resolve to a real directory (the project
        // root from which the test suite runs), not merely a non-empty string.
        self::assertDirectoryExists($app->basePath());
    }

    /**
     * Test that resourcePath returns the module path when Modules resolves a
     * non-empty resource path.
     *
     * @return void
     */
    public function testResourcePathReturnsModulePathWhenFound(): void
    {
        $app = new Application($this->tempDir);

        $path = $app->resourcePath();

        $resourcesRealPath = realpath(
            $this->tempDir . '/modules/foundation/Resources',
        );

        self::assertSame($resourcesRealPath, $path);
    }

    /**
     * Test that resourcePath falls back to the parent implementation when
     * Modules returns an empty string.
     *
     * @return void
     */
    public function testResourcePathFallsBackToParentWhenEmpty(): void
    {
        $emptyDir = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'app_empty_' . uniqid();

        mkdir($emptyDir, 0755, true);
        mkdir($emptyDir . '/modules', 0755, true);
        mkdir($emptyDir . '/resources', 0755, true);

        Modules::setBasePath($emptyDir);
        $this->resetModulesState();

        $app = new Application($emptyDir);

        $path = $app->resourcePath();

        self::assertSame(
            $emptyDir . DIRECTORY_SEPARATOR . 'resources',
            $path,
        );

        $this->removeDirectory($emptyDir);
    }

    /**
     * Test that resourcePath appends the subpath to the module resources
     * directory.
     *
     * @return void
     */
    public function testResourcePathAppendsSubpath(): void
    {
        $app = new Application($this->tempDir);

        $resourcesRealPath = realpath(
            $this->tempDir . '/modules/foundation/Resources',
        );

        $path = $app->resourcePath('views');

        self::assertSame(
            $resourcesRealPath . DIRECTORY_SEPARATOR . 'views',
            $path,
        );
    }

    /**
     * Test that resourcePath strips the module:: prefix before joining the
     * path, so the namespace does not leak into the filesystem path.
     *
     * @return void
     */
    public function testResourcePathStripsModulePrefixBeforeJoining(): void
    {
        $app = new Application($this->tempDir);

        $resourcesRealPath = realpath(
            $this->tempDir . '/modules/foundation/Resources',
        );

        $path = $app->resourcePath('foundation::views');

        self::assertSame(
            $resourcesRealPath . DIRECTORY_SEPARATOR . 'views',
            $path,
        );
        self::assertStringNotContainsString('::', $path);
    }

    /**
     * Test that resourcePath only treats the first :: as the module boundary,
     * preserving any further separators within the sub-path.
     *
     * @return void
     */
    public function testResourcePathPreservesNestedSeparatorInSubPath(): void
    {
        $app = new Application($this->tempDir);

        $resourcesRealPath = realpath(
            $this->tempDir . '/modules/foundation/Resources',
        );

        $path = $app->resourcePath('foundation::nested::asset');

        self::assertSame(
            $resourcesRealPath . DIRECTORY_SEPARATOR . 'nested::asset',
            $path,
        );
    }

    /**
     * Test that path() returns the modules directory by default.
     *
     * @return void
     */
    public function testPathReturnsModulesDirectory(): void
    {
        $app = new Application($this->tempDir);

        $path = $app->path();

        self::assertSame(
            $this->tempDir . DIRECTORY_SEPARATOR . 'modules',
            $path,
        );
    }

    /**
     * Test that path() appends a subpath to the modules directory.
     *
     * @return void
     */
    public function testPathAppendsSubpath(): void
    {
        $app = new Application($this->tempDir);

        $path = $app->path('Models');

        self::assertSame(
            $this->tempDir
                . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . 'Models',
            $path,
        );
    }

    /**
     * Test that path() uses appPath when it has been explicitly set, instead of
     * falling back to basePath/modules.
     *
     * @return void
     */
    public function testPathUsesAppPathWhenSet(): void
    {
        $app = new Application($this->tempDir);

        $customAppPath = $this->tempDir
            . DIRECTORY_SEPARATOR . 'custom_app';

        $reflection = new \ReflectionProperty($app, 'appPath');
        $reflection->setValue($app, $customAppPath);

        $path = $app->path('Services');

        self::assertSame(
            $customAppPath . DIRECTORY_SEPARATOR . 'Services',
            $path,
        );
    }

    /**
     * Test that viewPath returns the first configured view path.
     *
     * @return void
     */
    public function testViewPathUsesTheFirstConfiguredPath(): void
    {
        $configured = $this->tempDir . DIRECTORY_SEPARATOR . 'configured';

        $app = $this->applicationWithViewPaths([$configured]);

        self::assertSame(
            $configured . DIRECTORY_SEPARATOR . 'components',
            $app->viewPath('components'),
        );
    }

    /**
     * Test that viewPath falls back to the module-aware resource path when no
     * view paths are configured, rather than reading past the end of the list.
     *
     * @return void
     */
    public function testViewPathFallsBackToTheResourcePathWhenNoneAreConfigured(): void
    {
        $app = $this->applicationWithViewPaths([]);

        self::assertSame(
            $this->tempDir
                . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . 'foundation'
                . DIRECTORY_SEPARATOR . 'Resources'
                . DIRECTORY_SEPARATOR . 'views'
                . DIRECTORY_SEPARATOR . 'components',
            $app->viewPath('components'),
        );
    }

    /**
     * Test that viewPath falls back to the resource path when the configured
     * entry is not a string.
     *
     * @return void
     */
    public function testViewPathFallsBackWhenTheConfiguredPathIsNotAString(): void
    {
        $app = $this->applicationWithViewPaths([123]);

        self::assertSame(
            $this->tempDir
                . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . 'foundation'
                . DIRECTORY_SEPARATOR . 'Resources'
                . DIRECTORY_SEPARATOR . 'views',
            $app->viewPath(),
        );
    }

    /**
     * Test that viewPath falls back to the resource path when the view paths
     * are absent from the configuration entirely.
     *
     * @return void
     */
    public function testViewPathFallsBackWhenTheViewPathsAreMissing(): void
    {
        $app = new Application($this->tempDir);

        $app->instance('config', new ConfigRepository);

        self::assertSame(
            $this->tempDir
                . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . 'foundation'
                . DIRECTORY_SEPARATOR . 'Resources'
                . DIRECTORY_SEPARATOR . 'views',
            $app->viewPath(),
        );
    }

    /**
     * Test that viewPath strips a trailing separator from the configured path
     * so the joined result is not doubled.
     *
     * @return void
     */
    public function testViewPathStripsATrailingSeparator(): void
    {
        $configured = $this->tempDir . DIRECTORY_SEPARATOR . 'configured';

        $app = $this->applicationWithViewPaths([$configured . DIRECTORY_SEPARATOR]);

        self::assertSame(
            $configured . DIRECTORY_SEPARATOR . 'components',
            $app->viewPath('components'),
        );
    }

    /**
     * Create an application whose configuration holds the given view paths.
     *
     * @param  list<int|string>  $paths
     * @return \SineMacula\Laravel\Modules\Application
     */
    private function applicationWithViewPaths(array $paths): Application
    {
        $app = new Application($this->tempDir);

        $app->instance('config', new ConfigRepository(['view' => ['paths' => $paths]]));

        return $app;
    }

    /**
     * Extract the Application instance from an ApplicationBuilder using
     * reflection.
     *
     * @param  \SineMacula\Laravel\Modules\Configuration\ApplicationBuilder  $builder
     * @return \SineMacula\Laravel\Modules\Application
     */
    private function extractAppFromBuilder(ApplicationBuilder $builder): Application
    {
        $reflection = new \ReflectionProperty($builder, 'app');

        return $reflection->getValue($builder); // @phpstan-ignore return.type (ReflectionProperty::getValue returns mixed; the builder's app property holds an Application)
    }
}
