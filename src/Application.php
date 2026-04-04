<?php

namespace SineMacula\Laravel\Modules;

use Illuminate\Foundation\Application as BaseApplication;
use SineMacula\Laravel\Modules\Configuration\ApplicationBuilder;
use SineMacula\Laravel\Modules\Configuration\Modules;

/**
 * Extend the base Laravel Application for a modularised architecture.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 */
final class Application extends BaseApplication
{
    /**
     * Begin configuring a new Laravel application instance.
     *
     * @param  string|null  $basePath
     * @return \SineMacula\Laravel\Modules\Configuration\ApplicationBuilder
     */
    #[\Override]
    public static function configure(?string $basePath = null): ApplicationBuilder
    {
        $basePath = is_string($basePath) ? $basePath : self::inferBasePath();

        return (new ApplicationBuilder(new self($basePath)))->withModules();
    }

    /**
     * Get the path to the resources directory.
     *
     * Supports module-scoped paths using the {module}::{path} format. When a
     * module prefix is present, the path resolves to that module's Resources
     * directory. Otherwise, the default module is used, falling back to the
     * standard Laravel resources directory.
     *
     * phpcs:disable Squiz.Commenting.FunctionComment.ScalarTypeHintMissing
     *
     * @param  string  $path
     * @return string
     */
    #[\Override]
    public function resourcePath($path = ''): string
    {
        // phpcs:enable
        $modulePath = Modules::resourcePath($path);
        $subPath    = self::stripModulePrefix($path);

        if ($modulePath !== '') {
            return $this->joinPaths($modulePath, $subPath);
        }

        return parent::resourcePath($subPath);
    }

    /**
     * Get the path to the application "app" directory.
     *
     * phpcs:disable Squiz.Commenting.FunctionComment.ScalarTypeHintMissing
     *
     * @param  string  $path
     * @return string
     */
    #[\Override]
    public function path($path = ''): string
    {
        // phpcs:enable
        return $this->joinPaths($this->appPath ?: $this->basePath('modules'), $path);
    }

    /**
     * Strip the module prefix from a path.
     *
     * Removes the {module}:: segment if present, returning only the path
     * portion.
     *
     * @param  string  $path
     * @return string
     */
    private static function stripModulePrefix(string $path): string
    {
        if (str_contains($path, Modules::MODULE_SEPARATOR)) {
            return explode(Modules::MODULE_SEPARATOR, $path, 2)[1];
        }

        return $path;
    }
}
