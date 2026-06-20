<?php

namespace SineMacula\Laravel\Modules\Exceptions;

/**
 * Exception thrown when a module operation cannot be completed.
 *
 * Raised for configuration faults (such as a missing base path) and for cache
 * read/write failures during module discovery.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited
 */
class ModuleException extends \RuntimeException {}
