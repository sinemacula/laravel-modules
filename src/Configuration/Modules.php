<?php

namespace SineMacula\Laravel\Modules\Configuration;

use SineMacula\Laravel\Modules\Configuration\Enums\ModulePath;

/**
 * Manages the discovery, caching, and retrieval of application modules.
 *
 * Serves as a central point for managing modules within the application,
 * enabling efficient and lazy loading of module information on demand. Module
 * discovery is performed only once and results are cached for subsequent
 * access.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @SuppressWarnings("php:S1448")
 */
class Modules
{
    /** @var string The separator used in module-scoped paths. */
    public const string MODULE_SEPARATOR = '::';

    /** @var string The default module for resource resolution. */
    private const string DEFAULT_MODULE = 'foundation';

    /** @var string The base path to the root application directory. */
    private static string $basePath;

    /** @var array<string, string>|null The discovered module paths keyed by module name. */
    private static ?array $modules = null;

    /** @var array<string, array<string, string>> Resolved paths keyed by path type. */
    private static array $resolvedPaths = [];

    /**
     * Set the base path for the module resolver.
     *
     * @param  string  $path
     * @return void
     */
    public static function setBasePath(string $path): void
    {
        self::$basePath = $path;
    }

    /**
     * Cache the application modules.
     *
     * @return void
     *
     * @throws \RuntimeException
     *
     * @SuppressWarnings("php:S112")
     */
    public static function cache(): void
    {
        self::flush();

        $content   = "<?php\nreturn " . var_export(self::discoverModules(), true) . ';';
        $cachePath = self::cachePath();
        $tempPath  = $cachePath . '.tmp';

        if (file_put_contents($tempPath, $content) === false) {
            throw new \RuntimeException('Failed to write cache file at ' . $cachePath . '.');
        }

        if (!rename($tempPath, $cachePath)) { // @codeCoverageIgnoreStart

            @unlink($tempPath);

            throw new \RuntimeException('Failed to write cache file at ' . $cachePath . '.');
        } // @codeCoverageIgnoreEnd
    }

    /**
     * Return the path to the modules directory.
     *
     * @return string
     */
    public static function modulesPath(): string
    {
        return self::buildPath(ModulePath::MODULES->value);
    }

    /**
     * Clear the cached application modules.
     *
     * @return void
     */
    public static function clearCache(): void
    {
        @unlink(self::cachePath());

        self::flush();
    }

    /**
     * Get the path to a module's resources directory.
     *
     * A module can be specified using the {module}::{path} format. When no
     * module prefix is present, the default module is used.
     *
     * @param  string  $path
     * @return string
     */
    public static function resourcePath(string $path = ''): string
    {
        $module = self::extractModuleFromPath($path)
            ?? self::DEFAULT_MODULE;

        return self::resolvePaths(
            ModulePath::RESOURCES->value,
        )[$module] ?? '';
    }

    /**
     * Return the module paths to the routes.
     *
     * @return array<string, string>
     */
    public static function routePaths(): array
    {
        return self::resolvePaths(ModulePath::ROUTES->value);
    }

    /**
     * Return the module paths to the views directory.
     *
     * @return array<string, string>
     */
    public static function viewPaths(): array
    {
        return self::resolvePaths(ModulePath::VIEWS->value);
    }

    /**
     * Return the module paths to the translation files directory.
     *
     * @return array<string, string>
     */
    public static function langPaths(): array
    {
        return self::resolvePaths(ModulePath::LANG->value);
    }

    /**
     * Return the module paths to the event listeners directory.
     *
     * @return array<string, string>
     */
    public static function listenerPaths(): array
    {
        return self::resolvePaths(ModulePath::LISTENERS->value);
    }

    /**
     * Return the module paths to the console commands directory.
     *
     * @return array<string, string>
     */
    public static function commandPaths(): array
    {
        return self::resolvePaths(ModulePath::COMMANDS->value);
    }

    /**
     * Return the module paths to the schedule files.
     *
     * @return array<string, string>
     */
    public static function schedulePaths(): array
    {
        return self::resolvePaths(ModulePath::SCHEDULES->value);
    }

    /**
     * Return an array of each of the module paths.
     *
     * @return array<string, string>
     */
    public static function getModules(): array
    {
        return self::$modules ??= self::resolveModules();
    }

    /**
     * Return the path for a single module by name.
     *
     * @param  string  $name
     * @return string|null
     */
    public static function getModule(string $name): ?string
    {
        return self::getModules()[strtolower($name)] ?? null;
    }

    /**
     * Flush all in-memory state.
     *
     * @return void
     */
    private static function flush(): void
    {
        self::$modules       = null;
        self::$resolvedPaths = [];
    }

    /**
     * Auto-discover the modules within the application.
     *
     * @return array<string, string>
     */
    private static function discoverModules(): array
    {
        $directory = new \DirectoryIterator(self::modulesPath());
        $modules   = [];

        foreach ($directory as $fileInfo) {

            if ($fileInfo->isDir() && !$fileInfo->isDot()) {
                $modules[strtolower($fileInfo->getFilename())] = $fileInfo->getRealPath();
            }
        }

        return $modules;
    }

    /**
     * Build a path relative to the base path.
     *
     * @param  string  $path
     * @return string
     *
     * @throws \RuntimeException
     *
     * @SuppressWarnings("php:S112")
     */
    private static function buildPath(string $path): string
    {
        if (!isset(self::$basePath)) {
            throw new \RuntimeException('No base path has been set.');
        }

        return self::$basePath . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * Temporary method to test PHPStan centralised config in Qlty Cloud.
     *
     * @return string
     */
    public static function testPHPStanConfig(): string
    {
        return 123;
    }

    /**
     * Return the path to the cache file.
     *
     * @return string
     */
    private static function cachePath(): string
    {
        return self::buildPath(ModulePath::CACHE->value);
    }

    /**
     * Extract the module name from the given path.
     *
     * Parses the {module}::{path} format and returns the module segment, or
     * null if no separator is present.
     *
     * @param  string  $path
     * @return string|null
     */
    private static function extractModuleFromPath(
        string $path,
    ): ?string {
        $parts = explode(self::MODULE_SEPARATOR, $path, 2);

        if (count($parts) === 2 && $parts[0] !== '') {
            return strtolower($parts[0]);
        }

        return null;
    }

    /**
     * Resolve and cache paths to a specific file or directory within each
     * module.
     *
     * Results are cached per path type to avoid repeated filesystem calls to
     * realpath().
     *
     * @param  string  $path
     * @return array<string, string>
     */
    private static function resolvePaths(string $path): array
    {
        if (isset(self::$resolvedPaths[$path])) {
            return self::$resolvedPaths[$path];
        }

        $paths = [];

        foreach (self::getModules() as $module => $modulePath) {
            $paths[$module] = $modulePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
        }

        return self::$resolvedPaths[$path] = array_filter(
            array_map('realpath', $paths),
            static fn (false|string $path): bool => $path !== false,
        );
    }

    /**
     * Resolve the modules from cache or discovery.
     *
     * @return array<string, string>
     */
    private static function resolveModules(): array
    {
        return self::loadModulesFromCache() ?? self::discoverModules();
    }

    /**
     * Attempt to load the modules from the cache.
     *
     * @return array<string, string>|null
     *
     * @SuppressWarnings("php:S4833")
     * @SuppressWarnings("php:S2003")
     */
    private static function loadModulesFromCache(): ?array
    {
        if (!file_exists(self::cachePath())) {
            return null;
        }

        $modules = require self::cachePath();

        return is_array($modules) ? $modules : null; // @phpstan-ignore return.type
    }
}
