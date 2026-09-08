<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowGiEmbedding
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! filter_var(config('gi.allow_outside_iframe'), FILTER_VALIDATE_BOOL)
            && ! $this->temAutenticacaoPropria($request)) {
            $destination = strtolower((string) $request->header('Sec-Fetch-Dest'));

            if (in_array($destination, ['', 'document'], true)) {
                abort(403, 'Acesso bloqueado. Abra esta aplicação pelo iframe do sistema GI.');
            }

            // O callback do GI e quem cria a sessao, entao ele nao pode exigir uma sessao ja iniciada.
            if (! $request->routeIs('auth.gi')
                && ! ($request->hasSession() && $request->session()->has('gi_context'))) {
                abort(403, 'Acesso bloqueado. Esta chamada requer uma sessão iniciada pelo sistema GI.');
            }
        }

        $response = $next($request);

        $response->headers->remove('X-Frame-Options');

        // Estas páginas foram feitas para visitantes e podem ser incorporadas em sites
        // externos. As demais continuam limitadas às origens configuradas para o GI.
        if ($request->routeIs('eventos.pagina.visualizar', 'inscricoes.publica*', 'submissoes.publicas.*')) {
            $response->headers->set(
                'Content-Security-Policy',
                "frame-ancestors *; object-src 'none'; base-uri 'self'",
            );
        } elseif (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set(
                'Content-Security-Policy',
                "frame-ancestors ".config('gi.frame_ancestors')."; object-src 'none'; base-uri 'self'",
            );
        }

        return $response;
    }

    /**
     * Rotas que nao dependem da sessao do GI porque provam o acesso de outro jeito.
     *
     * Sao os pontos de entrada legitimos fora do iframe: o formulario publico de inscricao
     * e os arquivos que ele gera, abertos por visitantes anonimos ou em aba nova. Exigir o
     * iframe neles derrubaria justamente quem tem direito de entrar.
     *
     * A página pública do evento é a exceção deliberadamente anônima: só eventos ativos
     * são entregues e a resposta define sua própria política de incorporação.
     */
    private function temAutenticacaoPropria(Request $request): bool
    {
        if ($request->is('health') || $request->is('api/*')) {
            return true;
        }

        if ($request->routeIs('eventos.pagina.visualizar', 'inscricoes.publica*', 'submissoes.publicas.*')) {
            return true;
        }

        return in_array('signed', $request->route()?->gatherMiddleware() ?? [], true);
    }
}
