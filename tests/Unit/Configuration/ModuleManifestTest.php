<?php

declare(strict_types = 1);

namespace Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SineMacula\Laravel\Modules\Configuration\ModuleManifest;
use SineMacula\Laravel\Modules\Exceptions\ModuleException;
use Tests\Support\Concerns\ManagesTemporaryFiles;

/**
 * Unit tests for the module manifest.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @SuppressWarnings("php:S1192")
 * @SuppressWarnings("php:S4833")
 * @SuppressWarnings("php:S2003")
 *
 * @internal
 */
#[CoversClass(ModuleManifest::class)]
final class ModuleManifestTest extends TestCase
{
    use ManagesTemporaryFiles;

    /** @var string The path to the manifest file. */
    private string $manifestPath = '';

    /** @var string The path to the modules directory. */
    private string $modulesPath = '';

    /**
     * Set up the test fixtures.
     *
     * @return void
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTempDirectory('manifest_test_');
        $this->createDirectory('bootstrap/cache');
        $this->createDirectory('modules/alpha');

        $this->manifestPath = $this->tempDir . '/bootstrap/cache/modules.php';
        $this->modulesPath  = $this->tempDir . '/modules';
    }

    /**
     * Tear down the test fixtures.
     *
     * @return void
     */
    #[\Override]
    protected function tearDown(): void
    {
        $this->removeTempDirectory();

        parent::tearDown();
    }

    /**
     * Test that path returns the manifest file path.
     *
     * @return void
     */
    public function testPathReturnsTheManifestPath(): void
    {
        self::assertSame($this->manifestPath, $this->manifest()->path());
    }

    /**
     * Test that reading a manifest that does not exist returns null.
     *
     * @return void
     */
    public function testReadReturnsNullWhenNoManifestExists(): void
    {
        self::assertNull($this->manifest()->read());
    }

    /**
     * Test that a manifest written for the current modules directory is
     * honoured.
     *
     * @return void
     */
    public function testReadReturnsModulesWhenTheSignatureMatches(): void
    {
        $manifest = $this->manifest();

        $manifest->write(fn (): array => ['alpha' => $this->modulesPath . '/alpha']);

        self::assertSame(['alpha' => $this->modulesPath . '/alpha'], $manifest->read());
    }

    /**
     * Test that a manifest is discarded once a module has been added to the
     * modules directory.
     *
     * @return void
     */
    public function testReadReturnsNullAfterAModuleIsAdded(): void
    {
        $manifest = $this->manifest();

        $manifest->write(fn (): array => ['alpha' => $this->modulesPath . '/alpha']);

        mkdir($this->modulesPath . '/beta', 0755, true);

        self::assertNull($manifest->read());
    }

    /**
     * Test that a manifest is discarded once a module has been removed from the
     * modules directory.
     *
     * @return void
     */
    public function testReadReturnsNullAfterAModuleIsRemoved(): void
    {
        $manifest = $this->manifest();

        $manifest->write(fn (): array => ['alpha' => $this->modulesPath . '/alpha']);

        rmdir($this->modulesPath . '/alpha');

        self::assertNull($manifest->read());
    }

    /**
     * Test that a manifest written under a different modules root is discarded.
     *
     * @return void
     */
    public function testReadReturnsNullForAForeignModulesRoot(): void
    {
        // Same entries, different root, so only the path distinguishes them.
        $foreign = $this->tempDir . '/elsewhere';

        mkdir($foreign . '/alpha', 0755, true);

        (new ModuleManifest($this->manifestPath, $foreign))
            ->write(fn (): array => ['alpha' => $this->modulesPath . '/alpha']);

        self::assertNull($this->manifest()->read());
    }

    /**
     * Test that a manifest is discarded when the modules directory cannot be
     * read, since none of the paths it names resolve.
     *
     * @return void
     */
    public function testReadDiscardsTheManifestWhenTheModulesDirectoryIsUnreadable(): void
    {
        if (posix_geteuid() === 0) {
            self::markTestSkipped('Permissions are not enforced for the superuser.');
        }

        $manifest = $this->manifest();

        $manifest->write(fn (): array => ['alpha' => $this->modulesPath . '/alpha']);

        chmod($this->modulesPath, 0000);

        try {
            self::assertNull($manifest->read());
        } finally {
            chmod($this->modulesPath, 0755);
        }
    }

    /**
     * Test that a manifest naming a module that is no longer there is discarded
     * rather than reporting a module with nothing behind it.
     *
     * @return void
     */
    public function testReadDiscardsTheManifestWhenAModulePathIsGone(): void
    {
        $manifest = $this->manifest();

        // A manifest written in one root and read in another, which is what a
        // build stage or a shared cache directory produces.
        $manifest->write(static fn (): array => ['alpha' => '/build/app/modules/Alpha']);

        self::assertNull($manifest->read());
    }

    /**
     * Test that a manifest that cannot be parsed is treated as absent.
     *
     * @return void
     */
    public function testReadDiscardsAManifestThatCannotBeParsed(): void
    {
        file_put_contents($this->manifestPath, "<?php\nreturn [ this is not php ;");

        self::assertNull($this->manifest()->read());
    }

