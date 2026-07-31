<?php

declare(strict_types = 1);

namespace SineMacula\Laravel\Modules\Providers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;
use SineMacula\Laravel\Modules\Configuration\Modules;
use SineMacula\Laravel\Modules\Console\Commands\ModuleCacheCommand;
use SineMacula\Laravel\Modules\Console\Commands\ModuleClearCommand;
use SineMacula\Laravel\Modules\Console\Commands\ModuleListCommand;
use SineMacula\Laravel\Modules\Console\Commands\ModuleMakeCommand;

/**
 * Module service provider.
 *
 * Registers module views, translations, and optimization commands for the
 * modular architecture.
 *
 * @inheritable Subclassed by the test spy to record protected boot calls.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 */
class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register any module services.
     *
     * @return void
     *
     * @throws \SineMacula\Laravel\Modules\Exceptions\ModuleException
     */
    #[\Override]
    public function register(): void
    {
        Modules::setBasePath($this->app->basePath());

        $this->pruneMissingViewPaths();
        $this->registerCommands();
    }

    /**
     * Bootstrap any module services.
     *
     * @return void
     *
     * @throws \SineMacula\Laravel\Modules\Exceptions\ModuleException
     */
    public function boot(): void
    {
        $this->registerViews();
        $this->registerTranslations();

        $this->optimizes(
            optimize: 'module:cache',
            clear   : 'module:clear',
            key     : 'modules',
        );
    }

    /**
     * Register the module views.
     *
     * @return void
     *
     * @throws \SineMacula\Laravel\Modules\Exceptions\ModuleException
     */
    private function registerViews(): void
    {
        foreach (Modules::viewPaths() as $module => $path) {
            $this->loadViewsFrom($path, $module);
        }
    }

    /**
     * Register the module translation files.
     *
     * @return void
     *
     * @throws \SineMacula\Laravel\Modules\Exceptions\ModuleException
     */
    private function registerTranslations(): void
    {
        foreach (Modules::langPaths() as $module => $path) {
            $this->loadTranslationsFrom($path, $module);
        }
    }

    /**
     * Remove the configured view paths that do not exist.
     *
     * View caching reads every configured path and fails on any that is
     * missing. The default module ships without a Resources directory, so the
     * resource path falls back to the framework default, which a modular
     * application has no reason to create.
     *
     * @return void
     */
    private function pruneMissingViewPaths(): void
    {
        $config = $this->app->make(ConfigRepository::class);

        $paths = array_filter(
            (array) $config->get('view.paths'),
            static fn (mixed $path): bool => is_string($path) && is_dir($path),
        );

        $config->set('view.paths', array_values($paths));
    }

    /**
     * Register the package console commands.
     *
     * @return void
     */
    private function registerCommands(): void
    {
        $this->commands([
            ModuleCacheCommand::class,
            ModuleClearCommand::class,
            ModuleListCommand::class,
            ModuleMakeCommand::class,
        ]);
    }
}
