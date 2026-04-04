<?php

namespace Tests\Unit\Configuration;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SineMacula\Laravel\Modules\Configuration\ApplicationBuilder;
use Tests\Support\Concerns\InteractsWithModules;
use Tests\Support\Concerns\ManagesTemporaryFiles;
use Tests\Support\Spies\SpyApplicationBuilder;

/**
 * Unit tests for the modular ApplicationBuilder.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @SuppressWarnings("php:S1192")
 *
 * @internal
 */
#[CoversClass(ApplicationBuilder::class)]
class ApplicationBuilderTest extends TestCase
{
    use InteractsWithModules, ManagesTemporaryFiles;

    /**
     * Set up the test environment.
     *
     * Creates a temporary directory structure and configures the Modules class
     * to use it.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('test-builder-');

        $this->createDirectory('modules');
        $this->createDirectory('bootstrap/cache');

        // Create an empty bootstrap/providers.php for Application
        $this->createFile(
            'bootstrap/providers.php',
            '<?php return [];',
        );

        $this->initModules($this->tempDir);
    }

    /**
     * Tear down the test environment.
     *
     * Removes the temporary directory and resets all static state.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->resetModulesState();
        $this->removeTempDirectory();

        parent::tearDown();
    }

    /**
     * Test that withModules returns the same builder instance for fluent
     * chaining.
     *
     * @return void
     */
    public function testWithModulesReturnsStaticInstance(): void
    {
        $app     = new Application($this->tempDir);
        $builder = new ApplicationBuilder($app);

        $result = $builder->withModules();

        static::assertSame($builder, $result);
    }

    /**
     * Test that withModules discovers Listeners directories within modules and
     * passes them to withEvents.
     *
     * @return void
     */
    public function testWithModulesDiscoversListenersDirectory(): void
    {
        $listenersDir = $this->tempDir
            . DIRECTORY_SEPARATOR . 'modules'
            . DIRECTORY_SEPARATOR . 'alpha'
            . DIRECTORY_SEPARATOR . 'Listeners';

        mkdir($listenersDir, 0755, true);

        $app     = new Application($this->tempDir);
        $builder = $this->createSpyBuilder($app);

        $builder->withModules();

        static::assertNotEmpty($builder->capturedEvents);
        static::assertContains(realpath($listenersDir), $builder->capturedEvents);
    }

    /**
     * Test that withModules discovers schedule.php files within modules and
     * passes them to withCommands.
     *
     * @return void
     */
    public function testWithModulesDiscoversScheduleFiles(): void
    {
        $consoleDir = $this->tempDir
            . DIRECTORY_SEPARATOR . 'modules'
            . DIRECTORY_SEPARATOR . 'alpha'
            . DIRECTORY_SEPARATOR . 'Console';

        mkdir($consoleDir, 0755, true);
        touch($consoleDir . DIRECTORY_SEPARATOR . 'schedule.php');

        $app     = new Application($this->tempDir);
        $builder = $this->createSpyBuilder($app);

        $builder->withModules();

        $scheduleFile = realpath(
            $consoleDir . DIRECTORY_SEPARATOR . 'schedule.php',
        );

        static::assertContains(
            $scheduleFile,
            $builder->capturedCommands,
        );
    }

    /**
     * Test that withModules discovers Commands directories within modules and
     * passes them to withCommands.
     *
     * @return void
     */
    public function testWithModulesDiscoversCommandsDirectory(): void
    {
        $commandsDir = $this->tempDir
            . DIRECTORY_SEPARATOR . 'modules'
            . DIRECTORY_SEPARATOR . 'alpha'
            . DIRECTORY_SEPARATOR . 'Console'
            . DIRECTORY_SEPARATOR . 'Commands';

        mkdir($commandsDir, 0755, true);

        $app     = new Application($this->tempDir);
        $builder = $this->createSpyBuilder($app);

        $builder->withModules();

        static::assertContains(
            realpath($commandsDir),
            $builder->capturedCommands,
        );
    }

    /**
     * Test that withModules calls withKernels as part of the registration
     * chain.
     *
     * @return void
     */
    public function testWithModulesCallsWithKernels(): void
    {
        $app     = new Application($this->tempDir);
        $builder = $this->createSpyBuilder($app);

        $builder->withModules();

        static::assertTrue($builder->withKernelsCalled);
    }

    /**
     * Test that withModules calls withProviders as part of the registration
     * chain.
     *
     * @return void
     */
    public function testWithModulesCallsWithProviders(): void
    {
        $app     = new Application($this->tempDir);
        $builder = $this->createSpyBuilder($app);

        $builder->withModules();

        static::assertTrue($builder->withProvidersCalled);
    }

    /**
     * Test that withModules handles an empty modules directory without errors.
     *
     * @return void
     */
    public function testWithModulesHandlesEmptyGlobResults(): void
    {
        // The modules directory exists but is empty
        $app     = new Application($this->tempDir);
        $builder = $this->createSpyBuilder($app);

        $builder->withModules();

        static::assertEmpty($builder->capturedEvents);
        static::assertEmpty($builder->capturedCommands);
    }

    /**
     * Test that withModules merges both schedule files and command directories
     * into a single array for withCommands.
     *
     * @return void
     */
    public function testWithModulesMergesSchedulesAndCommands(): void
    {
        $consoleDir = $this->tempDir
            . DIRECTORY_SEPARATOR . 'modules'
            . DIRECTORY_SEPARATOR . 'alpha'
            . DIRECTORY_SEPARATOR . 'Console';

        $commandsDir = $consoleDir
            . DIRECTORY_SEPARATOR . 'Commands';

        mkdir($commandsDir, 0755, true);
        touch($consoleDir . DIRECTORY_SEPARATOR . 'schedule.php');

        $app     = new Application($this->tempDir);
        $builder = $this->createSpyBuilder($app);

        $builder->withModules();

        $scheduleFile = realpath(
            $consoleDir . DIRECTORY_SEPARATOR . 'schedule.php',
        );

        static::assertContains(
            $scheduleFile,
            $builder->capturedCommands,
        );

        static::assertContains(
            realpath($commandsDir),
            $builder->capturedCommands,
        );

        static::assertCount(2, $builder->capturedCommands);
    }

    /**
     * Create a spy builder that captures the arguments passed to withEvents
     * and withCommands.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return \Tests\Support\Spies\SpyApplicationBuilder
     */
    private function createSpyBuilder(Application $app): SpyApplicationBuilder
    {
        return new SpyApplicationBuilder($app);
    }
}
