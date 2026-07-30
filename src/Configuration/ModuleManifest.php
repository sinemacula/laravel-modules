<?php

declare(strict_types = 1);

namespace SineMacula\Laravel\Modules\Configuration;

use SineMacula\Laravel\Modules\Exceptions\ModuleException;

/**
 * Reads and writes the cached module manifest.
 *
 * The manifest stores the discovered module paths alongside a signature of the
 * modules directory they were discovered from. Laravel caches routes and events
 * before any package optimization command runs, so a manifest that outlived its
 * module set would otherwise be used to build those caches.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 */
final readonly class ModuleManifest
{
    /**
     * Create a new module manifest.
     *
     * @param  string  $path
     * @param  string  $modulesPath
     */
    public function __construct(

        /** @var string The path to the manifest file. */
        private string $path,

        /** @var string The path to the modules directory. */
        private string $modulesPath,
    ) {}

    /**
     * Return the path to the manifest file.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Return the cached module paths, or null when the manifest cannot be used.
     *
     * The manifest is only honoured while it still describes the modules
     * directory it was written for. One written before a module was added,
     * removed or renamed is discarded in favour of discovery, so route and
     * event caches can never be built from a superseded module set.
     *
     * @return array<string, string>|null
     */
    public function read(): ?array
    {
        $manifest = $this->load();

        if ($manifest === null) {
            return null;
        }

        $signature = $this->signature();

        // A directory that cannot be read leaves the signature unknown, which
        // is not the same as the manifest being stale.
        if ($signature !== null && $signature !== $manifest['signature']) {
            return null;
        }

        return $manifest['modules'];
    }

    /**
     * Write the manifest for the modules produced by the given callback.
     *
     * The signature is captured before the callback runs so that a module
     * appearing while discovery is in progress invalidates the manifest rather
     * than being paired with a signature taken after it landed.
     *
     * @param  \Closure(): array<string, string>  $discover
     * @return void
     *
     * @throws \SineMacula\Laravel\Modules\Exceptions\ModuleException
     */
    public function write(\Closure $discover): void
    {
        $manifest = [
            'signature' => $this->signature(),
            'modules'   => $discover(),
        ];

        $content  = "<?php\nreturn " . var_export($manifest, true) . ';';
        $tempPath = $this->path . '.tmp';

        if (file_put_contents($tempPath, $content) === false) {
            throw new ModuleException('Failed to write temporary manifest file at ' . $tempPath . '.');
        }

        if (!rename($tempPath, $this->path)) { // @codeCoverageIgnoreStart

            @unlink($tempPath);

            throw new ModuleException('Failed to write manifest file at ' . $this->path . '.');
        } // @codeCoverageIgnoreEnd
    }

    /**
     * Delete the manifest file.
     *
     * @return bool true when no manifest file remains after the operation
     */
    public function delete(): bool
    {
        return !file_exists($this->path) || @unlink($this->path);
    }

    /**
     * Load the manifest file and validate its shape.
     *
     * @return array{signature: string|null, modules: array<string, string>}|null
     *
     * @SuppressWarnings("php:S4833")
     * @SuppressWarnings("php:S2003")
     */
    private function load(): ?array
    {
        if (!file_exists($this->path)) {
            return null;
        }

        $manifest = require $this->path;

        if (!is_array($manifest) || !isset($manifest['modules'], $manifest['signature'])) {
            return null;
        }

        return is_array($manifest['modules']) ? $manifest : null; // @phpstan-ignore return.type (require() of the manifest yields mixed; the guards above validate the shape)
    }

    /**
     * Return a signature for the current contents of the modules directory.
     *
     * Covers the modules path and its immediate entries, which are the only
     * inputs that can change the discovered module set. Returns null when the
     * directory cannot be read.
     *
     * @return string|null
     */
    private function signature(): ?string
    {
        $entries = @scandir($this->modulesPath);

        if ($entries === false) {
            return null;
        }

        return implode("\n", [$this->modulesPath, ...$entries]);
    }
}
