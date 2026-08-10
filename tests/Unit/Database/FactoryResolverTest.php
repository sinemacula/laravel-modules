<?php

declare(strict_types = 1);

namespace Tests\Unit\Database;

use Database\Factories\Billing\InvoiceFactory;
use Database\Factories\Billing\Models\ArchiveFactory;
use Database\Factories\Billing\StatementFactory;
use Database\Factories\InvoiceFactory as RootInvoiceFactory;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Factories\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SineMacula\Laravel\Modules\Database\FactoryResolver;

/**
 * Unit tests for the FactoryResolver class.
 *
 * The resolver reads the application namespace from the container, so every
 * test runs against a container it controls. With nothing bound, resolution
 * falls back to the framework's own default of App\, which is what the fixture
 * classes are namespaced under.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @SuppressWarnings("php:S1192")
 *
 * @internal
 */
#[CoversClass(FactoryResolver::class)]
final class FactoryResolverTest extends TestCase
{
    /**
     * Set up the test environment.
     *
     * @return void
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Container::setInstance(new Container);
    }

    /**
     * Tear down the test environment.
     *
     * @return void
     */
    #[\Override]
    protected function tearDown(): void
    {
        Container::setInstance(null);

        Factory::flushState();

        parent::tearDown();
    }

    /**
     * Test that a module model resolves to a factory scoped by module alone,
     * whether or not the model sits in the module's Models directory.
     *
     * @return void
     */
    public function testModuleModelsResolveToAModuleScopedFactory(): void
    {
        self::assertSame(
            InvoiceFactory::class,
            FactoryResolver::factoryNameFor('App\Billing\Models\Invoice'),
        );

        self::assertSame(
            StatementFactory::class,
            FactoryResolver::factoryNameFor('App\Billing\Statement'),
        );
    }

    /**
     * Test that a model nested below the module's Models directory keeps the
     * nesting in its factory name.
     *
     * @return void
     */
    public function testNestedModuleModelsKeepTheirNesting(): void
    {
        self::assertSame(
            'Database\Factories\Billing\Sub\ThingFactory',
            FactoryResolver::factoryNameFor('App\Billing\Models\Sub\Thing'),
        );
    }

    /**
     * Return the model names that resolve without the module scheme applying.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function unscopedModelProvider(): iterable
    {
        yield from [
            'the application Models directory'         => ['App\Models\User', 'Database\Factories\UserFactory'],
            'a model at the application root'          => ['App\User', 'Database\Factories\UserFactory'],
            'a model outside the application'          => ['Vendor\Pkg\Models\Thing', 'Database\Factories\Vendor\Pkg\Models\ThingFactory'],
            'a class named after the Models directory' => ['App\Billing\Models', 'Database\Factories\Billing\ModelsFactory'],
        ];
    }

    /**
     * Test that models outside the module scheme resolve exactly as the
     * framework would resolve them.
     *
     * @param  string  $model
     * @param  string  $expected
     * @return void
     */
    #[DataProvider('unscopedModelProvider')]
    public function testUnscopedModelsKeepTheFrameworkDefault(string $model, string $expected): void
    {
        self::assertSame($expected, FactoryResolver::factoryNameFor($model));
    }

    /**
     * Test that a factory already sitting where the framework would put one is
     * still used, so adopting the package does not orphan existing factories.
     *
     * @return void
     */
    public function testAnExistingDefaultFactoryIsPreferredOverAMissingScopedOne(): void
    {
        self::assertSame(
            ArchiveFactory::class,
            FactoryResolver::factoryNameFor('App\Billing\Models\Archive'),
        );
    }

    /**
     * Test that the module-scoped factory wins when a factory exists in both
     * places, so the documented location is the one that takes effect.
     *
     * @return void
     */
    public function testTheModuleScopedFactoryWinsWhenBothExist(): void
    {
        self::assertSame(
            'Database\Factories\Billing\NoteFactory',
            FactoryResolver::factoryNameFor('App\Billing\Models\Note'),
        );
    }

    /**
     * Test that a module whose name merely begins with the Models directory is
     * still treated as a module.
     *
     * @return void
     */
    public function testAModuleNamedAfterTheModelsDirectoryIsStillAModule(): void
    {
        self::assertSame(
            'Database\Factories\Modelling\Models\ReportFactory',
            FactoryResolver::factoryNameFor('App\Modelling\Models\Report'),
        );
    }

    /**
     * Test that a model with no factory anywhere reports the module-scoped
     * name, so the resulting error names the documented location.
     *
     * @return void
     */
    public function testAMissingFactoryReportsTheModuleScopedName(): void
    {
        self::assertSame(
            'Database\Factories\Billing\MissingFactory',
            FactoryResolver::factoryNameFor('App\Billing\Models\Missing'),
        );
    }

    /**
     * Test that a module-scoped factory resolves back to its model, in either
     * of the two places a module model may live.
     *
     * @return void
     */
    public function testAModuleFactoryResolvesBackToItsModel(): void
    {
        self::assertSame('App\Billing\Models\Invoice', FactoryResolver::modelNameFor(new InvoiceFactory));
        self::assertSame('App\Billing\Statement', FactoryResolver::modelNameFor(new StatementFactory));
    }

    /**
     * Test that a factory whose model does not exist under either module
     * convention falls back to the framework's own guess.
     *
     * @return void
     */
    public function testAFactoryWithNoModuleModelFallsBackToTheFrameworkGuess(): void
    {
        self::assertSame('App\Archive', FactoryResolver::modelNameFor(new ArchiveFactory));
    }

    /**
     * Test that a factory outside the module scheme resolves through the
     * application's Models directory, as the framework does.
     *
     * @return void
     */
    public function testAnUnscopedFactoryResolvesThroughTheApplicationModelsDirectory(): void
    {
        $application = self::createStub(Application::class);

        $application->method('getNamespace')->willReturn('App\Billing\\');

        Container::getInstance()->instance(Application::class, $application);

        self::assertSame(
            'App\Billing\Models\Invoice',
            FactoryResolver::modelNameFor(new RootInvoiceFactory),
        );
    }

    /**
     * Test that registering the resolver takes effect on the factory itself, in
     * both directions.
     *
     * @return void
     */
    public function testRegisterInstallsBothResolversOnTheFactory(): void
    {
        self::assertSame(
            'Database\Factories\Billing\Models\InvoiceFactory',
            Factory::resolveFactoryName('App\Billing\Models\Invoice'),
        );

        FactoryResolver::register();

        self::assertSame(InvoiceFactory::class, Factory::resolveFactoryName('App\Billing\Models\Invoice'));
        self::assertSame('App\Billing\Models\Invoice', (new InvoiceFactory)->modelName());
    }
}
