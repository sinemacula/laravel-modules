<?php

declare(strict_types = 1);

namespace Tests\EndToEnd;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\Laravel\Modules\Configuration\Modules;
use Tests\EndToEndTestCase;

/**
 * End-to-end tests for which copy of a module's classes is executed.
 *
 * Each test runs against its own staged copy of the fixture application. The
 * package autoloader maps the fixture namespace to the committed modules, so a
 * staged copy whose classes were not remapped would run the code it was copied
 * from and no test could observe the module it actually staged.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
#[CoversClass(Modules::class)]
final class StagedModuleClassesTest extends EndToEndTestCase
{
    /**
     * Test that a command edited in the staged copy is the one that runs.
     *
     * @return void
     */
    public function testStagedModuleClassesAreExecuted(): void
    {
        $command = $this->fixtureAppPath
            . '/modules/Billing/Console/Commands/BillingPingCommand.php';

        file_put_contents($command, str_replace(
            'billing-command-ok',
            'staged-command-ok',
            (string) file_get_contents($command),
        ));

        $process = $this->runFixtureArtisan(['billing:ping']);

        self::assertSame(
            0,
            $process->getExitCode(),
            $process->getOutput() . $process->getErrorOutput(),
        );

        self::assertStringContainsString('staged-command-ok', $process->getOutput());
    }

    /**
     * Test that a module scaffolded into the staged copy is discovered and its
     * generated class is runnable.
     *
     * @return void
     */
    public function testScaffoldedModuleClassesAreExecuted(): void
    {
        $make = $this->runFixtureArtisan(['module:make', 'Shipping']);

        self::assertSame(
            0,
            $make->getExitCode(),
            $make->getOutput() . $make->getErrorOutput(),
        );

        file_put_contents(
            $this->fixtureAppPath . '/modules/Shipping/Console/Commands/ShippingPingCommand.php',
            <<<'PHP'
                <?php

                namespace App\Shipping\Console\Commands;

                use Illuminate\Console\Command;

                class ShippingPingCommand extends Command
                {
                    protected $signature = 'shipping:ping';

                    public function handle(): int
                    {
                        $this->line('scaffolded-command-ok');

                        return self::SUCCESS;
                    }
                }
                PHP,
        );

        $process = $this->runFixtureArtisan(['shipping:ping']);

        self::assertSame(
            0,
            $process->getExitCode(),
            $process->getOutput() . $process->getErrorOutput(),
        );

        self::assertStringContainsString('scaffolded-command-ok', $process->getOutput());
    }

    /**
     * Stage only the billing module, whose command the first test edits.
     *
     * @return list<string>
     */
    #[\Override]
    protected function fixtureModules(): array
    {
        return ['Billing'];
    }
}
