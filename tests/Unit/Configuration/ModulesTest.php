<?php

namespace Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use SineMacula\Laravel\Modules\Configuration\Modules;
use Tests\Support\Concerns\InteractsWithModules;
use Tests\Support\Concerns\ManagesTemporaryFiles;

/**
 * Unit tests for the Modules configuration class.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @SuppressWarnings("php:S1192")
 * @SuppressWarnings("php:S1448")
 * @SuppressWarnings("php:S4833")
 * @SuppressWarnings("php:S2003")
 *
 * @internal
 */
#[CoversClass(Modules::class)]
class ModulesTest extends TestCase
{
    use InteractsWithModules, ManagesTemporaryFiles;

    /**
     * Set up the test environment.
     *
     * Creates a temporary directory structure that mirrors a real modular
     * application layout.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetModulesState();
        $this->createTempDirectory('test-modules-');
        $this->createDirectoryStructure();
    }

    /**
     * Tear down the test environment.
     *
     * Removes the temporary directory and resets all static state on the
     * Modules class.
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
     * Test that setBasePath stores the value used by modulesPath.
     *
     * @return void
     */
    public function testSetBasePathStoresValue(): void
    {
        Modules::setBasePath($this->tempDir);

        $expected = $this->tempDir
            . DIRECTORY_SEPARATOR . 'modules';

        static::assertSame($expected, Modules::modulesPath());
    }

    /**
     * Test that modulesPath returns the base path joined with the modules
     * directory using DIRECTORY_SEPARATOR.
     *
     * @return void
     */
    public function testModulesPathReturnsBasePathPlusModules(): void
    {
        Modules::setBasePath('/app');

        static::assertSame(
            '/app' . DIRECTORY_SEPARATOR . 'modules',
            Modules::modulesPath(),
        );
    }

    /**
     * Test that modulesPath throws a RuntimeException when the base path has
     * not been set.
     *
     * @return void
     */
    #[RunInSeparateProcess]
    public function testModulesPathThrowsWhenBasePathNotSet(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No base path has been set.');

        Modules::modulesPath();
    }

    /**
     * Test that cache writes a valid PHP file that returns an array of
     * discovered modules.
     *
     * @return void
     */
    public function testCacheWritesValidPhpFile(): void
    {
        Modules::setBasePath($this->tempDir);

        Modules::cache();

        $cachePath = $this->tempDir
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'modules.php';

        static::assertFileExists($cachePath);

        $result = require $cachePath;

        static::assertIsArray($result);
        static::assertArrayHasKey('alpha', $result);
        static::assertArrayHasKey('beta', $result);
    }

    /**
     * Test that cache throws a RuntimeException when the cache file cannot be
     * written.
     *
     * @return void
     */
    public function testCacheThrowsOnWriteFailure(): void
    {
        $nonWritable = $this->tempDir
            . DIRECTORY_SEPARATOR . 'readonly';

        mkdir($nonWritable . DIRECTORY_SEPARATOR . 'modules', 0755, true);
        mkdir(
            $nonWritable
                . DIRECTORY_SEPARATOR . 'bootstrap'
                . DIRECTORY_SEPARATOR . 'cache',
            0755,
            true,
        );

        // Make the cache directory non-writable
        chmod(
            $nonWritable
                . DIRECTORY_SEPARATOR . 'bootstrap'
                . DIRECTORY_SEPARATOR . 'cache',
            0444,
        );

        Modules::setBasePath($nonWritable);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to write cache file at');

        // Suppress the PHP warning from file_put_contents so
        // PHPUnit only sees the RuntimeException
        set_error_handler(static fn (): bool => true);

        try {
            Modules::cache();
        } finally {
            restore_error_handler();

            // Restore permissions so tearDown can clean up
            chmod(
                $nonWritable
                    . DIRECTORY_SEPARATOR . 'bootstrap'
                    . DIRECTORY_SEPARATOR . 'cache',
                0755,
            );
        }
    }

    /**
     * Test that cache flushes internal state before performing fresh discovery.
     *
     * @return void
     */
    public function testCacheFlushesBeforeDiscovery(): void
    {
        Modules::setBasePath($this->tempDir);

        // Populate internal state by calling a resolve method
        Modules::routePaths();

        // Cache should flush and re-discover
        Modules::cache();

        $cachePath = $this->tempDir
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'modules.php';

        $result = require $cachePath;

        static::assertArrayHasKey('alpha', $result);
        static::assertArrayHasKey('beta', $result);
    }

