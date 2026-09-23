<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Resolve the interface language on every web request (after the session is
        // available, so a user's saved preference and a guest's session choice apply).
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\AddSecurityHeaders::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'perm' => \App\Http\Middleware\EnsurePermission::class,
            'password.changed' => \App\Http\Middleware\EnsurePasswordChanged::class,
            'account.active' => \App\Http\Middleware\EnsureAccountActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
