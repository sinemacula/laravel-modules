<?php

declare(strict_types = 1);

namespace Tests\Unit\Console\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SineMacula\Laravel\Modules\Console\Commands\ModuleMakeCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\Concerns\InteractsWithModules;
use Tests\Support\Concerns\ManagesTemporaryFiles;
use Tests\Support\Spies\FailingFilesystem;
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
final class ModuleMakeCommandTest extends TestCase
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
    #[\Override]
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
    #[\Override]
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
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleCreatesModuleStructure(): void
    {
        $this->runMakeCommand('billing');

        $modulePath = $this->modulePath('Billing');

        self::assertDirectoryExists($modulePath);
        self::assertDirectoryExists($modulePath . '/Console/Commands');
        self::assertDirectoryExists($modulePath . '/Http/Controllers');
        self::assertDirectoryExists($modulePath . '/Http/Requests');
        self::assertDirectoryExists($modulePath . '/Listeners');
        self::assertDirectoryExists($modulePath . '/Models');
    }

    /**
     * Test that handle writes a .gitkeep file into each module directory at the
     * expected path.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleWritesGitkeepFilesToEachDirectory(): void
    {
        $this->runMakeCommand('billing');

        $modulePath = $this->modulePath('Billing');
        $written    = $this->filesystem->writtenPaths();

        self::assertContains($modulePath . '/Console/Commands/.gitkeep', $written);
        self::assertContains($modulePath . '/Http/Controllers/.gitkeep', $written);
        self::assertContains($modulePath . '/Http/Requests/.gitkeep', $written);
        self::assertContains($modulePath . '/Listeners/.gitkeep', $written);
        self::assertContains($modulePath . '/Models/.gitkeep', $written);
    }

    /**
     * Test that handle writes a routes.php file to the module Http directory
     * with the expected content.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
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

        self::assertCount(1, $routes);
        self::assertStringContainsString('<?php', $routes[0]['contents']);
        self::assertStringContainsString('use Illuminate\Support\Facades\Route;', $routes[0]['contents']);
    }

    /**
     * Test that handle converts the name to StudlyCase.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleConvertsNameToStudlyCase(): void
    {
        $this->runMakeCommand('order-management');

        self::assertDirectoryExists(
            $this->modulePath('OrderManagement'),
        );
    }

    /**
     * Test that handle returns FAILURE when the module already exists.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleFailsWhenModuleExists(): void
    {
        $this->createDirectory('modules/Billing');

        $exitCode = $this->runMakeCommand('billing');

        self::assertSame(1, $exitCode);
    }

    /**
     * Test that a module can be scaffolded before the modules directory itself
     * exists.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleScaffoldsWhenTheModulesDirectoryIsAbsent(): void
    {
        rmdir($this->tempDir . DIRECTORY_SEPARATOR . 'modules');

        $exitCode = $this->runMakeCommand('billing');

        self::assertSame(0, $exitCode);
        self::assertDirectoryExists($this->modulePath('Billing') . '/Models');
    }

    /**
     * Test that a module whose name differs only by case is treated as an
     * existing module, since discovery keys them identically.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleFailsWhenADifferentlyCasedModuleExists(): void
    {
        $this->createDirectory('modules/billing');

        $exitCode = $this->runMakeCommand('Billing');

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('already exists', $this->output);
    }

    /**
     * Test that handle returns SUCCESS when the module is created.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleReturnsSuccessOnCreation(): void
    {
        $exitCode = $this->runMakeCommand('billing');

        self::assertSame(0, $exitCode);
    }

    /**
     * Test that handle outputs the success message when the module is created.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleOutputsSuccessMessageOnCreation(): void
    {
        $this->runMakeCommand('billing');

        self::assertStringContainsString('Module [Billing] created successfully', $this->output);
    }

    /**
     * Test that handle outputs an error message when the module already exists.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleOutputsErrorWhenModuleExists(): void
    {
        $this->createDirectory('modules/Billing');

        $this->runMakeCommand('billing');

        self::assertStringContainsString('Module [Billing] already exists', $this->output);
    }

    /**
     * Test that handle reports an error and returns FAILURE when the scaffold
     * cannot be written to disk.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleReturnsFailureWhenScaffoldingFails(): void
    {
        $app = new Application($this->tempDir);
        $app->instance(Filesystem::class, new FailingFilesystem);

        $command = new ModuleMakeCommand;
        $command->setLaravel($app);

        $output = new BufferedOutput;

        $exitCode = $command->run(new ArrayInput(['name' => 'billing']), $output);

        $body = $output->fetch();

        self::assertSame(ModuleMakeCommand::FAILURE, $exitCode);
        self::assertStringContainsString('Failed to create module [Billing]: Simulated filesystem failure', $body);
    }

    /**
     * Provide invalid module names that must be rejected before scaffolding.
     *
     * @return iterable<string, array{string}>
     */
    public static function invalidModuleNameProvider(): iterable
    {
        yield from [
            'parent traversal'   => ['../../evil'],
            'embedded separator' => ['Billing/../secrets'],
        ];
    }

    /**
     * Test that handle rejects a module name that would escape the modules
     * directory, writing nothing and not attempting to scaffold.
     *
     * @param  string  $name
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    #[DataProvider('invalidModuleNameProvider')]
    public function testHandleRejectsInvalidModuleName(string $name): void
    {
        $exitCode = $this->runMakeCommand($name);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Invalid module name', $this->output);
        self::assertStringNotContainsString('Failed to create', $this->output);
        self::assertSame([], $this->filesystem->writes);
    }

    /**
     * Run the module:make command with the given name.
     *
     * @param  string  $name
     * @return int
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
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
