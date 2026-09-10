<?php

declare(strict_types = 1);

namespace Tests\EndToEnd;

use PHPUnit\Framework\Attributes\CoversClass;
use SineMacula\Laravel\Modules\Application;

/**
 * End-to-end tests for an application booted from a non-canonical root.
 *
 * Listener discovery derives a class name by stripping the application base
 * path off each listener's real path, and the framework swallows the failure
 * when the two are spelled differently. Every other surface keeps working, so
 * the whole matrix runs here to prove the boot is otherwise unaffected.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
#[CoversClass(Application::class)]
final class NonCanonicalRootBootTest extends ModuleIntegrationTestCase
{
    /**
     * Test that the application resolves its non-canonical root.
     *
     * @return void
     */
    public function testTheApplicationCanonicalisesItsBasePath(): void
    {
        self::assertSame(
            $this->fixtureAppPath,
            $this->modularApplication()->basePath(),
        );
    }

    /**
     * Boot the staged application from a root spelled through the modules
     * directory rather than directly.
     *
     * @return void
     */
    #[\Override]
    protected function prepareFixtureApp(): void
    {
        $bootstrap = <<<'EOD'
            <?php

            use Illuminate\Foundation\Configuration\Exceptions;
            use Illuminate\Foundation\Configuration\Middleware;
            use SineMacula\Laravel\Modules\Application;
            use SineMacula\Laravel\Modules\Configuration\Modules;

            $basePath = dirname(__DIR__)
                . DIRECTORY_SEPARATOR . 'modules'
                . DIRECTORY_SEPARATOR . '..';

            Modules::setBasePath($basePath);

            return Application::configure(basePath: $basePath)
                ->withRouting(
                    api      : Modules::routePaths(),
                    health   : '/health',
                    apiPrefix: '',
                )
                ->withMiddleware(static function (Middleware $middleware): void {})
                ->withExceptions(static function (Exceptions $exceptions): void {})
                ->create();
            EOD;

        file_put_contents(
            $this->fixtureAppPath . '/bootstrap/app.php',
            $bootstrap . "\n",
        );
    }
}
