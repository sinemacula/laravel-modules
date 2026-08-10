<?php

declare(strict_types = 1);

namespace SineMacula\Laravel\Modules\Database;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Resolves factory names for models that live inside a module.
 *
 * The framework maps a model to a factory by stripping the application
 * namespace, so a modular model resolves through the directory it sits in:
 * App\Billing\Models\Invoice becomes Database\Factories\Billing\Models\
 * InvoiceFactory. This resolver maps the module to a single directory instead,
 * giving Database\Factories\Billing\InvoiceFactory, and leaves everything else
 * to the framework default.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 */
final class FactoryResolver
{
    /** @var string The directory a model is conventionally namespaced under. */
    private const string MODELS_DIRECTORY = 'Models';

    /** @var string The suffix every factory class name carries. */
    private const string FACTORY_SUFFIX = 'Factory';

    /**
     * Register the resolver with the factory.
     *
     * @return void
     */
    public static function register(): void
    {
        Factory::guessFactoryNamesUsing(self::factoryNameFor(...));
        Factory::guessModelNamesUsing(self::modelNameFor(...));
    }

    /**
     * Return the factory class name for the given model.
     *
     * An application already holding a factory where the framework default puts
     * one keeps using it, so adopting the package does not orphan existing
     * factories. Otherwise the module-scoped name is returned, including when
     * neither exists, so the resulting error names the documented location.
     *
     * @param  class-string  $model
     * @return string
     */
    public static function factoryNameFor(string $model): string
    {
        $scoped = self::moduleFactoryName($model);

        if ($scoped === null) {
            return self::defaultFactoryName($model);
        }

        if (class_exists($scoped)) {
            return $scoped;
        }

        $default = self::defaultFactoryName($model);

        return class_exists($default) ? $default : $scoped;
    }

    /**
     * Return the model class name for the given factory.
     *
     * @param  \Illuminate\Database\Eloquent\Factories\Factory<\Illuminate\Database\Eloquent\Model>  $factory
     * @return string
     */
    public static function modelNameFor(Factory $factory): string
    {
        foreach (self::moduleModelNames($factory) as $candidate) {
            if (class_exists($candidate)) {
                return $candidate;
            }
        }

        return self::defaultModelName($factory);
    }

    /**
     * Return the module-scoped factory name for the given model.
     *
     * A model is module-scoped when it sits below a directory of the modules
     * directory. The module's own Models directory is optional and drops out of
     * the factory name, so both App\Billing\Invoice and
     * App\Billing\Models\Invoice resolve to the same factory.
     *
     * @param  string  $model
     * @return string|null
     */
    private static function moduleFactoryName(string $model): ?string
    {
        $namespace = self::appNamespace();

        if (!str_starts_with($model, $namespace)) {
            return null;
        }

        $segments = explode('\\', substr($model, strlen($namespace)));

        // The application's own Models directory is not a module.
        if (count($segments) < 2 || $segments[0] === self::MODELS_DIRECTORY) {
            return null;
        }

        $module = array_shift($segments);

        if ($segments[0] === self::MODELS_DIRECTORY) {
            array_shift($segments);
        }

        return $segments === []
            ? null
            : Factory::$namespace . $module . '\\' . implode('\\', $segments) . self::FACTORY_SUFFIX;
    }

    /**
     * Return the model names a module-scoped factory could describe.
     *
     * @param  \Illuminate\Database\Eloquent\Factories\Factory<\Illuminate\Database\Eloquent\Model>  $factory
     * @return list<string>
     */
    private static function moduleModelNames(Factory $factory): array
    {
        $segments = explode('\\', Str::replaceFirst(Factory::$namespace, '', $factory::class));

        if (count($segments) < 2) {
            return [];
        }

        $module = array_shift($segments);
        $name   = Str::replaceLast(self::FACTORY_SUFFIX, '', implode('\\', $segments));

        $namespace = self::appNamespace() . $module . '\\';

        return [
            $namespace . self::MODELS_DIRECTORY . '\\' . $name,
            $namespace . $name,
        ];
    }

    /**
     * Return the factory name the framework would resolve for the given model.
     *
     * @param  string  $model
     * @return string
     */
    private static function defaultFactoryName(string $model): string
    {
        $namespace = self::appNamespace();

        $model = Str::startsWith($model, $namespace . self::MODELS_DIRECTORY . '\\')
            ? Str::after($model, $namespace . self::MODELS_DIRECTORY . '\\')
            : Str::after($model, $namespace);

        return Factory::$namespace . $model . self::FACTORY_SUFFIX;
    }

    /**
     * Return the model name the framework would resolve for the given factory.
     *
     * @param  \Illuminate\Database\Eloquent\Factories\Factory<\Illuminate\Database\Eloquent\Model>  $factory
     * @return string
     */
    private static function defaultModelName(Factory $factory): string
    {
        $namespaced = Str::replaceLast(
            self::FACTORY_SUFFIX,
            '',
            Str::replaceFirst(Factory::$namespace, '', $factory::class),
        );

        $namespace = self::appNamespace();

        return class_exists($namespace . self::MODELS_DIRECTORY . '\\' . $namespaced)
            ? $namespace . self::MODELS_DIRECTORY . '\\' . $namespaced
            : $namespace . Str::replaceLast(self::FACTORY_SUFFIX, '', class_basename($factory));
    }

    /**
     * Return the application namespace.
     *
     * @return string
     */
    private static function appNamespace(): string
    {
        try {
            return Container::getInstance()->make(Application::class)->getNamespace();
        } catch (\Throwable) {
            return 'App\\';
        }
    }
}