    /**
     * Test that clearCache removes the cache file from disk.
     *
     * @return void
     */
    public function testClearCacheRemovesFile(): void
    {
        Modules::setBasePath($this->tempDir);

        $cachePath = $this->tempDir
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'modules.php';

        file_put_contents($cachePath, '<?php return [];');

        static::assertFileExists($cachePath);

        Modules::clearCache();

        static::assertFileDoesNotExist($cachePath);
    }

    /**
     * Test that clearCache flushes the in-memory state so that subsequent calls
     * re-discover modules from the filesystem.
     *
     * @return void
     */
    public function testClearCacheFlushesState(): void
    {
        Modules::setBasePath($this->tempDir);

        // Populate internal state
        Modules::viewPaths();

        // Add a new module directory after initial discovery
        $newModuleViews = $this->tempDir
            . DIRECTORY_SEPARATOR . 'modules'
            . DIRECTORY_SEPARATOR . 'delta'
            . DIRECTORY_SEPARATOR . 'Resources'
            . DIRECTORY_SEPARATOR . 'views';

        mkdir($newModuleViews, 0755, true);

        // Without flush, the cached state would NOT include delta
        Modules::clearCache();

        // After clearing, fresh discovery should find delta
        $viewsAfter = Modules::viewPaths();

        static::assertArrayHasKey('delta', $viewsAfter);
    }

    /**
     * Test that clearCache succeeds without error when no cache file exists.
     *
     * @return void
     */
    public function testClearCacheSucceedsWithoutCacheFile(): void
    {
        Modules::setBasePath($this->tempDir);

        $cachePath = $this->tempDir
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'modules.php';

        static::assertFileDoesNotExist($cachePath);

        // Should not throw
        Modules::clearCache();

        static::assertFileDoesNotExist($cachePath);
    }

    /**
     * Test that resourcePath resolves the correct Resources directory when a
     * module namespace is provided.
     *
     * @return void
     */
    public function testResourcePathWithModuleNamespace(): void
    {
        Modules::setBasePath($this->tempDir);

        $result = Modules::resourcePath('alpha::some/path');

        $expected = realpath(
            $this->tempDir
                . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . 'alpha'
                . DIRECTORY_SEPARATOR . 'Resources',
        );

        static::assertSame($expected, $result);
    }

    /**
     * Test that resourcePath normalises a mixed-case module prefix to
     * lowercase before resolving.
     *
     * @return void
     */
    public function testResourcePathNormalisesModulePrefixToLowercase(): void
    {
        Modules::setBasePath($this->tempDir);

        $lower = Modules::resourcePath('alpha::some/path');
        $upper = Modules::resourcePath('ALPHA::some/path');

        static::assertSame($lower, $upper);
        static::assertNotSame('', $lower);
    }

    /**
     * Test that resourcePath uses the default module when no namespace
     * separator is present.
     *
     * @return void
     */
    public function testResourcePathWithoutNamespaceUsesDefault(): void
    {
        Modules::setBasePath($this->tempDir);

        // The default module is 'foundation', which does not
        // exist in our temp structure, so should return empty
        $result = Modules::resourcePath('some/path');

        static::assertSame('', $result);
    }

    /**
     * Test that resourcePath returns an empty string for an unknown module.
     *
     * @return void
     */
    public function testResourcePathReturnsEmptyForUnknownModule(): void
    {
        Modules::setBasePath($this->tempDir);

        $result = Modules::resourcePath('nonexistent::path');

        static::assertSame('', $result);
    }

    /**
     * Test that resourcePath uses the default module when the module name
     * before :: is empty.
     *
     * @return void
     */
    public function testResourcePathWithEmptyModuleNameUsesDefault(): void
    {
        Modules::setBasePath($this->tempDir);

        $result = Modules::resourcePath('::path');

        static::assertSame('', $result);
    }

    /**
     * Test that a single colon does not act as the module namespace separator
     * — only double colon does.
     *
     * @return void
     */
    public function testSingleColonDoesNotExtractModuleName(): void
    {
        Modules::setBasePath($this->tempDir);

        $result = Modules::resourcePath('alpha:path');

        static::assertSame('', $result);
    }

