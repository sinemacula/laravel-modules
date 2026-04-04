<?php

namespace SineMacula\Laravel\Modules\Providers;

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
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 */
class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register any module services.
     *
     * @return void
     */
    #[\Override]
    public function register(): void
    {
        $this->registerCommands();
    }

    /**
     * Bootstrap any module services.
     *
     * @return void
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
     */
    private function registerTranslations(): void
    {
        foreach (Modules::langPaths() as $module => $path) {
            $this->loadTranslationsFrom($path, $module);
        }
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
