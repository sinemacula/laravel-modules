<?php

declare(strict_types = 1);

namespace Benchmarks\Support;

use Illuminate\Filesystem\Filesystem;
use SineMacula\Laravel\Modules\Configuration\Modules;

/**
 * Temporary module tree fixture for the benchmark suite.
 *
 * Builds a realistic on-disk modular application under the system temp
 * directory and points the Modules resolver at it. Also exposes a reset helper
 * so individual benchmark revolutions can measure cold discovery without the
 * resolver's in-memory cache short-circuiting the work.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 */
final readonly class ModuleTree
{
    /** @var list<string> The directory structure created within each module. */
    private const array MODULE_DIRECTORIES = [
        'Resources/views',
        'Resources/lang',
        'Http',
        'Console/Commands',
        'Listeners',
    ];

    /** @var string The root path of the temporary application. */
    private string $basePath;

    /**
     * @param  string  $basePath
     * @return void
     */
    private function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Build a fresh module tree and configure the resolver to use it.
     *
     * @param  int  $moduleCount
     * @param  bool  $withCache
     * @return self
     */
    public static function create(int $moduleCount = 12, bool $withCache = false): self
    {
        $basePath = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'modules_bench_' . uniqid();

        $tree = new self($basePath);
        $tree->build($moduleCount);

        Modules::setBasePath($basePath);
        self::reset();

        if ($withCache) {
            Modules::cache();
            self::reset();
        }

        return $tree;
    }

    /**
     * Flush the resolver's in-memory state so the next call re-discovers and
     * re-resolves from disk.
     *
     * @return void
     */
    public static function reset(): void
    {
        Modules::flush();
    }

    /**
     * Remove the temporary module tree from disk.
     *
     * @return void
     */
    public function destroy(): void
    {
        self::reset();

        (new Filesystem)->deleteDirectory($this->basePath);
    }

    /**
     * Create the module directories and supporting files on disk.
     *
     * @param  int  $moduleCount
     * @return void
     */
    private function build(int $moduleCount): void
    {
        // The foundation module is the default for resourcePath() resolution.
        $this->createModule('foundation');

        for ($index = 0; $index < $moduleCount; $index++) {
            $this->createModule('module' . $index);
        }

        $this->makeDirectory('bootstrap' . DIRECTORY_SEPARATOR . 'cache');
    }

    /**
     * Create a single fully populated module directory.
     *
     * @param  string  $name
     * @return void
     */
    private function createModule(string $name): void
    {
        $module = 'modules' . DIRECTORY_SEPARATOR . $name;

        foreach (self::MODULE_DIRECTORIES as $directory) {
            $this->makeDirectory(
                $module . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory),
            );
        }

        $this->writePhpStub($module . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'routes.php');
        $this->writePhpStub($module . DIRECTORY_SEPARATOR . 'Console' . DIRECTORY_SEPARATOR . 'schedule.php');
    }

    /**
     * Create a directory relative to the base path.
     *
     * @param  string  $relativePath
     * @return void
     */
    private function makeDirectory(string $relativePath): void
    {
        $path = $this->basePath . DIRECTORY_SEPARATOR . $relativePath;

        if (is_dir($path)) {
            return;
        }

        mkdir($path, 0755, true);
    }

    /**
     * Write an empty PHP stub file relative to the base path.
     *
     * @param  string  $relativePath
     * @return void
     */
    private function writePhpStub(string $relativePath): void
    {
        file_put_contents($this->basePath . DIRECTORY_SEPARATOR . $relativePath, "<?php\n");
    }
}
