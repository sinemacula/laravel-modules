<?php

namespace Tests\Unit\Console\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SineMacula\Laravel\Modules\Console\Commands\ModuleMakeCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\Concerns\InteractsWithModules;
use Tests\Support\Concerns\ManagesTemporaryFiles;

/**
 * Unit tests for the ModuleMakeCommand.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @SuppressWarnings("php:S1192")
 *
 * @internal
 */
#[CoversClass(ModuleMakeCommand::class)]
class ModuleMakeCommandTest extends TestCase
{
    use InteractsWithModules, ManagesTemporaryFiles;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('make_cmd_test_');
        $this->createDirectory('modules');
        $this->createDirectory('bootstrap/cache');

        $this->initModules($this->tempDir);
    }

    /**
     * Tear down the test environment.
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
     * Test that handle creates the module directory structure.
     *
     * @return void
     */
    public function testHandleCreatesModuleStructure(): void
    {
        $this->runMakeCommand('billing');

        $modulePath = $this->modulePath('Billing');

        static::assertDirectoryExists($modulePath);
        static::assertDirectoryExists($modulePath . '/Console/Commands');
        static::assertDirectoryExists($modulePath . '/Http/Controllers');
        static::assertDirectoryExists($modulePath . '/Http/Requests');
        static::assertDirectoryExists($modulePath . '/Listeners');
        static::assertDirectoryExists($modulePath . '/Models');
    }

    /**
     * Test that handle creates .gitkeep files in each directory.
     *
     * @return void
     */
    public function testHandleCreatesGitkeepFiles(): void
    {
        $this->runMakeCommand('billing');

        $modulePath = $this->modulePath('Billing');

        static::assertFileExists($modulePath . '/Console/Commands/.gitkeep');
        static::assertFileExists($modulePath . '/Http/Controllers/.gitkeep');
        static::assertFileExists($modulePath . '/Http/Requests/.gitkeep');
        static::assertFileExists($modulePath . '/Listeners/.gitkeep');
        static::assertFileExists($modulePath . '/Models/.gitkeep');
    }

    /**
     * Test that handle creates a routes.php file with the correct content.
     *
     * @return void
     */
    public function testHandleCreatesRoutesFile(): void
    {
        $this->runMakeCommand('billing');

        $routesFile = $this->modulePath('Billing')
            . DIRECTORY_SEPARATOR . 'Http'
            . DIRECTORY_SEPARATOR . 'routes.php';

        static::assertFileExists($routesFile);

        $content = file_get_contents($routesFile);

        static::assertStringContainsString('<?php', $content);
        static::assertStringContainsString('use Illuminate\Support\Facades\Route;', $content);
    }

    /**
     * Test that handle converts the name to StudlyCase.
     *
     * @return void
     */
    public function testHandleConvertsNameToStudlyCase(): void
    {
        $this->runMakeCommand('order-management');

        static::assertDirectoryExists(
            $this->modulePath('OrderManagement'),
        );
    }

    /**
     * Test that handle returns FAILURE when the module already exists.
     *
     * @return void
     */
    public function testHandleFailsWhenModuleExists(): void
    {
        $this->createDirectory('modules/Billing');

        $exitCode = $this->runMakeCommand('billing');

        static::assertSame(1, $exitCode);
    }

    /**
     * Test that handle returns SUCCESS when the module is created.
     *
     * @return void
     */
    public function testHandleReturnsSuccessOnCreation(): void
    {
        $exitCode = $this->runMakeCommand('billing');

        static::assertSame(0, $exitCode);
    }

    /**
     * Run the module:make command with the given name.
     *
     * @param  string  $name
     * @return int
     */
    private function runMakeCommand(string $name): int
    {
        $app = $this->createApplication();

        $command = new ModuleMakeCommand;
        $command->setLaravel($app);

        return $command->run(
            new ArrayInput(['name' => $name]),
            new BufferedOutput,
        );
    }

    /**
     * Build the full path to a module directory.
     *
     * @param  string  $name
     * @return string
     */
    private function modulePath(string $name): string
    {
        return $this->tempDir
            . DIRECTORY_SEPARATOR . 'modules'
            . DIRECTORY_SEPARATOR . $name;
    }

    /**
     * Create a minimal Laravel Application instance.
     *
     * @return \Illuminate\Foundation\Application
     */
    private function createApplication(): Application
    {
        $app = new Application($this->tempDir);

        $app->instance(Filesystem::class, new Filesystem);

        return $app;
    }
}
