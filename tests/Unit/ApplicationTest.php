<?php

namespace Tests\Unit;

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
class ApplicationTest extends TestCase
{
    use InteractsWithModules, ManagesTemporaryFiles;

    /**
     * Set up the test fixtures.
     *
     * @return void
     */
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

        static::assertInstanceOf(ApplicationBuilder::class, $builder);
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

        static::assertSame($this->tempDir, $app->basePath());
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

        static::assertNotEmpty($app->basePath());
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

        $result = $app->resourcePath();

        $resourcesRealPath = realpath(
            $this->tempDir . '/modules/foundation/Resources',
        );

        static::assertSame($resourcesRealPath, $result);
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

        $result = $app->resourcePath();

        static::assertSame(
            $emptyDir . DIRECTORY_SEPARATOR . 'resources',
            $result,
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

        $result = $app->resourcePath('views');

        static::assertSame(
            $resourcesRealPath . DIRECTORY_SEPARATOR . 'views',
            $result,
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

        $result = $app->resourcePath('foundation::views');

        static::assertSame(
            $resourcesRealPath . DIRECTORY_SEPARATOR . 'views',
            $result,
        );
        static::assertStringNotContainsString('::', $result);
    }

    /**
     * Test that path() returns the modules directory by default.
     *
     * @return void
     */
    public function testPathReturnsModulesDirectory(): void
    {
        $app = new Application($this->tempDir);

        $result = $app->path();

        static::assertSame(
            $this->tempDir . DIRECTORY_SEPARATOR . 'modules',
            $result,
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

        $result = $app->path('Models');

        static::assertSame(
            $this->tempDir
                . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . 'Models',
            $result,
        );
    }

    /**
     * Test that path() uses appPath when it has been explicitly set, instead
     * of falling back to basePath/modules.
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

        $result = $app->path('Services');

        static::assertSame(
            $customAppPath . DIRECTORY_SEPARATOR . 'Services',
            $result,
        );
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

        return $reflection->getValue($builder); // @phpstan-ignore return.type
    }
}