    /**
     * Test that a manifest written for a modules directory that could not be
     * read is honoured, because a null signature is a recorded value.
     *
     * @return void
     */
    public function testReadReturnsModulesWhenTheStoredSignatureIsNull(): void
    {
        $manifest = new ModuleManifest($this->manifestPath, $this->tempDir . '/missing');

        $manifest->write(fn (): array => ['alpha' => $this->modulesPath . '/alpha']);

        self::assertSame(['alpha' => $this->modulesPath . '/alpha'], $manifest->read());
    }

    /**
     * Test that a manifest carrying a null signature is discarded once the
     * modules directory can be read, so a stale module set is never served.
     *
     * @return void
     */
    public function testReadReturnsNullOnceTheModulesDirectoryAppears(): void
    {
        $modules  = $this->tempDir . '/missing';
        $manifest = new ModuleManifest($this->manifestPath, $modules);

        $manifest->write(fn (): array => ['alpha' => $this->modulesPath . '/alpha']);

        mkdir($modules, 0755, true);

        self::assertNull($manifest->read());
    }

    /**
     * Test that a manifest whose stored signature is neither a string nor null
     * is discarded.
     *
     * @return void
     */
    public function testReadReturnsNullWhenTheStoredSignatureIsNotAString(): void
    {
        // No signature can be captured for an absent directory, so the shape
        // guard is what rejects this.
        $manifest = new ModuleManifest($this->manifestPath, $this->tempDir . '/missing');

        file_put_contents(
            $this->manifestPath,
            "<?php\nreturn " . var_export([
                'signature' => 123,
                'modules'   => ['alpha' => '/somewhere/alpha'],
            ], true) . ';',
        );

        self::assertNull($manifest->read());
    }

    /**
     * Test that a manifest carrying no signature key at all is discarded even
     * when the current signature is unknown.
     *
     * @return void
     */
    public function testReadReturnsNullWhenTheSignatureKeyIsAbsent(): void
    {
        $manifest = new ModuleManifest($this->manifestPath, $this->tempDir . '/missing');

        file_put_contents(
            $this->manifestPath,
            "<?php\nreturn " . var_export(['modules' => ['alpha' => '/somewhere/alpha']], true) . ';',
        );

        self::assertNull($manifest->read());
    }

    /**
     * Test that a manifest written before the signature was introduced is
     * discarded rather than trusted.
     *
     * @return void
     */
    public function testReadReturnsNullForAManifestWithoutASignature(): void
    {
        file_put_contents(
            $this->manifestPath,
            "<?php\nreturn " . var_export(['alpha' => '/somewhere/alpha'], true) . ';',
        );

        self::assertNull($this->manifest()->read());
    }

    /**
     * Test that a manifest whose module map is not an array is discarded.
     *
     * @return void
     */
    public function testReadReturnsNullWhenTheModuleMapIsNotAnArray(): void
    {
        // The signature must match, so the shape guard is what rejects this.
        $signature = implode("\n", [$this->modulesPath, ...(array) scandir($this->modulesPath)]);

        file_put_contents(
            $this->manifestPath,
            "<?php\nreturn " . var_export(['signature' => $signature, 'modules' => 'nope'], true) . ';',
        );

        self::assertNull($this->manifest()->read());
    }

    /**
     * Test that a manifest containing a non-string module path is discarded.
     *
     * Resolution concatenates the stored paths, so a non-string value would
     * otherwise raise a conversion warning on every request.
     *
     * @return void
     */
    public function testReadReturnsNullWhenAModulePathIsNotAString(): void
    {
        $signature = implode("\n", [$this->modulesPath, ...(array) scandir($this->modulesPath)]);

        file_put_contents(
            $this->manifestPath,
            "<?php\nreturn " . var_export([
                'signature' => $signature,
                'modules'   => ['alpha' => '/somewhere/alpha', 'beta' => ['nested']],
            ], true) . ';',
        );

        self::assertNull($this->manifest()->read());
    }

    /**
     * Test that a manifest that is not an array at all is discarded.
     *
     * @return void
     */
    public function testReadReturnsNullWhenTheManifestIsNotAnArray(): void
    {
        file_put_contents($this->manifestPath, "<?php\nreturn 'nope';");

        self::assertNull($this->manifest()->read());
    }

    /**
     * Test that the signature is captured before discovery runs, so a module
     * appearing during discovery invalidates the manifest.
     *
     * @return void
     */
    public function testWriteCapturesTheSignatureBeforeDiscovery(): void
    {
        $manifest = $this->manifest();

        $manifest->write(function (): array {

            mkdir($this->modulesPath . '/beta', 0755, true);

            return [
                'alpha' => $this->modulesPath . '/alpha',
                'beta'  => $this->modulesPath . '/beta',
            ];
        });

        self::assertNull($manifest->read());
    }

