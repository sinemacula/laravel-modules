<?php

declare(strict_types = 1);

namespace Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use SineMacula\Laravel\Modules\Configuration\ModuleManifest;
use SineMacula\Laravel\Modules\Configuration\Modules;
use SineMacula\Laravel\Modules\Exceptions\ModuleException;
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
final class ModulesTest extends TestCase
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
    #[\Override]
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
    #[\Override]
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

        self::assertSame($expected, Modules::modulesPath());
    }

    /**
     * Test that changing the base path discards module state resolved against
     * the previous one.
     *
     * @return void
     */
    public function testSetBasePathDiscardsStateWhenThePathChanges(): void
    {
        Modules::setBasePath($this->tempDir);

        self::assertArrayHasKey('alpha', Modules::getModules());

        $other = $this->tempDir . DIRECTORY_SEPARATOR . 'other';

        mkdir($other . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'omega', 0755, true);

        Modules::setBasePath($other);

        $modules = Modules::getModules();

        self::assertArrayHasKey('omega', $modules);
        self::assertArrayNotHasKey('alpha', $modules);
    }

    /**
     * Test that setting the same base path again keeps the memoised state, so
     * the resolver is not a refresh hook.
     *
     * @return void
     */
    public function testSetBasePathKeepsStateWhenThePathIsUnchanged(): void
    {
        Modules::setBasePath($this->tempDir);

        $before = Modules::getModules();

        mkdir($this->tempDir . '/modules/omega', 0755, true);

        Modules::setBasePath($this->tempDir);

        self::assertSame($before, Modules::getModules());
    }

    /**
     * Test that a base path given in a non-canonical form resolves to the same
     * value as its canonical form.
     *
     * @return void
     */
    public function testSetBasePathCanonicalisesTheGivenPath(): void
    {
        Modules::setBasePath($this->tempDir . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . '..');

        self::assertSame(
            $this->tempDir . DIRECTORY_SEPARATOR . 'modules',
            Modules::modulesPath(),
        );
    }

    /**
     * Test that a base path which cannot be resolved is kept exactly as given.
     *
     * @return void
     */
    public function testSetBasePathKeepsUnresolvablePathsAsGiven(): void
    {
        Modules::setBasePath('/no/such/directory');

        self::assertSame(
            '/no/such/directory' . DIRECTORY_SEPARATOR . 'modules',
            Modules::modulesPath(),
        );
    }

    /**
     * Test that an empty base path is kept as given rather than resolving to
     * the working directory.
     *
     * @return void
     */
    public function testSetBasePathKeepsAnEmptyPathAsGiven(): void
    {
        Modules::setBasePath('');

        self::assertSame(DIRECTORY_SEPARATOR . 'modules', Modules::modulesPath());
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

        self::assertSame(
            '/app' . DIRECTORY_SEPARATOR . 'modules',
            Modules::modulesPath(),
        );
    }

    /**
     * Test that modulesPath throws a ModuleException when the base path has not
     * been set.
     *
     * @return void
     */
    #[RunInSeparateProcess]
    public function testModulesPathThrowsWhenBasePathNotSet(): void
    {
        $this->expectException(ModuleException::class);
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

        self::assertFileExists($cachePath);

        $manifest = require $cachePath;

        self::assertIsArray($manifest);
        self::assertIsString($manifest['signature']);
        self::assertIsArray($manifest['modules']);
        self::assertArrayHasKey('alpha', $manifest['modules']);
        self::assertArrayHasKey('beta', $manifest['modules']);
    }

    /**
     * Test that cache throws a ModuleException when the cache file cannot be
     * written.
     *
     * @return void
     */
    public function testCacheThrowsOnWriteFailure(): void
    {
        $cacheDir  = $this->createReadOnlyCacheDirectory();
        $cachePath = $cacheDir . DIRECTORY_SEPARATOR . 'modules.php';

        Modules::setBasePath(dirname($cacheDir, 2));

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('Failed to write temporary manifest file at ' . $cachePath . '.tmp.');

        // Suppress the file_put_contents warning so PHPUnit sees the exception.
        set_error_handler(static fn (): bool => true);

        try {
            Modules::cache();
        } finally {
            restore_error_handler();

            // Restore permissions so tearDown can clean up.
            chmod($cacheDir, 0755);
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

        $manifest = require $cachePath;

        self::assertArrayHasKey('alpha', $manifest['modules']);
        self::assertArrayHasKey('beta', $manifest['modules']);
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

        self::assertFileExists($cachePath);

        Modules::clearCache();

        self::assertFileDoesNotExist($cachePath);
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

        self::assertArrayHasKey('delta', $viewsAfter);
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

        self::assertFileDoesNotExist($cachePath);

        // Should not throw
        Modules::clearCache();

        self::assertFileDoesNotExist($cachePath);
    }

    /**
     * Test that clearCache returns true when the cache file is removed.
     *
     * @return void
     */
    public function testClearCacheReturnsTrueWhenFileRemoved(): void
    {
        Modules::setBasePath($this->tempDir);

        $cachePath = $this->tempDir
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache'
            . DIRECTORY_SEPARATOR . 'modules.php';

        file_put_contents($cachePath, '<?php return [];');

        self::assertTrue(Modules::clearCache());
        self::assertFileDoesNotExist($cachePath);
    }

    /**
     * Test that clearCache returns true when there is no cache file to remove.
     *
     * @return void
     */
    public function testClearCacheReturnsTrueWhenNoCacheFile(): void
    {
        Modules::setBasePath($this->tempDir);

        self::assertTrue(Modules::clearCache());
    }

    /**
     * Test that clearCache returns false when the cache file cannot be removed.
     *
     * @return void
     */
    public function testClearCacheReturnsFalseWhenFileCannotBeRemoved(): void
    {
        Modules::setBasePath($this->tempDir);

        $cacheDir = $this->tempDir
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache';

        $cachePath = $cacheDir . DIRECTORY_SEPARATOR . 'modules.php';

        file_put_contents($cachePath, '<?php return [];');

        // Remove write permission on the directory so the unlink fails.
        chmod($cacheDir, 0555);

        try {
            self::assertFalse(Modules::clearCache());
            self::assertFileExists($cachePath);
        } finally {
            chmod($cacheDir, 0755);
        }
    }

    /**
     * Test that discoverModules throws a ModuleException when the modules
     * directory exists but cannot be read.
     *
     * @return void
     */
    public function testDiscoverModulesThrowsWhenModulesDirectoryUnreadable(): void
    {
        $modulesDir = $this->tempDir . DIRECTORY_SEPARATOR . 'modules';

        // Drop read permission so DirectoryIterator cannot open it.
        chmod($modulesDir, 0000);

        Modules::setBasePath($this->tempDir);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('Failed to read the modules directory at ' . $modulesDir . '.');

        try {
            Modules::getModules();
        } finally {
            chmod($modulesDir, 0755);
        }
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

        $path = Modules::resourcePath('alpha::some/path');

        $expected = realpath(
            $this->tempDir
                . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . 'alpha'
                . DIRECTORY_SEPARATOR . 'Resources',
        );

        self::assertSame($expected, $path);
    }

    /**
     * Test that resourcePath normalises a mixed-case module prefix to lowercase
     * before resolving.
     *
     * @return void
     */
    public function testResourcePathNormalisesModulePrefixToLowercase(): void
    {
        Modules::setBasePath($this->tempDir);

        $lower = Modules::resourcePath('alpha::some/path');
        $upper = Modules::resourcePath('ALPHA::some/path');

        self::assertSame($lower, $upper);
        self::assertNotSame('', $lower);
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

        // The default module is 'foundation', which does not exist in our temp
        // structure, so should return empty
        $path = Modules::resourcePath('some/path');

        self::assertSame('', $path);
    }

    /**
     * Test that resourcePath returns an empty string for an unknown module.
     *
     * @return void
     */
    public function testResourcePathReturnsEmptyForUnknownModule(): void
    {
        Modules::setBasePath($this->tempDir);

        $path = Modules::resourcePath('nonexistent::path');

        self::assertSame('', $path);
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

        $path = Modules::resourcePath('::path');

        self::assertSame('', $path);
    }

    /**
     * Test that a single colon does not act as the module namespace separator
     * - only double colon does.
     *
     * @return void
     */
    public function testSingleColonDoesNotExtractModuleName(): void
    {
        Modules::setBasePath($this->tempDir);

        $path = Modules::resourcePath('alpha:path');

        self::assertSame('', $path);
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

        $path = Modules::resourcePath('some/path');

        self::assertSame(
            realpath($foundationResources),
            $path,
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

        $path = Modules::resourcePath();

        self::assertSame(
            realpath($foundationResources),
            $path,
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

        self::assertArrayHasKey('alpha', $routes);
        self::assertArrayNotHasKey('beta', $routes);
        self::assertStringContainsString('routes.php', $routes['alpha']);
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

        self::assertArrayHasKey('alpha', $views);
        self::assertArrayNotHasKey('beta', $views);
        self::assertStringContainsString('views', $views['alpha']);
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

        self::assertArrayHasKey('alpha', $langs);
        self::assertArrayNotHasKey('beta', $langs);
        self::assertStringContainsString('lang', $langs['alpha']);
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

        self::assertSame($first, $second);
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
        self::assertArrayNotHasKey('beta', $views);
        self::assertCount(1, $views);
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

        self::assertArrayNotHasKey('not-a-module.txt', $routes);
        self::assertArrayHasKey('alpha', $routes);
    }

    /**
     * Test that discoverModules skips dot entries such as .hidden directories.
     *
     * @return void
     */
    public function testDiscoverModulesSkipsDotEntries(): void
    {
        Modules::setBasePath($this->tempDir);

        $modules = Modules::getModules();

        self::assertArrayNotHasKey('.hidden', $modules);
        self::assertArrayNotHasKey('hidden', $modules);
    }

    /**
     * Test that discoverModules returns an empty array when the modules
     * directory does not exist, rather than throwing.
     *
     * @return void
     */
    public function testDiscoverModulesReturnsEmptyWhenModulesDirectoryMissing(): void
    {
        $baseWithoutModules = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR . 'no_modules_' . uniqid();

        mkdir($baseWithoutModules, 0755, true);

        $this->resetModulesState();
        Modules::setBasePath($baseWithoutModules);

        self::assertSame([], Modules::getModules());
        self::assertSame([], Modules::routePaths());

        rmdir($baseWithoutModules);
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

        self::assertArrayHasKey('gamma', $views);
        self::assertArrayNotHasKey('Gamma', $views);
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

        (new ModuleManifest($cachePath, $this->tempDir . DIRECTORY_SEPARATOR . 'modules'))
            ->write(static fn (): array => ['cached_module' => $fakePath]);

        $routes = Modules::routePaths();

        // The cached_module key should be present instead of alpha/beta from
        // discovery
        self::assertArrayNotHasKey('alpha', $routes);
        self::assertArrayNotHasKey('beta', $routes);

        // The cached_module does not have routes.php, so it should be filtered
        // out, but the key should have been attempted - confirming cache was
        // used
        $views = Modules::viewPaths();

        // Verify that 'alpha' (from discovery) is NOT present, confirming cache
        // was used
        self::assertArrayNotHasKey('alpha', $views);
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

        self::assertFileDoesNotExist($cachePath);

        $routes = Modules::routePaths();

        self::assertArrayHasKey('alpha', $routes);
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
        self::assertArrayHasKey('alpha', $routes);
        self::assertArrayHasKey('alpha', $views);
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

        self::assertArrayHasKey('alpha', $listeners);
        self::assertArrayNotHasKey('beta', $listeners);
        self::assertStringContainsString('Listeners', $listeners['alpha']);
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

        self::assertArrayHasKey('alpha', $commands);
        self::assertArrayNotHasKey('beta', $commands);
        self::assertStringContainsString('Commands', $commands['alpha']);
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

        self::assertArrayHasKey('alpha', $schedules);
        self::assertArrayNotHasKey('beta', $schedules);
        self::assertStringContainsString('schedule.php', $schedules['alpha']);
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

        self::assertNotNull($path);
        self::assertStringContainsString('alpha', $path);
    }

    /**
     * Test that getModule returns null for an unknown module.
     *
     * @return void
     */
    public function testGetModuleReturnsNullForUnknownModule(): void
    {
        Modules::setBasePath($this->tempDir);

        self::assertNull(Modules::getModule('nonexistent'));
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

        self::assertSame($lower, $upper);
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

        self::assertArrayHasKey('alpha', $modules);
        self::assertArrayHasKey('beta', $modules);
        self::assertArrayNotHasKey('.hidden', $modules);
        self::assertCount(2, $modules);
    }

    /**
     * Test that the MODULE_SEPARATOR constant has the correct value.
     *
     * @return void
     */
    public function testModuleSeparatorConstant(): void
    {
        self::assertSame('::', Modules::MODULE_SEPARATOR);
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

        self::assertFileExists($cachePath);

        // The temp file should not persist after a successful cache
        self::assertFileDoesNotExist($cachePath . '.tmp');
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

        self::assertArrayHasKey('alpha', $routes);
    }

    /**
     * Test that cache flushes the in-memory state so a read taken after caching
     * reflects modules added since the state was first primed.
     *
     * @return void
     */
    public function testCacheFlushesInMemoryState(): void
    {
        Modules::setBasePath($this->tempDir);

        // Prime the in-memory module and resolved-path state.
        Modules::viewPaths();

        // Add a new module after the state has been primed.
        mkdir(
            $this->tempDir
                . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . 'epsilon'
                . DIRECTORY_SEPARATOR . 'Resources'
                . DIRECTORY_SEPARATOR . 'views',
            0755,
            true,
        );

        // The cache() call must flush primed state before writing discovery, so
        // the subsequent read reflects epsilon.
        Modules::cache();

        self::assertArrayHasKey('epsilon', Modules::viewPaths());
    }

    /**
     * Test that getModules memoizes its result so modules added after the first
     * resolution are not picked up without a flush.
     *
     * @return void
     */
    public function testGetModulesMemoizesResult(): void
    {
        Modules::setBasePath($this->tempDir);

        $first = Modules::getModules();

        // Add a module after the first resolution has been memoized.
        mkdir(
            $this->tempDir
                . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . 'zeta',
            0755,
            true,
        );

        $second = Modules::getModules();

        self::assertSame($first, $second);
        self::assertArrayNotHasKey('zeta', $second);
    }

    /**
     * Test that resourcePath treats only the first :: as the module boundary,
     * so additional separators do not change which module resolves.
     *
     * @return void
     */
    public function testResourcePathUsesOnlyFirstSeparatorAsModuleBoundary(): void
    {
        Modules::setBasePath($this->tempDir);

        $path = Modules::resourcePath('alpha::nested::asset');

        $expected = realpath(
            $this->tempDir
                . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . 'alpha'
                . DIRECTORY_SEPARATOR . 'Resources',
        );

        self::assertSame($expected, $path);
    }

    /**
     * Test that an empty module prefix (a leading ::) falls back to the default
     * module rather than resolving an empty module name.
     *
     * @return void
     */
    public function testResourcePathWithEmptyModulePrefixFallsBackToDefaultModule(): void
    {
        mkdir(
            $this->tempDir
                . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . 'foundation'
                . DIRECTORY_SEPARATOR . 'Resources',
            0755,
            true,
        );

        Modules::setBasePath($this->tempDir);

        $path = Modules::resourcePath('::views');

        $expected = realpath(
            $this->tempDir
                . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . 'foundation'
                . DIRECTORY_SEPARATOR . 'Resources',
        );

        self::assertSame($expected, $path);
    }

    /**
     * Test that resolved paths are memoized, so a target sub-path created after
     * the first resolution does not retroactively appear.
     *
     * @return void
     */
    public function testResolvePathsCachesAcrossSubPathCreation(): void
    {
        Modules::setBasePath($this->tempDir);

        // Beta exists but has no views directory, so it is filtered out.
        self::assertArrayNotHasKey('beta', Modules::viewPaths());

        // Create beta's views directory after the paths have been resolved.
        mkdir(
            $this->tempDir
                . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . 'beta'
                . DIRECTORY_SEPARATOR . 'Resources'
                . DIRECTORY_SEPARATOR . 'views',
            0755,
            true,
        );

        // The resolved paths are memoized, so beta must remain absent.
        self::assertArrayNotHasKey('beta', Modules::viewPaths());
    }

    /**
     * Create a non-writable bootstrap/cache directory and return its path.
     *
     * @return string
     */
    private function createReadOnlyCacheDirectory(): string
    {
        $base     = $this->tempDir . DIRECTORY_SEPARATOR . 'readonly';
        $cacheDir = $base
            . DIRECTORY_SEPARATOR . 'bootstrap'
            . DIRECTORY_SEPARATOR . 'cache';

        mkdir($base . DIRECTORY_SEPARATOR . 'modules', 0755, true);
        mkdir($cacheDir, 0755, true);
        chmod($cacheDir, 0444);

        return $cacheDir;
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

        $this->createFile('modules/alpha/Http/routes.php');
        $this->createFile('modules/alpha/Console/schedule.php');

        // Beta module - minimal, no routes/views/lang
        $this->createDirectory('modules/beta/Resources');
        $this->createDirectory('modules/beta/Http');

        // Dot directory - should be skipped
        $this->createDirectory('modules/.hidden');

        // Bootstrap cache directory
        $this->createDirectory('bootstrap/cache');
    }
}