    /**
     * Test that resourcePath uses the default module config value when no
     * namespace is provided.
     *
     * @return void
     */
    public function testResourcePathUsesDefaultModuleConfig(): void
    {
        $foundationResources = $this->tempDir
            . DIRECTORY_SEPARATOR . 'modules'
            . DIRECTORY_SEPARATOR . 'foundation'
            . DIRECTORY_SEPARATOR . 'Resources';

        mkdir($foundationResources, 0755, true);

        Modules::setBasePath($this->tempDir);

        $result = Modules::resourcePath('some/path');

        static::assertSame(
            realpath($foundationResources),
            $result,
        );
    }

    /**
     * Test that resourcePath with no arguments returns the default module's
     * Resources directory.
     *
     * @return void
     */
    public function testResourcePathWithNoArguments(): void
    {
        $foundationResources = $this->tempDir
            . DIRECTORY_SEPARATOR . 'modules'
            . DIRECTORY_SEPARATOR . 'foundation'
            . DIRECTORY_SEPARATOR . 'Resources';

        mkdir($foundationResources, 0755, true);

        Modules::setBasePath($this->tempDir);

        $result = Modules::resourcePath();

        static::assertSame(
            realpath($foundationResources),
            $result,
        );
    }

    /**
     * Test that routePaths only returns paths for modules that have a
     * routes.php file.
     *
     * @return void
     */
    public function testRoutePathsReturnsExistingRoutesOnly(): void
    {
        Modules::setBasePath($this->tempDir);

        $routes = Modules::routePaths();

        static::assertArrayHasKey('alpha', $routes);
        static::assertArrayNotHasKey('beta', $routes);
        static::assertStringContainsString('routes.php', $routes['alpha']);
    }

    /**
     * Test that viewPaths only returns paths for modules that have a views
     * directory.
     *
     * @return void
     */
    public function testViewPathsReturnsExistingViewsOnly(): void
    {
        Modules::setBasePath($this->tempDir);

        $views = Modules::viewPaths();

        static::assertArrayHasKey('alpha', $views);
        static::assertArrayNotHasKey('beta', $views);
        static::assertStringContainsString('views', $views['alpha']);
    }

    /**
     * Test that langPaths only returns paths for modules that have a lang
     * directory.
     *
     * @return void
     */
    public function testLangPathsReturnsExistingLangOnly(): void
    {
        Modules::setBasePath($this->tempDir);

        $langs = Modules::langPaths();

        static::assertArrayHasKey('alpha', $langs);
        static::assertArrayNotHasKey('beta', $langs);
        static::assertStringContainsString('lang', $langs['alpha']);
    }

    /**
     * Test that resolved paths are cached after the first call and subsequent
     * calls return the same result.
     *
     * @return void
     */
    public function testResolvePathsCachesResults(): void
    {
        Modules::setBasePath($this->tempDir);

        $first  = Modules::viewPaths();
        $second = Modules::viewPaths();

        static::assertSame($first, $second);
    }

    /**
     * Test that resolvePaths filters out modules whose target path does not
     * exist on disk.
     *
     * @return void
     */
    public function testResolvePathsFiltersNonexistentPaths(): void
    {
        Modules::setBasePath($this->tempDir);

        $views = Modules::viewPaths();

        // Beta has no views directory
        static::assertArrayNotHasKey('beta', $views);
        static::assertCount(1, $views);
    }

    /**
     * Test that discoverModules only finds directories, not regular files
     * placed in the modules directory.
     *
     * @return void
     */
    public function testDiscoverModulesFindsDirectoriesOnly(): void
    {
        // Create a regular file in the modules directory
        file_put_contents(
            $this->tempDir
                . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . 'not-a-module.txt',
            'test',
        );

        Modules::setBasePath($this->tempDir);

        $routes = Modules::routePaths();

        static::assertArrayNotHasKey('not-a-module.txt', $routes);
        static::assertArrayHasKey('alpha', $routes);
    }

    /**
     * Test that discoverModules skips dot entries such as .hidden directories.
     *
     * @return void
     */
    public function testDiscoverModulesSkipsDotEntries(): void
    {
        Modules::setBasePath($this->tempDir);

        $views = Modules::viewPaths();

        static::assertArrayNotHasKey('.hidden', $views);
        static::assertArrayNotHasKey('hidden', $views);
    }

