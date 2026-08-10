<?php

declare(strict_types = 1);

namespace Tests\EndToEnd;

use App\Billing\Models\Invoice;
use App\Billing\Models\Receipt;
use App\Billing\Statement;
use Database\Factories\Billing\CustomReceiptFactory;
use Database\Factories\Billing\InvoiceFactory;
use Database\Factories\Billing\StatementFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\Laravel\Modules\Database\FactoryResolver;
use SineMacula\Laravel\Modules\Providers\ModuleServiceProvider;
use Tests\EndToEndTestCase;

/**
 * End-to-end tests for factory resolution against a real modular application.
 *
 * The unit tier calls the resolver directly; these boot the application and go
 * through the model, so a resolver that is never registered fails here.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
#[CoversClass(FactoryResolver::class)]
#[CoversClass(ModuleServiceProvider::class)]
final class ModuleFactoryResolutionTest extends EndToEndTestCase
{
    /**
     * Test that a module model reaches its factory without per-model wiring.
     *
     * @return void
     */
    public function testAModuleModelResolvesItsFactory(): void
    {
        $this->modularApplication();

        self::assertInstanceOf(InvoiceFactory::class, Invoice::factory());
        self::assertInstanceOf(StatementFactory::class, Statement::factory());
    }

    /**
     * Test that a factory resolves back to the model it builds, which is what a
     * factory declaring no model relies on.
     *
     * @return void
     */
    public function testAModuleFactoryBuildsTheRightModel(): void
    {
        $this->modularApplication();

        self::assertInstanceOf(Invoice::class, (new InvoiceFactory)->makeOne());
        self::assertInstanceOf(Statement::class, (new StatementFactory)->makeOne());
    }

    /**
     * Test that an explicit UseFactory attribute still wins, so a model that
     * names its factory is unaffected by the resolver.
     *
     * @return void
     */
    public function testAnExplicitFactoryAttributeTakesPrecedence(): void
    {
        $this->modularApplication();

        self::assertInstanceOf(CustomReceiptFactory::class, Receipt::factory());
    }

    /**
     * Test that resolution fails without the package registering the resolver,
     * so the assertions above cannot pass for an unrelated reason.
     *
     * @return void
     */
    public function testResolutionDependsOnThePackageRegisteringTheResolver(): void
    {
        $this->modularApplication();

        Factory::flushState();

        self::assertSame(
            'Database\Factories\Billing\Models\InvoiceFactory',
            Factory::resolveFactoryName(Invoice::class),
        );
    }
}
