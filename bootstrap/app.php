<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureInstallationBoundary;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [EnsureInstallationBoundary::class], append: [EnsureAccountIsActive::class]);
        $middleware->redirectUsersTo('/app');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response) {
            if (request()->is('login', 'logout', 'forgot-password', 'reset-password', 'reset-password/*', 'two-factor-challenge', 'user/*', 'account/*', 'app', 'app/*')) {
                $response->headers->set('Cache-Control', 'no-store, private');
                $response->headers->set('X-Frame-Options', 'DENY');
                $response->headers->set('X-Content-Type-Options', 'nosniff');
                $response->headers->set('Referrer-Policy', 'same-origin');
            }

            return $response;
        });
    })->create();