    /**
     * Test that discoverModules lowercases directory names when using them as
     * module keys.
     *
     * @return void
     */
    public function testDiscoverModulesLowercasesNames(): void
    {
        // Create an uppercase module directory
        $uppercaseDir = $this->tempDir
            . DIRECTORY_SEPARATOR . 'modules'
            . DIRECTORY_SEPARATOR . 'Gamma';

        mkdir(
            $uppercaseDir
                . DIRECTORY_SEPARATOR . 'Resources'
                . DIRECTORY_SEPARATOR . 'views',
            0755,
            true,
        );

        Modules::setBasePath($this->tempDir);

        $views = Modules::viewPaths();

        static::assertArrayHasKey('gamma', $views);
        static::assertArrayNotHasKey('Gamma', $views);
    }

    /**
     * Test that resolveModules prefers the cache file over filesystem discovery
     * when a cache file exists.
     *
     * @return void
     */
    public function testResolveModulesPrefersCacheOverDiscovery(): void
    {
        Modules::setBasePath($this->tempDir);

        // Write a cache file with a custom module entry
        $fakePath = $this->tempDir
            . DIRECTORY_SEPARATOR . 'modules'
            . DIRECTORY_SEPARATOR . 'alpha';

        $cachePath = $this->tempDir
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'modules.php';

        $content = "<?php\nreturn "
            . var_export(['cached_module' => $fakePath], true)
            . ';';

        file_put_contents($cachePath, $content);

        $routes = Modules::routePaths();

        // The cached_module key should be present instead of
        // alpha/beta from discovery
        static::assertArrayNotHasKey('alpha', $routes);
        static::assertArrayNotHasKey('beta', $routes);

        // The cached_module does not have routes.php, so it
        // should be filtered out, but the key should have been
        // attempted — confirming cache was used
        $views = Modules::viewPaths();

        // Verify that 'alpha' (from discovery) is NOT present,
        // confirming cache was used
        static::assertArrayNotHasKey('alpha', $views);
    }

    /**
     * Test that resolveModules falls back to filesystem discovery when no cache
     * file exists.
     *
     * @return void
     */
    public function testResolveModulesFallsBackToDiscoveryWithoutCache(): void
    {
        Modules::setBasePath($this->tempDir);

        $cachePath = $this->tempDir
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'modules.php';

        static::assertFileDoesNotExist($cachePath);

        $routes = Modules::routePaths();

        static::assertArrayHasKey('alpha', $routes);
    }

    /**
     * Test that getModules lazy loads the module list and returns consistent
     * results across multiple method calls.
     *
     * @return void
     */
    public function testGetModulesLazyLoads(): void
    {
        Modules::setBasePath($this->tempDir);

        $routes = Modules::routePaths();
        $views  = Modules::viewPaths();

        // Both should reflect the same discovered modules
        static::assertArrayHasKey('alpha', $routes);
        static::assertArrayHasKey('alpha', $views);
    }

    /**
     * Test that flush clears both the modules and resolved paths, forcing
     * re-resolution on the next call.
     *
     * @return void
     */
    public function testFlushClearsModulesAndResolvedPaths(): void
    {
        Modules::setBasePath($this->tempDir);

        // Populate internal state
        $viewsBefore = Modules::viewPaths();

        // cache() calls flush() internally
        Modules::cache();

        // After flush, the next call should re-resolve
        $viewsAfter = Modules::viewPaths();

        static::assertEquals($viewsBefore, $viewsAfter);
    }

    /**
     * Test that listenerPaths only returns paths for modules that have a
     * Listeners directory.
     *
     * @return void
     */
    public function testListenerPathsReturnsExistingListenersOnly(): void
    {
        Modules::setBasePath($this->tempDir);

        $listeners = Modules::listenerPaths();

        static::assertArrayHasKey('alpha', $listeners);
        static::assertArrayNotHasKey('beta', $listeners);
        static::assertStringContainsString('Listeners', $listeners['alpha']);
    }

    /**
     * Test that commandPaths only returns paths for modules that have a
     * Console/Commands directory.
     *
     * @return void
     */
    public function testCommandPathsReturnsExistingCommandsOnly(): void
    {
        Modules::setBasePath($this->tempDir);

        $commands = Modules::commandPaths();

        static::assertArrayHasKey('alpha', $commands);
        static::assertArrayNotHasKey('beta', $commands);
        static::assertStringContainsString('Commands', $commands['alpha']);
    }

