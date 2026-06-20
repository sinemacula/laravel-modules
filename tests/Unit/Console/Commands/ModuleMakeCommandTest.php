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
use Tests\Support\Spies\RecordingFilesystem;

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

    /** @var string The output captured from the most recent command run. */
    private string $output = '';

    /** @var \Tests\Support\Spies\RecordingFilesystem The filesystem used by the most recent run. */
    private RecordingFilesystem $filesystem;

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

        $this->filesystem = new RecordingFilesystem;

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
     * Test that handle writes a .gitkeep file into each module directory at the
     * expected path.
     *
     * @return void
     */
    public function testHandleWritesGitkeepFilesToEachDirectory(): void
    {
        $this->runMakeCommand('billing');

        $modulePath = $this->modulePath('Billing');
        $written    = $this->filesystem->writtenPaths();

        static::assertContains($modulePath . '/Console/Commands/.gitkeep', $written);
        static::assertContains($modulePath . '/Http/Controllers/.gitkeep', $written);
        static::assertContains($modulePath . '/Http/Requests/.gitkeep', $written);
        static::assertContains($modulePath . '/Listeners/.gitkeep', $written);
        static::assertContains($modulePath . '/Models/.gitkeep', $written);
    }

    /**
     * Test that handle writes a routes.php file to the module Http directory
     * with the expected content.
     *
     * @return void
     */
    public function testHandleWritesRoutesFileWithExpectedContent(): void
    {
        $this->runMakeCommand('billing');

        $routesFile = $this->modulePath('Billing')
            . DIRECTORY_SEPARATOR . 'Http'
            . DIRECTORY_SEPARATOR . 'routes.php';

        $routes = array_values(array_filter(
            $this->filesystem->writes,
            static fn (array $write): bool => $write['path'] === $routesFile,
        ));

        static::assertCount(1, $routes);
        static::assertStringContainsString('<?php', $routes[0]['contents']);
        static::assertStringContainsString('use Illuminate\Support\Facades\Route;', $routes[0]['contents']);
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
     * Test that handle outputs the success message when the module is created.
     *
     * @return void
     */
    public function testHandleOutputsSuccessMessageOnCreation(): void
    {
        $this->runMakeCommand('billing');

        static::assertStringContainsString('Module [Billing] created successfully', $this->output);
    }

    /**
     * Test that handle outputs an error message when the module already exists.
     *
     * @return void
     */
    public function testHandleOutputsErrorWhenModuleExists(): void
    {
        $this->createDirectory('modules/Billing');

        $this->runMakeCommand('billing');

        static::assertStringContainsString('Module [Billing] already exists', $this->output);
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

        $output = new BufferedOutput;

        $exitCode = $command->run(
            new ArrayInput(['name' => $name]),
            $output,
        );

        $this->output = $output->fetch();

        return $exitCode;
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

        $app->instance(Filesystem::class, $this->filesystem);

        return $app;
    }
}