    /**
     * Test that a staging write failure is reported against the manifest path.
     *
     * @return void
     */
    public function testWriteThrowsWhenTheStagingFileCannotBeWritten(): void
    {
        if (posix_geteuid() === 0) {
            self::markTestSkipped('Permissions are not enforced for the superuser.');
        }

        $cacheDir = $this->tempDir . '/readonly';

        mkdir($cacheDir, 0755, true);
        chmod($cacheDir, 0444);

        $manifest = new ModuleManifest($cacheDir . '/modules.php', $this->modulesPath);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage(
            'Failed to write the temporary manifest file at ' . $cacheDir . '/modules.php.' . getmypid() . '.tmp.',
        );

        try {
            $manifest->write(fn (): array => ['alpha' => $this->modulesPath . '/alpha']);
        } finally {
            chmod($cacheDir, 0755);
        }
    }

    /**
     * Test that a rename failure is reported against the manifest path.
     *
     * @return void
     */
    public function testWriteThrowsWhenTheManifestCannotBeRenamedIntoPlace(): void
    {
        // A non-empty directory in the manifest's place cannot be replaced by a
        // rename, which is the failure a losing concurrent writer sees.
        mkdir($this->manifestPath, 0755, true);
        touch($this->manifestPath . '/occupied');

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('Failed to write the manifest file at ' . $this->manifestPath . '.');

        $this->manifest()->write(fn (): array => ['alpha' => $this->modulesPath . '/alpha']);
    }

    /**
     * Test that a failed rename leaves no staging file behind.
     *
     * @return void
     */
    public function testWriteRemovesTheStagingFileWhenTheRenameFails(): void
    {
        mkdir($this->manifestPath, 0755, true);
        touch($this->manifestPath . '/occupied');

        try {
            $this->manifest()->write(fn (): array => ['alpha' => $this->modulesPath . '/alpha']);
        } catch (ModuleException) {
            // The cleanup, not the exception, is under test here.
        }

        self::assertSame([], glob($this->manifestPath . '.*.tmp'));
    }

    /**
     * Test that write creates the manifest directory when it is missing.
     *
     * @return void
     */
    public function testWriteCreatesTheManifestDirectory(): void
    {
        $manifestPath = $this->tempDir . '/missing/cache/modules.php';

        $manifest = new ModuleManifest($manifestPath, $this->modulesPath);

        // Neutralise the umask so the created mode is the one that was asked
        // for, not the one the environment happens to allow.
        $umask = umask(0);

        try {
            $manifest->write(fn (): array => ['alpha' => $this->modulesPath . '/alpha']);
        } finally {
            umask($umask);
        }

        self::assertFileExists($manifestPath);
        self::assertSame(['alpha' => $this->modulesPath . '/alpha'], $manifest->read());
        self::assertSame('0755', substr(sprintf('%o', fileperms(dirname($manifestPath))), -4));
    }

    /**
     * Test that write reports a manifest directory that cannot be created.
     *
     * @return void
     */
    public function testWriteThrowsWhenTheManifestDirectoryCannotBeCreated(): void
    {
        if (posix_geteuid() === 0) {
            self::markTestSkipped('Permissions are not enforced for the superuser.');
        }

        $parent = $this->tempDir . '/readonly-parent';

        mkdir($parent, 0755, true);
        chmod($parent, 0444);

        $manifest = new ModuleManifest($parent . '/cache/modules.php', $this->modulesPath);

        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('Failed to create the manifest directory at ' . $parent . '/cache.');

        try {
            $manifest->write(fn (): array => ['alpha' => $this->modulesPath . '/alpha']);
        } finally {
            chmod($parent, 0755);
        }
    }

    /**
     * Test that writers do not stage through a shared path.
     *
     * @return void
     */
    public function testWriteDoesNotStageThroughASharedPath(): void
    {
        // Stand in for the staging file of a concurrent writer. A shared path
        // would see it overwritten and then renamed away.
        $shared = $this->manifestPath . '.tmp';

        file_put_contents($shared, 'reserved');

        $this->manifest()->write(fn (): array => ['alpha' => $this->modulesPath . '/alpha']);

        self::assertFileExists($this->manifestPath);
        self::assertSame('reserved', file_get_contents($shared));
        self::assertSame([], glob($this->manifestPath . '.*.tmp'));
    }

    /**
     * Test that delete removes the manifest file.
     *
     * @return void
     */
    public function testDeleteRemovesTheManifest(): void
    {
        $manifest = $this->manifest();

        $manifest->write(fn (): array => ['alpha' => $this->modulesPath . '/alpha']);

        self::assertTrue($manifest->delete());
        self::assertFileDoesNotExist($this->manifestPath);
    }

    /**
     * Test that deleting a manifest that does not exist reports success.
     *
     * @return void
     */
    public function testDeleteReportsSuccessWhenNoManifestExists(): void
    {
        self::assertTrue($this->manifest()->delete());
    }

    /**
     * Return a manifest for the fixture paths.
     *
     * @return \SineMacula\Laravel\Modules\Configuration\ModuleManifest
     */
    private function manifest(): ModuleManifest
    {
        return new ModuleManifest($this->manifestPath, $this->modulesPath);
    }
}
