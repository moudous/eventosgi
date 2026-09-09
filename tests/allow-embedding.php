<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Middleware\AllowGiEmbedding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

config(['gi.allow_outside_iframe' => false]);
$middleware = new AllowGiEmbedding;
$publicas = [
    '/formularios/'.str_repeat('a', 64),
    '/biblioteca/arquivos/'.str_repeat('a', 36).'.png/visualizar',
    '/personalizacao/imagens/'.str_repeat('b', 36).'.jpg/visualizar',
    '/inscricoes/1/arquivos/documento/0/visualizar',
];

foreach ($publicas as $url) {
    $request = Request::create($url);
    $request->headers->set('Sec-Fetch-Dest', 'document');
    $rota = Route::getRoutes()->match($request);
    $request->setRouteResolver(fn () => $rota);
    $response = $middleware->handle($request, fn () => new Response('ok', 200, [
        'X-Frame-Options' => 'SAMEORIGIN',
        'Content-Security-Policy' => "default-src 'none'; sandbox",
    ]));
    if ($response->getStatusCode() !== 200
        || $response->headers->has('X-Frame-Options')
        || ! str_contains((string) $response->headers->get('Content-Security-Policy'), 'frame-ancestors *')) {
        throw new RuntimeException("A rota pública {$url} não aceitou incorporação externa.");
    }
}

config(['gi.allow_outside_iframe' => true]);
$validador = Request::create('/atividades/validador-presenca');
$rotaValidador = Route::getRoutes()->match($validador);
$validador->setRouteResolver(fn () => $rotaValidador);
$respostaValidador = $middleware->handle($validador, fn () => new Response('ok'));
if ($respostaValidador->headers->get('Permissions-Policy') !== 'camera=(self)') {
    throw new RuntimeException('A rota do validador não liberou a câmera para a própria origem.');
}

config(['gi.allow_outside_iframe' => false]);
$privada = Request::create('/atividades');
$privada->headers->set('Sec-Fetch-Dest', 'document');
$rota = Route::getRoutes()->match($privada);
$privada->setRouteResolver(fn () => $rota);
try {
    $middleware->handle($privada, fn () => new Response('não deveria abrir'));
    throw new RuntimeException('Uma rota administrativa ignorou indevidamente a configuração de iframe.');
} catch (HttpException $erro) {
    if ($erro->getStatusCode() !== 403) throw $erro;
}

echo "OK: formulários e arquivos ignoram a opção; rotas administrativas continuam protegidas.\n";
