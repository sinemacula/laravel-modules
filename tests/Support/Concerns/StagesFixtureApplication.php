<?php

declare(strict_types = 1);

namespace Tests\Support\Concerns;

use Symfony\Component\Process\Process;

/**
 * Stages a private copy of the end-to-end fixture application.
 *
 * Booting the committed fixture in place would write compiled views, package
 * manifests, and the module cache into the repository tree, and would let one
 * test's module cache leak into the next. Each test therefore runs against its
 * own copy in a temporary directory.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 *
 * @internal
 */
trait StagesFixtureApplication
{
    /** @var string The path to the staged fixture application. */
    protected string $fixtureAppPath = '';

    /**
     * Stage a private copy of the fixture application containing the given
     * modules.
     *
     * @param  list<string>  $modules
     * @return void
     */
    protected function stageFixtureApp(array $modules): void
    {
        // uniqid() alone is microsecond-derived, so parallel workers collide
        // and then tear down each other's staged tree.
        $path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'modules_e2e_' . getmypid() . '_' . bin2hex(random_bytes(8));

        mkdir($path, 0755, true);

        // Listener discovery strips the base path from each file's real path,
        // and the macOS temporary root is itself a symlink.
        $this->fixtureAppPath = realpath($path) ?: $path;

        foreach (['bootstrap/cache', 'resources/views', 'storage/framework/views', 'modules'] as $directory) {
            mkdir($this->fixtureAppPath . DIRECTORY_SEPARATOR . $directory, 0755, true);
        }

        foreach (['artisan', 'composer.json', 'bootstrap/app.php', 'bootstrap/providers.php'] as $file) {
            copy(
                self::fixtureSourcePath() . DIRECTORY_SEPARATOR . $file,
                $this->fixtureAppPath . DIRECTORY_SEPARATOR . $file,
            );
        }

        foreach ($modules as $module) {
            $this->stageModule($module);
        }
    }

    /**
     * Copy a single fixture module into the staged application.
     *
     * Exposed so a test can add a module after the application has already been
     * cached, which is how a stale module cache is reproduced.
     *
     * @param  string  $module
     * @return void
     */
    protected function stageModule(string $module): void
    {
        $this->copyDirectory(
            self::fixtureSourcePath() . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . $module,
            $this->fixtureAppPath . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . $module,
        );
    }

    /**
     * Run an artisan command inside the staged application as a separate
     * process.
     *
     * @param  list<string>  $command
     * @return \Symfony\Component\Process\Process
     */
    protected function runFixtureArtisan(array $command): Process
    {
        $process = new Process(
            [PHP_BINARY, 'artisan', ...$command],
            $this->fixtureAppPath,
            ['LARAVEL_MODULES_FIXTURE_AUTOLOAD' => dirname(__DIR__, 3) . '/vendor/autoload.php'],
        );

        $process->run();

        return $process;
    }

    /**
     * Remove the staged fixture application.
     *
     * @return void
     */
    protected function removeFixtureApp(): void
    {
        if ($this->fixtureAppPath === '' || !is_dir($this->fixtureAppPath)) {
            return;
        }

        $this->removeDirectory($this->fixtureAppPath); // @phpstan-ignore method.notFound (removeDirectory from sibling trait)
    }

    /**
     * Return the path to the committed fixture application.
     *
     * @return string
     */
    private static function fixtureSourcePath(): string
    {
        return dirname(__DIR__, 2) . '/Fixtures/app';
    }

    /**
     * Recursively copy a directory.
     *
     * @param  string  $source
     * @param  string  $destination
     * @return void
     */
    private function copyDirectory(string $source, string $destination): void
    {
        mkdir($destination, 0755, true);

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($items as $item) {

            $target = $destination
                . DIRECTORY_SEPARATOR
                . substr($item->getPathname(), strlen($source) + 1);

            if ($item->isDir()) {
                mkdir($target, 0755, true);
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }
}
