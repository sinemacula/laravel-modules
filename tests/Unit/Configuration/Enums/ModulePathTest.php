<?php

declare(strict_types = 1);

namespace Tests\Unit\Configuration\Enums;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SineMacula\Laravel\Modules\Configuration\Enums\ModulePath;

/**
 * Unit tests for the ModulePath enum.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
#[CoversClass(ModulePath::class)]
final class ModulePathTest extends TestCase
{
    /**
     * Test that all expected enum cases exist.
     *
     * @return void
     */
    public function testAllCasesExist(): void
    {
        $cases = ModulePath::cases();

        self::assertCount(9, $cases);
    }

    /**
     * Test that the MODULES case has the correct value.
     *
     * @return void
     */
    public function testModulesValue(): void
    {
        self::assertSame('modules', ModulePath::MODULES->value);
    }

    /**
     * Test that the CACHE case has the correct value.
     *
     * @return void
     */
    public function testCacheValue(): void
    {
        self::assertSame(
            'bootstrap/cache/modules.php',
            ModulePath::CACHE->value,
        );
    }

    /**
     * Test that the LISTENERS case has the correct value.
     *
     * @return void
     */
    public function testListenersValue(): void
    {
        self::assertSame('Listeners', ModulePath::LISTENERS->value);
    }

    /**
     * Test that the COMMANDS case has the correct value.
     *
     * @return void
     */
    public function testCommandsValue(): void
    {
        self::assertSame('Console/Commands', ModulePath::COMMANDS->value);
    }

    /**
     * Test that the RESOURCES case has the correct value.
     *
     * @return void
     */
    public function testResourcesValue(): void
    {
        self::assertSame('Resources', ModulePath::RESOURCES->value);
    }

    /**
     * Test that the VIEWS case has the correct value.
     *
     * @return void
     */
    public function testViewsValue(): void
    {
        self::assertSame('Resources/views', ModulePath::VIEWS->value);
    }

    /**
     * Test that the LANG case has the correct value.
     *
     * @return void
     */
    public function testLangValue(): void
    {
        self::assertSame('Resources/lang', ModulePath::LANG->value);
    }

    /**
     * Test that the ROUTES case has the correct value.
     *
     * @return void
     */
    public function testRoutesValue(): void
    {
        self::assertSame('Http/routes.php', ModulePath::ROUTES->value);
    }

    /**
     * Test that the SCHEDULES case has the correct value.
     *
     * @return void
     */
    public function testSchedulesValue(): void
    {
        self::assertSame('Console/schedule.php', ModulePath::SCHEDULES->value);
    }

    /**
     * Test that ModulePath is a string-backed enum.
     *
     * @return void
     */
    public function testIsStringBackedEnum(): void
    {
        $case = ModulePath::MODULES;

        self::assertIsString($case->value);
    }

    /**
     * Test that tryFrom returns null for an invalid value.
     *
     * @return void
     */
    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $case = ModulePath::tryFrom('nonexistent');

        self::assertNull($case);
    }

    /**
     * Test that from returns the correct case for a valid value.
     *
     * @return void
     */
    public function testFromReturnsCorrectCase(): void
    {
        $case = ModulePath::from('modules');

        self::assertSame(ModulePath::MODULES, $case);
    }
}
