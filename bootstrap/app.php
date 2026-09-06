<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AllowGiEmbedding;
use App\Http\Middleware\RequireGiPermission;
use App\Http\Middleware\VerificarTokenFormulario;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        health: '/health',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AllowGiEmbedding::class);
        $middleware->alias([
            'gi.permission' => RequireGiPermission::class,
            'formulario.token' => VerificarTokenFormulario::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();