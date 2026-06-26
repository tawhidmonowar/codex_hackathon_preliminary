<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\ForceJsonResponse::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always render exceptions as JSON since this is a pure API service
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => true,
        );
    })->create();

