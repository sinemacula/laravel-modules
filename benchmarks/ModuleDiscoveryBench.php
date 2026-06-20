<?php

namespace Benchmarks;

use Benchmarks\Support\ModuleTree;
use PhpBench\Attributes as Bench;
use SineMacula\Laravel\Modules\Configuration\Modules;

/**
 * Benchmarks for the module discovery and path resolution hot paths.
 *
 * Covers the three regimes that matter at runtime: cold discovery straight off
 * the filesystem (a fresh boot with no cache), cache-backed resolution (the
 * `php artisan optimize` path), and warm resolution where the resolver's
 * in-memory cache is already primed (repeated calls within a request).
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 */
#[Bench\OutputTimeUnit('microseconds')]
final class ModuleDiscoveryBench
{
    /** @var \Benchmarks\Support\ModuleTree|null The temporary module tree under test. */
    private ?ModuleTree $tree = null;

    /**
     * Tear down the temporary module tree.
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->tree?->destroy();
    }

    /**
     * Build a module tree with no cache file for the cold-path benchmarks.
     *
     * @return void
     */
    public function setUpDiscovery(): void
    {
        $this->tree = ModuleTree::create(withCache: false);
    }

    /**
     * Build a module tree with a pre-written cache file for the cached path.
     *
     * @return void
     */
    public function setUpCache(): void
    {
        $this->tree = ModuleTree::create(withCache: true);
    }

    /**
     * Build a module tree and prime the resolver's in-memory cache for the
     * warm-path benchmarks.
     *
     * @return void
     */
    public function setUpWarm(): void
    {
        $this->tree = ModuleTree::create(withCache: false);

        Modules::routePaths();
        Modules::resourcePath('foundation::views');
    }

    /**
     * Benchmark cold module discovery straight off the filesystem.
     *
     * @return void
     */
    #[Bench\AfterMethods('tearDown')]
    #[Bench\BeforeMethods('setUpDiscovery')]
    #[Bench\Iterations(5)]
    #[Bench\Revs(200)]
    #[Bench\Warmup(2)]
    public function benchDiscoverModules(): void
    {
        ModuleTree::reset();

        Modules::getModules();
    }

    /**
     * Benchmark cold route path resolution: discovery plus a realpath pass
     * across every module.
     *
     * @return void
     */
    #[Bench\AfterMethods('tearDown')]
    #[Bench\BeforeMethods('setUpDiscovery')]
    #[Bench\Iterations(5)]
    #[Bench\Revs(200)]
    #[Bench\Warmup(2)]
    public function benchColdRoutePathResolution(): void
    {
        ModuleTree::reset();

        Modules::routePaths();
    }

    /**
     * Benchmark cache-backed resolution: loading the cached module map and
     * resolving route paths from it.
     *
     * @return void
     */
    #[Bench\AfterMethods('tearDown')]
    #[Bench\BeforeMethods('setUpCache')]
    #[Bench\Iterations(5)]
    #[Bench\Revs(200)]
    #[Bench\Warmup(2)]
    public function benchCachedRoutePathResolution(): void
    {
        ModuleTree::reset();

        Modules::routePaths();
    }

    /**
     * Benchmark warm route path resolution where the resolved paths are already
     * cached in memory.
     *
     * @return void
     */
    #[Bench\AfterMethods('tearDown')]
    #[Bench\BeforeMethods('setUpWarm')]
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchWarmRoutePathResolution(): void
    {
        Modules::routePaths();
    }

    /**
     * Benchmark resource path resolution including module prefix extraction
     * against a warm resolver.
     *
     * @return void
     */
    #[Bench\AfterMethods('tearDown')]
    #[Bench\BeforeMethods('setUpWarm')]
    #[Bench\Iterations(5)]
    #[Bench\Revs(1000)]
    #[Bench\Warmup(2)]
    public function benchResourcePathResolution(): void
    {
        Modules::resourcePath('foundation::views');
    }

    /**
     * Benchmark the module_path() helper.
     *
     * @return void
     */
    #[Bench\AfterMethods('tearDown')]
    #[Bench\BeforeMethods('setUpDiscovery')]
    #[Bench\Iterations(5)]
    #[Bench\Revs(2000)]
    #[Bench\Warmup(2)]
    public function benchModulePathHelper(): void
    {
        module_path('Billing' . DIRECTORY_SEPARATOR . 'Models');
    }
}
