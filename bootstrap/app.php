<?php

use App\Exceptions\MyCustomErrorHandler;
use App\Http\Middleware\ClientToken;
use App\Http\Middleware\isAdmin;
use App\Http\Middleware\isMember;
use App\Http\Middleware\JwtMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('auth.client', [
            ClientToken::class
        ]);
        $middleware->appendToGroup('auth.jwt', [
            ClientToken::class,
            JwtMiddleware::class
        ]);
        $middleware->appendToGroup('auth.admin', [
            ClientToken::class,
            JwtMiddleware::class,
            isAdmin::class
        ]);
        $middleware->appendToGroup('auth.member', [
            ClientToken::class,
            JwtMiddleware::class,
            isMember::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->renderable(function (\Exception $e) {
            return MyCustomErrorHandler::handle($e);
        });
    })->create();
