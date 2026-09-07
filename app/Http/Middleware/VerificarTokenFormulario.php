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

        // Quem chama a API e o servidor do consumidor (ex.: WordPress), nao o visitante.
        // Com o token conferido, DispositivoVisitanteService pode confiar nos cabecalhos
        // X-Visitante-* que ele repassa; sem esta marca eles sao ignorados.
        $request->attributes->set('formulario_autenticado', true);

        $response = $next($request);

        $origem = (string) $request->header('Origin');
        if ($origem !== '' && in_array($origem, (array) config('formularios.origens'), true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origem);
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }
}
