<?php

declare(strict_types = 1);

namespace Tests\EndToEnd;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\Laravel\Modules\Application;
use SineMacula\Laravel\Modules\Providers\ModuleServiceProvider;
use Tests\EndToEndTestCase;

/**
 * End-to-end tests for a default module that owns the application resources.
 *
 * A Resources directory in the default module becomes the application resource
 * root, which moves the configured view path with it. One without a views child
 * therefore points that path at a directory that does not exist, exactly as an
 * application with no resources directory at all does.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
#[CoversClass(Application::class)]
#[CoversClass(ModuleServiceProvider::class)]
final class DefaultModuleResourcesBootTest extends EndToEndTestCase
{
    /**
     * Test that the optimize lifecycle completes when the default module has a
     * resources directory with no views inside it.
     *
     * @return void
     */
    public function testOptimizeCompletesWithoutADefaultModuleViewsDirectory(): void
    {
        $resources = $this->fixtureAppPath . '/modules/Foundation/Resources';

        self::assertDirectoryExists($resources);
        self::assertDirectoryDoesNotExist($resources . '/views');

        $process = $this->runFixtureArtisan(['optimize']);

        self::assertSame(
            0,
            $process->getExitCode(),
            $process->getOutput() . $process->getErrorOutput(),
        );

        self::assertFileExists($this->fixtureAppPath . '/bootstrap/cache/modules.php');
    }

    /**
     * Test that module views still render once the default module has taken
     * over the application resource root.
     *
     * @return void
     */
    public function testModuleViewsStillResolveThroughTheirNamespace(): void
    {
        $this->get('/billing/invoice')
            ->assertOk()
            ->assertSee('billing-invoice-ok');
    }

    /**
     * Give the default module a resources directory holding no views.
     *
     * @return void
     */
    #[\Override]
    protected function prepareFixtureApp(): void
    {
        mkdir(
            $this->fixtureAppPath . '/modules/Foundation/Resources/lang/en',
            0755,
            true,
        );
    }

    /**
     * Stage only the billing module alongside the scaffolded default module.
     *
     * @return list<string>
     */
    #[\Override]
    protected function fixtureModules(): array
    {
        return ['Billing'];
    }
}
