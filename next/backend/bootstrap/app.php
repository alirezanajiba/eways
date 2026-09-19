<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // React clients are served from the same trusted origin. Sensitive Eways
        // credentials/tokens never leave the encrypted HttpOnly Laravel session.
        $middleware->validateCsrfTokens(except: [
            'api/v1/eways/*',
            'api/v1/orders',
            'api/v1/admin/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Per-controller errors are intentionally returned as JSON during migration.
    })->create();
