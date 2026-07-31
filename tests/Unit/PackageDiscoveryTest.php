<?php

declare(strict_types = 1);

namespace Tests\Unit;

use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SineMacula\Laravel\Modules\Providers\ModuleServiceProvider;

/**
 * Unit tests for the package discovery declaration.
 *
 * A consumer registers nothing by hand, so the providers named in composer.json
 * are the whole of the installation contract. Renaming or moving one without
 * updating that declaration leaves every consumer with no module support and no
 * failing test anywhere else in this suite.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
#[CoversClass(ModuleServiceProvider::class)]
final class PackageDiscoveryTest extends TestCase
{
    /**
     * Test that every declared provider is a service provider that exists.
     *
     * @return void
     */
    public function testDeclaredProvidersAreResolvableServiceProviders(): void
    {
        $providers = self::declaredProviders();

        self::assertNotEmpty($providers);

        foreach ($providers as $provider) {

            self::assertTrue(
                class_exists($provider),
                'Declared provider [' . $provider . '] does not exist.',
            );

            self::assertTrue(
                is_subclass_of($provider, ServiceProvider::class),
                'Declared provider [' . $provider . '] is not a service provider.',
            );
        }
    }

    /**
     * Test that the module service provider is the one discovery registers.
     *
     * @return void
     */
    public function testTheModuleServiceProviderIsDeclared(): void
    {
        self::assertContains(ModuleServiceProvider::class, self::declaredProviders());
    }

    /**
     * Return the provider class names declared for package discovery.
     *
     * @return list<string>
     */
    private static function declaredProviders(): array
    {
        $manifest = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        // @phpstan-ignore offsetAccess.nonOffsetAccessible (decoded json is mixed; the assertions below validate the shape)
        $providers = $manifest['extra']['laravel']['providers'] ?? [];

        self::assertIsArray($providers);
        self::assertContainsOnlyString($providers);

        return array_values($providers);
    }
}
