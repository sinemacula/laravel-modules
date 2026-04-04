<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use SineMacula\Laravel\Modules\Configuration\Modules;
use Tests\Support\Concerns\InteractsWithModules;
use Tests\Support\Concerns\ManagesTemporaryFiles;

/**
 * Unit tests for the global helper functions.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
class HelpersTest extends TestCase
{
    use InteractsWithModules, ManagesTemporaryFiles;

    /**
     * Set up the test fixtures.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('helpers_test_');
        $this->createDirectory('modules');

        Modules::setBasePath($this->tempDir);
    }

    /**
     * Tear down the test fixtures.
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
     * Test that module_path returns the modules directory when called without
     * arguments.
     *
     * @return void
     */
    public function testModulePathReturnsModulesDirectory(): void
    {
        $expected = $this->tempDir
            . DIRECTORY_SEPARATOR . 'modules';

        static::assertSame($expected, module_path());
    }

    /**
     * Test that module_path appends the given path to the modules directory.
     *
     * @return void
     */
    public function testModulePathAppendsSubpath(): void
    {
        $expected = $this->tempDir
            . DIRECTORY_SEPARATOR . 'modules'
            . DIRECTORY_SEPARATOR . 'User';

        static::assertSame($expected, module_path('User'));
    }

    /**
     * Test that module_path with an empty string returns the modules directory
     * without a trailing separator.
     *
     * @return void
     */
    public function testModulePathWithEmptyStringReturnsBase(): void
    {
        $expected = $this->tempDir
            . DIRECTORY_SEPARATOR . 'modules';

        static::assertSame($expected, module_path(''));
    }
}
