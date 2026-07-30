<?php

declare(strict_types = 1);

namespace Tests\EndToEnd;

use App\Billing\Events\InvoicePaid;
use App\Billing\Listeners\RecordInvoice;
use App\Reporting\Events\ReportRequested;
use App\Reporting\Listeners\RecordReport;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\View\Factory;
use Tests\EndToEndTestCase;

/**
 * The end-result matrix every boot mode must satisfy.
 *
 * Cold and cached boots inherit these assertions so the two modes cannot drift
 * apart.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
abstract class ModuleIntegrationTestCase extends EndToEndTestCase
{
    /**
     * Test that the application is booted through the package rather than the
     * framework default.
     *
     * @return void
     */
    public function testApplicationBootsThroughThePackageApplication(): void
    {
        $app = $this->modularApplication();

        static::assertSame(
            $this->fixtureAppPath . DIRECTORY_SEPARATOR . 'modules',
            $app->path(),
        );

        static::assertSame('App\\', $app->getNamespace());
    }

    /**
     * Test that each module's routes file is registered and served.
     *
     * @return void
     */
    public function testModuleRoutesAreServed(): void
    {
        $this->get('/billing/invoice')
            ->assertOk()
            ->assertSee('billing-invoice-ok');

        $this->get('/reporting/summary')
            ->assertOk()
            ->assertSee('reporting-summary-ok');

        $this->get('/health')->assertOk();
    }

    /**
     * Test that each module's views are registered under its own namespace.
     *
     * @return void
     */
    public function testModuleViewsAreRendered(): void
    {
        $views = $this->modularApplication()->make(Factory::class);

        static::assertSame(
            'billing-view:INV-1',
            trim($views->make('billing::invoice', ['reference' => 'INV-1'])->render()),
        );

        static::assertSame(
            'reporting-view:Q1',
            trim($views->make('reporting::summary', ['period' => 'Q1'])->render()),
        );
    }

    /**
     * Test that each module's translations are registered under its own
     * namespace.
     *
     * @return void
     */
    public function testModuleTranslationsAreResolved(): void
    {
        $translator = $this->modularApplication()->make(Translator::class);

        static::assertSame('billing-lang-ok', $translator->get('billing::messages.greeting'));
        static::assertSame('reporting-lang-ok', $translator->get('reporting::messages.greeting'));
    }

    /**
     * Test that each module's console commands are discovered and invokable.
     *
     * @return void
     */
    public function testModuleCommandsAreInvokable(): void
    {
        $this->artisan('billing:ping')
            ->expectsOutput('billing-command-ok') // @phpstan-ignore method.nonObject (untyped pending-command API)
            ->assertExitCode(0);

        $this->artisan('reporting:ping')
            ->expectsOutput('reporting-command-ok') // @phpstan-ignore method.nonObject (untyped pending-command API)
            ->assertExitCode(0);
    }

    /**
     * Test that each module's schedule file is registered.
     *
     * @return void
     */
    public function testModuleSchedulesAreRegistered(): void
    {
        $scheduled = implode(' ', array_map(
            static fn (Event $event): string => $event->command ?? '',
            $this->modularApplication()->make(Schedule::class)->events(),
        ));

        static::assertStringContainsString('billing:ping', $scheduled);
        static::assertStringContainsString('reporting:ping', $scheduled);
    }

    /**
     * Test that each module's listeners are auto-discovered and receive
     * dispatched events.
     *
     * @return void
     */
    public function testModuleListenersReceiveDispatchedEvents(): void
    {
        RecordInvoice::$seen = [];
        RecordReport::$seen  = [];

        $events = $this->modularApplication()->make(Dispatcher::class);

        $events->dispatch(new InvoicePaid);
        $events->dispatch(new ReportRequested);

        static::assertSame(['billing-event-ok'], RecordInvoice::$seen);
        static::assertSame(['reporting-event-ok'], RecordReport::$seen);
    }
}
