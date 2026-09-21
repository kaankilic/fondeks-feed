<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Same-origin/local: the API always answers under `/api`, so the
            // admin panel (root) and the API can be served from one host.
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Production: the API additionally answers at the root of its own
            // subdomain (api.fondeks.com), with no `/api` in the path.
            if ($apiDomain = config('app.api_domain')) {
                Route::middleware('api')
                    ->domain($apiDomain)
                    ->group(base_path('routes/api.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            $apiDomain = config('app.api_domain');

            return $request->is('api/*')
                || ($apiDomain && $request->getHost() === $apiDomain)
                || $request->expectsJson();
        });
    })->create();
