<?php

declare(strict_types = 1);

namespace Tests\Support\Spies;

use Illuminate\Filesystem\Filesystem;

/**
 * Recording filesystem double that captures writes without touching disk.
 *
 * Directory creation and existence checks fall through to the real filesystem,
 * but file writes are recorded only. This lets command path construction be
 * asserted precisely without the real file writes that a mutated path would
 * otherwise trigger (and the PHP warnings that come with them).
 *
 * @internal
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @phpstan-ignore class.childType (test double subclasses the filesystem to record writes)
 */
final class RecordingFilesystem extends Filesystem
{
    /** @var list<array{path: string, contents: string}> The recorded file writes. */
    public array $writes = [];

    /**
     * Record a file write without touching the real filesystem.
     *
     * @param  mixed  $path
     * @param  mixed  $contents
     * @param  mixed  $lock
     * @return int
     */
    #[\Override]
    public function put(mixed $path, mixed $contents, mixed $lock = false): int
    {
        if (!is_string($path) || !is_string($contents)) {
            return 0; // @codeCoverageIgnore
        }

        $this->writes[] = ['path' => $path, 'contents' => $contents];

        return strlen($contents);
    }

    /**
     * Return the recorded write paths.
     *
     * @return list<string>
     */
    public function writtenPaths(): array
    {
        return array_column($this->writes, 'path');
    }
}
