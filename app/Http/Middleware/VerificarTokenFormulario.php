<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarTokenFormulario
{
    public function handle(Request $request, Closure $next): Response
    {
        $esperado = (string) config('formularios.token');
        $recebido = (string) ($request->header('X-Formulario-Token') ?: $request->bearerToken());

        abort_if($esperado === '', 503, 'A API de formulários não está configurada.');
        abort_unless(hash_equals($esperado, $recebido), 401, 'Token inválido.');

        $response = $next($request);

        $origem = (string) $request->header('Origin');
        if ($origem !== '' && in_array($origem, (array) config('formularios.origens'), true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origem);
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }
}
