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
            && ! $request->is('health')) {
            $destination = strtolower((string) $request->header('Sec-Fetch-Dest'));

            if (in_array($destination, ['', 'document'], true)) {
                abort(403, 'Acesso bloqueado. Abra esta aplicação pelo iframe do sistema GI.');
            }

            if (! $request->routeIs('auth.gi') && ! $request->session()->has('gi_context')) {
                abort(403, 'Acesso bloqueado. Esta chamada requer uma sessão iniciada pelo sistema GI.');
            }
        }

        $response = $next($request);

        $response->headers->remove('X-Frame-Options');
        $response->headers->set(
            'Content-Security-Policy',
            "frame-ancestors ".config('gi.frame_ancestors')."; object-src 'none'; base-uri 'self'",
        );

        return $response;
    }
}