<?php

declare(strict_types = 1);

namespace Tests\Integration\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SineMacula\Laravel\Modules\Configuration\Modules;
use Tests\Support\Concerns\InteractsWithModules;
use Tests\Support\Concerns\ManagesTemporaryFiles;

/**
 * Integration tests for the Modules configuration class.
 *
 * Exercises module discovery, caching, and path resolution against a real
 * filesystem with temporary module directories.
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
#[CoversClass(Modules::class)]
final class ModulesIntegrationTest extends TestCase
{
    use InteractsWithModules, ManagesTemporaryFiles;

    /**
     * Set up the temporary directory structure and reset Modules static state
     * before each test.
     *
     * @return void
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('modules-integration-');
        $this->createDirectoryStructure();
        $this->initModules($this->tempDir);
    }

    /**
     * Clean up the temporary directory and reset Modules static state after
     * each test.
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
     * Test that all three modules are discovered from the filesystem.
     *
     * @return void
     */
    public function testDiscoversRealModulesFromFilesystem(): void
    {
        $routes    = Modules::routePaths();
        $viewPaths = Modules::viewPaths();
        $langPaths = Modules::langPaths();

        // Alpha has routes, views, and lang
        self::assertArrayHasKey('alpha', $routes);
        self::assertArrayHasKey('alpha', $viewPaths);
        self::assertArrayHasKey('alpha', $langPaths);

        // Verify discovery via modulesPath containing all three
        $modulesPath = Modules::modulesPath();
        self::assertDirectoryExists(
            $modulesPath . DIRECTORY_SEPARATOR . 'alpha',
        );
        self::assertDirectoryExists(
            $modulesPath . DIRECTORY_SEPARATOR . 'beta',
        );
        self::assertDirectoryExists(
            $modulesPath . DIRECTORY_SEPARATOR . 'gamma',
        );
    }

    /**
     * Test that discovered module names are lowercased even when the directory
     * name has mixed case.
     *
     * @return void
     */
    public function testDiscoveredModuleNamesAreLowercase(): void
    {
        mkdir(
            $this->tempDir . '/modules/DeltaMixed',
            0755,
            true,
        );
        mkdir(
            $this->tempDir
            . '/modules/DeltaMixed/Resources/views',
            0755,
            true,
        );

        $viewPaths = Modules::viewPaths();

        self::assertArrayHasKey('deltamixed', $viewPaths);
        self::assertArrayNotHasKey(
            'DeltaMixed',
            $viewPaths,
        );
    }

    /**
     * Test that caching and restoring produces identical route path results.
     *
     * @return void
     */
    public function testCacheRoundtripProducesIdenticalResults(): void
    {
        $routesBefore = Modules::routePaths();

        Modules::cache();
        $this->resetModulesState();
        Modules::setBasePath($this->tempDir);
        $routesAfter = Modules::routePaths();

        self::assertSame($routesBefore, $routesAfter);
    }

    /**
     * Test that the cache file contains valid PHP that returns an array.
     *
     * @return void
     */
    public function testCacheFileContainsValidPhp(): void
    {
        Modules::cache();

        $cachePath = $this->tempDir
            . '/bootstrap/cache/modules.php';

        self::assertFileExists($cachePath);

        $content = file_get_contents($cachePath);
        self::assertStringStartsWith('<?php', $content);

        $modules = require $cachePath;
        self::assertIsArray($modules);
    }

    /**
     * Test that clearing the cache still allows modules to be rediscovered.
     *
     * @return void
     */
    public function testClearCacheThenRediscover(): void
    {
        Modules::cache();

        Modules::clearCache();
        $routes = Modules::routePaths();

        self::assertArrayHasKey('alpha', $routes);
    }

    /**
     * Test that resourcePath resolves a namespaced module path to the correct
     * Resources directory.
     *
     * @return void
     */
    public function testResourcePathResolvesNamespacedModule(): void
    {
        $path = Modules::resourcePath('alpha::views');

        $expected = realpath(
            $this->tempDir . '/modules/alpha/Resources',
        );

        self::assertSame($expected, $path);
    }

    /**
     * Test that resourcePath falls back to the default module when no namespace
     * is present, returning empty when the default module does not exist.
     *
     * @return void
     */
    public function testResourcePathFallsBackToDefaultModule(): void
    {
        $path = Modules::resourcePath('views');

        self::assertSame('', $path);
    }

    /**
     * Test that routePaths returns only modules with an existing routes file.
     *
     * @return void
     */
    public function testRoutePathsReturnsOnlyExistingRouteFiles(): void
    {
        $routes = Modules::routePaths();

        self::assertArrayHasKey('alpha', $routes);
        self::assertArrayNotHasKey('beta', $routes);
        self::assertArrayNotHasKey('gamma', $routes);
        self::assertCount(1, $routes);
    }

    /**
     * Test that viewPaths returns only modules with an existing views
     * directory.
     *
     * @return void
     */
    public function testViewPathsReturnsOnlyExistingViewDirs(): void
    {
        $views = Modules::viewPaths();

        self::assertArrayHasKey('alpha', $views);
        self::assertArrayNotHasKey('beta', $views);
        self::assertArrayNotHasKey('gamma', $views);
        self::assertCount(1, $views);
    }

    /**
     * Test that langPaths returns only modules with an existing lang directory.
     *
     * @return void
     */
    public function testLangPathsReturnsOnlyExistingLangDirs(): void
    {
        $langs = Modules::langPaths();

        self::assertArrayHasKey('alpha', $langs);
        self::assertArrayNotHasKey('beta', $langs);
        self::assertArrayNotHasKey('gamma', $langs);
        self::assertCount(1, $langs);
    }

    /**
     * Test that a pre-existing cache prevents fresh filesystem discovery.
     *
     * @return void
     */
    public function testCachePreventsFreshDiscovery(): void
    {
        $cachePath = $this->tempDir
            . '/bootstrap/cache/modules.php';
        $alphaPath = realpath(
            $this->tempDir . '/modules/alpha',
        );

        $content = "<?php\nreturn "
            . var_export(
                ['alpha' => $alphaPath],
                true,
            ) . ';';

        file_put_contents($cachePath, $content);

        $routes = Modules::routePaths();

        self::assertArrayHasKey('alpha', $routes);
        self::assertArrayNotHasKey('beta', $routes);
        self::assertArrayNotHasKey('gamma', $routes);
    }

    /**
     * Test that clearing the cache allows all modules to be rediscovered from
     * the filesystem.
     *
     * @return void
     */
    public function testClearCacheAllowsRediscovery(): void
    {
        $cachePath = $this->tempDir
            . '/bootstrap/cache/modules.php';
        $alphaPath = realpath(
            $this->tempDir . '/modules/alpha',
        );

        $content = "<?php\nreturn "
            . var_export(
                ['alpha' => $alphaPath],
                true,
            ) . ';';

        file_put_contents($cachePath, $content);

        $routesBefore = Modules::routePaths();
        self::assertCount(1, $routesBefore);

        Modules::clearCache();
        Modules::setBasePath($this->tempDir);
        $routesAfter = Modules::routePaths();

        self::assertArrayHasKey('alpha', $routesAfter);

        $modulesPath = Modules::modulesPath();
        self::assertDirectoryExists(
            $modulesPath . DIRECTORY_SEPARATOR . 'alpha',
        );
        self::assertDirectoryExists(
            $modulesPath . DIRECTORY_SEPARATOR . 'beta',
        );
        self::assertDirectoryExists(
            $modulesPath . DIRECTORY_SEPARATOR . 'gamma',
        );
    }

    /**
     * Create the temporary directory structure for testing.
     *
     * @return void
     */
    private function createDirectoryStructure(): void
    {
        // Alpha module - fully populated
        $this->createDirectory('modules/alpha/Resources/views');
        $this->createDirectory('modules/alpha/Resources/lang');
        $this->createDirectory('modules/alpha/Http');
        $this->createDirectory('modules/alpha/Console/Commands');
        $this->createDirectory('modules/alpha/Listeners');

        $this->createFile(
            'modules/alpha/Http/routes.php',
            "<?php\n",
        );
        $this->createFile(
            'modules/alpha/Console/schedule.php',
            "<?php\n",
        );

        // Beta module - partial
        $this->createDirectory('modules/beta/Resources');
        $this->createDirectory('modules/beta/Http');

        // Gamma module - empty
        $this->createDirectory('modules/gamma');

        // Bootstrap cache directory
        $this->createDirectory('bootstrap/cache');
    }
}
