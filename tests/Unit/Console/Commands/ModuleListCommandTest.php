<?php

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
class ModuleListCommandTest extends TestCase
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
    protected function tearDown(): void
    {
        $this->resetModulesState();
        $this->removeTempDirectory();

        parent::tearDown();
    }

    /**
     * Test that handle outputs a table with column headers when modules exist.
     *
     * @return void
     */
    public function testHandleOutputsTableWithHeaders(): void
    {
        $this->createDirectory('modules/alpha');

        $this->resetModulesState();
        Modules::setBasePath($this->tempDir);

        $output = $this->runListCommand();

        static::assertStringContainsString('Module', $output);
        static::assertStringContainsString('Path', $output);
    }

    /**
     * Test that handle outputs all discovered modules in the table.
     *
     * @return void
     */
    public function testHandleOutputsAllDiscoveredModules(): void
    {
        $this->createDirectory('modules/alpha');
        $this->createDirectory('modules/beta');

        $this->resetModulesState();
        Modules::setBasePath($this->tempDir);

        $output = $this->runListCommand();

        static::assertStringContainsString('alpha', $output);
        static::assertStringContainsString('beta', $output);
    }

    /**
     * Test that handle outputs a warning when no modules exist.
     *
     * @return void
     */
    public function testHandleOutputsWarningWhenNoModules(): void
    {
        $output = $this->runListCommand();

        static::assertStringContainsString('No modules discovered', $output);
    }

    /**
     * Test that handle does not render a table when no modules exist.
     *
     * @return void
     */
    public function testHandleDoesNotRenderTableWhenNoModules(): void
    {
        $output = $this->runListCommand();

        static::assertStringNotContainsString('Module', $output);
        static::assertStringNotContainsString('Path', $output);
    }

    /**
     * Run the module:list command and return the output.
     *
     * @return string
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
