<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
            ResolveTenant::class,
        ]);

        $middleware->web(prepend: [
            ResolveTenant::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            // SPA uses cookie + CSRF header; keep defaults
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
