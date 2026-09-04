<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\TrackPageViews::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/mailgun/*',
        ]);

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'verify.mailgun.signature' => \App\Http\Middleware\VerifyMailgunSignature::class,
            // #25 — delivery events nest the signature fields and arrive as
            // JSON. A separate alias, not a widening: see
            // VerifyMailgunEventSignature's docblock.
            'verify.mailgun.event.signature' => \App\Http\Middleware\VerifyMailgunEventSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
