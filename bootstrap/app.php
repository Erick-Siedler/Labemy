<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'payment.token' => \App\Http\Middleware\PaymentTokenMiddleware::class,
            'tenant.selected' => \App\Http\Middleware\EnsureTenantSelection::class,
            'tenant.role' => \App\Http\Middleware\EnsureTenantRole::class,
            'session.timeout' => \App\Http\Middleware\SessionTimeoutMiddleware::class,
        ]);
        $middleware->append(\App\Http\Middleware\LogDeniedAccess::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
