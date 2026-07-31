<?php

declare(strict_types = 1);

namespace Tests\EndToEnd;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\View\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\Laravel\Modules\Configuration\Modules;
use SineMacula\Laravel\Modules\Console\Commands\ModuleCacheCommand;
use Tests\EndToEndTestCase;

/**
 * End-to-end tests for a boot that reads a cache written by another process.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
#[CoversClass(ModuleCacheCommand::class)]
#[CoversClass(Modules::class)]
final class ModuleCacheCommandBootTest extends EndToEndTestCase
{
    /**
     * Test that a module route is served from a cache the booting process did
     * not write.
     *
     * @return void
     */
    public function testModuleRoutesAreServedFromAForeignCache(): void
    {
        $this->get('/billing/invoice')
            ->assertOk()
            ->assertSee('billing-invoice-ok');
    }

    /**
     * Test that module views and translations resolve from a foreign cache.
     *
     * @return void
     */
    public function testModuleResourcesResolveFromAForeignCache(): void
    {
        $app = $this->modularApplication();

        self::assertSame(
            'billing-view:INV-9',
            trim($app->make(Factory::class)->make('billing::invoice', ['reference' => 'INV-9'])->render()),
        );

        self::assertSame(
            'billing-lang-ok',
            $app->make(Translator::class)->get('billing::messages.greeting'),
        );
    }

    /**
     * Test that the optimize lifecycle runs to completion and writes the module
     * cache.
     *
     * The staged application has no resources directory, so a lifecycle that
     * depended on one would abort before reaching the module cache.
     *
     * @return void
     */
    public function testOptimizeWritesTheModuleCache(): void
    {
        @unlink($this->fixtureAppPath . '/bootstrap/cache/modules.php');

        self::assertDirectoryDoesNotExist($this->fixtureAppPath . '/resources');

        $process = $this->runFixtureArtisan(['optimize']);

        self::assertSame(
            0,
            $process->getExitCode(),
            $process->getOutput() . $process->getErrorOutput(),
        );

        self::assertFileExists($this->fixtureAppPath . '/bootstrap/cache/modules.php');
    }

    /**
     * Write the module cache from a separate process before the application is
     * created.
     *
     * @return void
     */
    #[\Override]
    protected function prepareFixtureApp(): void
    {
        $process = $this->runFixtureArtisan(['module:cache']);

        self::assertSame(
            0,
            $process->getExitCode(),
            $process->getOutput() . $process->getErrorOutput(),
        );
    }
}