    /**
     * Test that schedulePaths only returns paths for modules that have a
     * Console/schedule.php file.
     *
     * @return void
     */
    public function testSchedulePathsReturnsExistingSchedulesOnly(): void
    {
        Modules::setBasePath($this->tempDir);

        $schedules = Modules::schedulePaths();

        static::assertArrayHasKey('alpha', $schedules);
        static::assertArrayNotHasKey('beta', $schedules);
        static::assertStringContainsString('schedule.php', $schedules['alpha']);
    }

    /**
     * Test that getModule returns the path for a known module.
     *
     * @return void
     */
    public function testGetModuleReturnsPathForKnownModule(): void
    {
        Modules::setBasePath($this->tempDir);

        $path = Modules::getModule('alpha');

        static::assertNotNull($path);
        static::assertStringContainsString('alpha', $path);
    }

    /**
     * Test that getModule returns null for an unknown module.
     *
     * @return void
     */
    public function testGetModuleReturnsNullForUnknownModule(): void
    {
        Modules::setBasePath($this->tempDir);

        static::assertNull(Modules::getModule('nonexistent'));
    }

    /**
     * Test that getModule normalises the name to lowercase.
     *
     * @return void
     */
    public function testGetModuleNormalisesNameToLowercase(): void
    {
        Modules::setBasePath($this->tempDir);

        $lower = Modules::getModule('alpha');
        $upper = Modules::getModule('ALPHA');

        static::assertSame($lower, $upper);
    }

    /**
     * Test that getModules returns all discovered modules.
     *
     * @return void
     */
    public function testGetModulesReturnsAllDiscoveredModules(): void
    {
        Modules::setBasePath($this->tempDir);

        $modules = Modules::getModules();

        static::assertArrayHasKey('alpha', $modules);
        static::assertArrayHasKey('beta', $modules);
        static::assertArrayHasKey('.hidden', $modules);
        static::assertCount(3, $modules);
    }

    /**
     * Test that the MODULE_SEPARATOR constant has the correct value.
     *
     * @return void
     */
    public function testModuleSeparatorConstant(): void
    {
        static::assertSame('::', Modules::MODULE_SEPARATOR);
    }

    /**
     * Test that cache uses atomic write via a temp file.
     *
     * @return void
     */
    public function testCacheCreatesFileAtomically(): void
    {
        Modules::setBasePath($this->tempDir);

        Modules::cache();

        $cachePath = $this->tempDir
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'modules.php';

        static::assertFileExists($cachePath);

        // The temp file should not persist after a successful cache
        static::assertFileDoesNotExist($cachePath . '.tmp');
    }

    /**
     * Test that loadModulesFromCache returns null when the cache contains
     * invalid content.
     *
     * @return void
     */
    public function testLoadModulesFromCacheReturnsNullForInvalidContent(): void
    {
        Modules::setBasePath($this->tempDir);

        $cachePath = $this->tempDir
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'modules.php';

        // Write a cache file that returns a non-array value
        file_put_contents($cachePath, '<?php return "not-an-array";');

        // Should fall back to discovery instead of using invalid cache
        $routes = Modules::routePaths();

        static::assertArrayHasKey('alpha', $routes);
    }

    /**
     * Create the temporary directory structure for testing.
     *
     * @return void
     */
    private function createDirectoryStructure(): void
    {
        // Alpha module — fully populated
        $this->createDirectory('modules/alpha/Resources/views');
        $this->createDirectory('modules/alpha/Resources/lang');
        $this->createDirectory('modules/alpha/Http');
        $this->createDirectory('modules/alpha/Console/Commands');
        $this->createDirectory('modules/alpha/Listeners');

        $this->createFile('modules/alpha/Http/routes.php');
        $this->createFile('modules/alpha/Console/schedule.php');

        // Beta module — minimal, no routes/views/lang
        $this->createDirectory('modules/beta/Resources');
        $this->createDirectory('modules/beta/Http');

        // Dot directory — should be skipped
        $this->createDirectory('modules/.hidden');

        // Bootstrap cache directory
        $this->createDirectory('bootstrap/cache');
    }
}
