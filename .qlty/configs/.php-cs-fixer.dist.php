<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$vendorDir = getenv('COMPOSER_VENDOR_DIR') ?: dirname(__DIR__, 2) . '/vendor';
$rules     = require $vendorDir . '/sinemacula/coding-standards/php/.php-cs-fixer.rules.php';

$finder = Finder::create()
    ->in([
        dirname(__DIR__, 2) . '/src',
        dirname(__DIR__, 2) . '/tests',
    ])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config)
    ->setFinder($finder)
    ->setUsingCache(true)
    ->setRiskyAllowed(true)
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules($rules);
