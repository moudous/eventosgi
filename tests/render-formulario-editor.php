<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Atividade;
use App\Services\GiPermissionService;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\ViewErrorBag;

$request = Request::create('/atividades/3/formulario');
$request->setLaravelSession(new Store('editor', new ArraySessionHandler(120)));
$request->session()->put('gi_context.permissoes', [
    'atividades.formulario.estrutura', 'atividades.visualizar_formulario', 'atividades.inscritos',
]);
$app->instance('request', $request);
view()->share('errors', new ViewErrorBag);

$atividade = new Atividade(['nome' => 'Atividade', 'formulario' => [
    'editor' => ['exibir' => true, 'conteudo' => '<p>Conteúdo</p>'], 'campos' => [],
]]);
$atividade->id = 3;
$atividade->hash_publica = str_repeat('a', 64);
$atividade->exists = true;
$html = view('atividades.formulario', [
    'atividade' => $atividade,
    'permissoes' => app(GiPermissionService::class),
])->render();

if (! str_contains($html, 'id="editor_exibir"') || ! str_contains($html, 'new Quill')) {
    throw new RuntimeException('O editor rico não foi renderizado.');
}
preg_match_all('#<script(?:\s[^>]*)?>(.*?)</script>#s', $html, $scripts);
file_put_contents('/tmp/formulario-editor-renderizado.js', implode("\n", $scripts[1]));
echo "OK: editor rico renderizado para validação do JavaScript.\n";
