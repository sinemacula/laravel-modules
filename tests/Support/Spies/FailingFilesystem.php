<?php

declare(strict_types = 1);

namespace Tests\Support\Spies;

use Illuminate\Filesystem\Filesystem;

/**
 * Filesystem double that fails on directory creation.
 *
 * Simulates an unwritable target (permissions, read-only mount) so the scaffold
 * error path in ModuleMakeCommand can be exercised without depending on real
 * filesystem permissions.
 *
 * @internal
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @phpstan-ignore class.childType (test double subclasses the filesystem to simulate a write failure)
 */
final class FailingFilesystem extends Filesystem
{
    /**
     * Throw as though the directory could not be created.
     *
     * @param  mixed  $path
     * @param  mixed  $mode
     * @param  mixed  $recursive
     * @return never
     *
     * @throws \RuntimeException
     */
    #[\Override]
    public function ensureDirectoryExists(mixed $path, mixed $mode = 0755, mixed $recursive = true): never
    {
        throw new \RuntimeException('Simulated filesystem failure.');
    }
}
