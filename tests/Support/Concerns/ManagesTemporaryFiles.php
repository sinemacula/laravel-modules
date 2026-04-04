<?php

namespace Tests\Support\Concerns;

/**
 * Provides temporary directory management for tests.
 *
 * Creates and cleans up temporary directories and files used during testing.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
trait ManagesTemporaryFiles
{
    /** @var string The path to the temporary directory. */
    protected string $tempDir = '';

    /**
     * Create a temporary directory with the given prefix.
     *
     * @param  string  $prefix
     * @return void
     */
    protected function createTempDirectory(string $prefix = 'test-'): void
    {
        $this->tempDir = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . $prefix . uniqid();

        mkdir($this->tempDir, 0755, true);
    }

    /**
     * Remove the temporary directory and all its contents.
     *
     * @return void
     */
    protected function removeTempDirectory(): void
    {
        if ($this->tempDir !== '' && is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    /**
     * Create a directory relative to the temporary directory.
     *
     * @param  string  $relativePath
     * @return void
     */
    protected function createDirectory(string $relativePath): void
    {
        $path = $this->tempDir
            . DIRECTORY_SEPARATOR
            . $relativePath;

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Create a file relative to the temporary directory.
     *
     * @param  string  $relativePath
     * @param  string  $content
     * @return void
     */
    protected function createFile(
        string $relativePath,
        string $content = '',
    ): void {
        $path = $this->tempDir
            . DIRECTORY_SEPARATOR
            . $relativePath;

        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $content);
    }

    /**
     * Recursively remove a directory and all its contents.
     *
     * @param  string  $path
     * @return void
     */
    protected function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $path,
                \RecursiveDirectoryIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($path);
    }
}
