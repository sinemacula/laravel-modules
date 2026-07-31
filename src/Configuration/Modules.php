<?php

declare(strict_types = 1);

namespace SineMacula\Laravel\Modules\Configuration;

use SineMacula\Laravel\Modules\Configuration\Enums\ModulePath;
use SineMacula\Laravel\Modules\Exceptions\ModuleException;

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
final class Modules
{
    /** @var string The separator used in module-scoped paths. */
    public const string MODULE_SEPARATOR = '::';

    /** @var string The default module for resource resolution. */
    private const string DEFAULT_MODULE = 'foundation';

    /** @var string The base path to the root application directory. */
    private static string $basePath; // @phpstan-ignore sineMacula.mutableStaticProperty (intentional static state for the module facade)

    /** @var array<string, string>|null The discovered module paths keyed by module name. */
    private static ?array $modules = null; // @phpstan-ignore sineMacula.mutableStaticProperty (intentional static state for the module facade)

    /** @var array<string, array<string, string>> Resolved paths keyed by path type. */
    private static array $resolvedPaths = []; // @phpstan-ignore sineMacula.mutableStaticProperty (intentional static state for the module facade)

    /**
     * Set the base path for the module resolver.
     *
     * The path is canonicalised where it resolves, so a symlinked or
     * trailing-slash form agrees with the canonical paths returned by
     * discovery. Memoised state is discarded whenever the resolved path
     * changes; setting the same path again is a no-op. Paths already handed to
     * the application builder are not revisited.
     *
     * @param  string  $path
     * @return void
     */
    public static function setBasePath(string $path): void
    {
        // realpath('') resolves to the working directory rather than failing,
        // so an empty path is kept as given.
        if ($path !== '') {
            $path = realpath($path) ?: $path;
        }

        if ((self::$basePath ?? null) === $path) {
            return;
        }

        self::$basePath = $path;

        self::flush();
    }

    /**
     * Cache the application modules.
     *
     * @return void
     *
     * @throws \SineMacula\Laravel\Modules\Exceptions\ModuleException
     */
    public static function cache(): void
    {
        self::flush();

        self::manifest()->write(static fn (): array => self::discoverModules());
    }

    /**
     * Return the name of the module used to resolve unprefixed resources.
     *
     * @return string
     */
    public static function defaultModule(): string
    {
        return self::DEFAULT_MODULE;
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
     * @return bool true when no cache file remains after the operation
     */
    public static function clearCache(): bool
    {
        $cleared = self::manifest()->delete();

        self::flush();

        return $cleared;
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
     * The map is ordered by module name so that route precedence, command
     * registration and listing output do not depend on the order in which the
     * filesystem happens to return directory entries.
     *
     * @return array<string, string>
     *
     * @throws \SineMacula\Laravel\Modules\Exceptions\ModuleException
     */
    public static function getModules(): array
    {
        if (self::$modules === null) {

            self::$modules = self::manifest()->read() ?? self::discoverModules();

            // Byte-wise, so the order cannot shift with the process locale or
            // with directory names that compare equal numerically.
            ksort(self::$modules, SORT_STRING);
        }

        return self::$modules;
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
     *
     * @throws \SineMacula\Laravel\Modules\Exceptions\ModuleException
     */
    private static function discoverModules(): array
    {
        $path = self::modulesPath();

        if (!is_dir($path)) {
            return [];
        }

        try {
            $directory = new \DirectoryIterator($path);
        } catch (\UnexpectedValueException $exception) {
            throw new ModuleException('Failed to read the modules directory at ' . $path . '.', previous: $exception);
        }

        $modules = [];

        foreach ($directory as $fileInfo) {

            if (!$fileInfo->isDir() || str_starts_with($fileInfo->getFilename(), '.')) {
                continue;
            }

            $name = $fileInfo->getFilename();
            $key  = strtolower($name);

            if (isset($modules[$key])) {
                throw new ModuleException('Duplicate module name [' . $key . '] in ' . $path . '. Module directory names must be unique regardless of case.');
            }

            // Laravel derives command and listener class names from each
            // discovered file's real path, so a module reached through a link
            // registers its resources but none of its classes.
            if ($fileInfo->getRealPath() !== $fileInfo->getPathname()) {
                throw new ModuleException('Module [' . $name . '] in ' . $path . ' does not resolve to a real directory. Symlinked module directories are not supported.');
            }

            $modules[$key] = $fileInfo->getRealPath();
        }

        return $modules;
    }

    /**
     * Build a path relative to the base path.
     *
     * @param  string  $path
     * @return string
     *
     * @throws \SineMacula\Laravel\Modules\Exceptions\ModuleException
     */
    private static function buildPath(string $path): string
    {
        if (!isset(self::$basePath)) {
            throw new ModuleException('No base path has been set.');
        }

        return self::$basePath . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * Return the manifest for the current base path.
     *
     * @return \SineMacula\Laravel\Modules\Configuration\ModuleManifest
     *
     * @throws \SineMacula\Laravel\Modules\Exceptions\ModuleException
     */
    private static function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            self::buildPath(ModulePath::CACHE->value),
            self::modulesPath(),
        );
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
    private static function extractModuleFromPath(string $path): ?string
    {
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
}
