<?php

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use SineMacula\Laravel\Modules\Application;
use SineMacula\Laravel\Modules\Configuration\Modules;

Modules::setBasePath(dirname(__DIR__));

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api      : Modules::routePaths(),
        health   : '/health',
        apiPrefix: '',
    )
    ->withMiddleware(static function (Middleware $middleware): void {})
    ->withExceptions(static function (Exceptions $exceptions): void {})
    ->create();
