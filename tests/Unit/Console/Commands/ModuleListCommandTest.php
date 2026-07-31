<?php

declare(strict_types = 1);

namespace Tests\Unit\Console\Commands;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SineMacula\Laravel\Modules\Configuration\Modules;
use SineMacula\Laravel\Modules\Console\Commands\ModuleListCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\Concerns\InteractsWithModules;
use Tests\Support\Concerns\ManagesTemporaryFiles;

/**
 * Unit tests for the ModuleListCommand.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
#[CoversClass(ModuleListCommand::class)]
final class ModuleListCommandTest extends TestCase
{
    use InteractsWithModules, ManagesTemporaryFiles;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('list_cmd_test_');
        $this->createDirectory('modules');
        $this->createDirectory('bootstrap/cache');

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
     * Test that a discovery failure is reported and exits with a failure code
     * rather than escaping as an uncaught exception.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleReportsDiscoveryFailure(): void
    {
        $target = $this->tempDir . '/outside/Remote';

        mkdir($target, 0755, true);
        symlink($target, $this->tempDir . '/modules/remote');

        $this->resetModulesState();
        Modules::setBasePath($this->tempDir);

        $app = new Application($this->tempDir);

        $command = new ModuleListCommand;
        $command->setLaravel($app);

        $output   = new BufferedOutput;
        $exitCode = $command->run(new ArrayInput([]), $output);

        self::assertSame(ModuleListCommand::FAILURE, $exitCode);
        self::assertStringContainsString('does not resolve to a real directory', $output->fetch());
    }

    /**
     * Test that a successful listing exits with a success code.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleReturnsSuccessWhenModulesAreListed(): void
    {
        $this->createDirectory('modules/alpha');

        $this->resetModulesState();
        Modules::setBasePath($this->tempDir);

        $app = new Application($this->tempDir);

        $command = new ModuleListCommand;
        $command->setLaravel($app);

        $exitCode = $command->run(new ArrayInput([]), new BufferedOutput);

        self::assertSame(ModuleListCommand::SUCCESS, $exitCode);
    }

    /**
     * Test that handle outputs a table with column headers when modules exist.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleOutputsTableWithHeaders(): void
    {
        $this->createDirectory('modules/alpha');

        $this->resetModulesState();
        Modules::setBasePath($this->tempDir);

        $output = $this->runListCommand();

        self::assertStringContainsString('Module', $output);
        self::assertStringContainsString('Path', $output);
    }

    /**
     * Test that handle outputs all discovered modules in the table.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleOutputsAllDiscoveredModules(): void
    {
        $this->createDirectory('modules/alpha');
        $this->createDirectory('modules/beta');

        $this->resetModulesState();
        Modules::setBasePath($this->tempDir);

        $output = $this->runListCommand();

        self::assertStringContainsString('alpha', $output);
        self::assertStringContainsString('beta', $output);
    }

    /**
     * Test that handle renders each module name in its own table cell, distinct
     * from the path column (which also contains the name as a substring).
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleRendersModuleNamesInOwnColumn(): void
    {
        $this->createDirectory('modules/alpha');
        $this->createDirectory('modules/beta');

        $this->resetModulesState();
        Modules::setBasePath($this->tempDir);

        $output = $this->runListCommand();

        self::assertMatchesRegularExpression('/\|\s+alpha\s+\|/', $output);
        self::assertMatchesRegularExpression('/\|\s+beta\s+\|/', $output);
    }

    /**
     * Test that handle outputs a warning when no modules exist.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleOutputsWarningWhenNoModules(): void
    {
        $output = $this->runListCommand();

        self::assertStringContainsString('No modules discovered', $output);
    }

    /**
     * Test that handle does not render a table when no modules exist.
     *
     * @return void
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    public function testHandleDoesNotRenderTableWhenNoModules(): void
    {
        $output = $this->runListCommand();

        // Assert on the table itself rather than its header words, so that
        // rewording the warning cannot break this for an unrelated reason.
        self::assertStringNotContainsString('+---', $output);
        self::assertStringNotContainsString('|', $output);
        self::assertMatchesRegularExpression('/no modules discovered/i', $output);
    }

    /**
     * Run the module:list command and return the output.
     *
     * @return string
     *
     * @throws \Symfony\Component\Console\Exception\ExceptionInterface
     */
    private function runListCommand(): string
    {
        $app = new Application($this->tempDir);

        $command = new ModuleListCommand;
        $command->setLaravel($app);

        $output = new BufferedOutput;

        $command->run(new ArrayInput([]), $output);

        return $output->fetch();
    }
}
