<?php

use App\Http\Middleware\EnsureCapability;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->alias(['capability' => EnsureCapability::class]);

        // Laravel's ApplicationBuilder::withMiddleware() unconditionally
        // defaults guest-redirects to route('login') — see vendor/laravel/
        // framework/.../Configuration/ApplicationBuilder.php. api/ has no
        // web login page (it's API only, see README's layout section), so
        // no route is named `login`, and that default throws
        // RouteNotFoundException instead of the AuthenticationException
        // it's meant to carry, the moment a request doesn't send
        // `Accept: application/json` (a bare curl with no headers, for
        // instance) and hits the `auth` middleware while unauthenticated.
        // Overriding to null here lets that exception construct cleanly.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Belt-and-suspenders with the redirectGuestsTo override above:
        // Illuminate\Foundation\Exceptions\Handler::unauthenticated() has
        // its own separate route('login') fallback for non-JSON-expecting
        // requests, which would otherwise crash the same way even after
        // the exception itself constructs fine. api/ never returns
        // anything but JSON — it's API only, no views, no web login page —
        // so every response should be JSON regardless of what a client's
        // Accept header does or doesn't say; an auth failure must be 401
        // unconditionally, not dependent on the caller remembering that
        // header.
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();
