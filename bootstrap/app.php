<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\RecomputeConflictsCommand::class,
    ])
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('conflicts:recompute --active-or-scheduling --queue')
            ->dailyAt('02:15')
            ->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $proxyWildcard = chr(42);
        $trustedProxies = env('TRUSTED_PROXIES', $proxyWildcard);
        $proxyList = $trustedProxies === $proxyWildcard
            ? $proxyWildcard
            : array_map('trim', explode(',', $trustedProxies));

        $middleware->trustProxies(
            at: $proxyList,
            headers: SymfonyRequest::HEADER_X_FORWARDED_FOR
                | SymfonyRequest::HEADER_X_FORWARDED_HOST
                | SymfonyRequest::HEADER_X_FORWARDED_PORT
                | SymfonyRequest::HEADER_X_FORWARDED_PROTO
                | SymfonyRequest::HEADER_X_FORWARDED_PREFIX
        );

        $middleware->web(append: [
            \App\Http\Middleware\CheckActiveUser::class,
        ]);

        $middleware->alias([
            'no-back' => \App\Http\Middleware\PreventBackHistory::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
