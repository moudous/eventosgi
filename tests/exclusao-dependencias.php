<?php

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Illuminate\Http\Request::create('/', 'DELETE');
$historico = new App\Services\HistoricoService();
$verificados = 0;
$garantir = static function (bool $condicao, string $mensagem): void {
    if (! $condicao) throw new RuntimeException($mensagem);
};

$evento = App\Models\Evento::query()
    ->where(fn ($consulta) => $consulta->whereHas('atividades')->orWhereHas('submissoes'))
    ->first();
if ($evento) {
    $resposta = (new App\Http\Controllers\EventoController())->destroy($request, $evento, $historico);
    $garantir($resposta->getStatusCode() === 409, 'Evento vinculado não retornou HTTP 409.');
    $garantir(App\Models\Evento::query()->whereKey($evento->id)->exists(), 'Evento vinculado foi excluído.');
    $verificados++;
}

$atividade = App\Models\Atividade::query()->whereHas('inscricoes')->first();
if ($atividade) {
    $resposta = (new App\Http\Controllers\AtividadeController())->destroy($request, $atividade, $historico);
    $garantir($resposta->getStatusCode() === 409, 'Atividade com inscritos não retornou HTTP 409.');
    $garantir(App\Models\Atividade::query()->whereKey($atividade->id)->exists(), 'Atividade com inscritos foi excluída.');
    $verificados++;
}

$submissao = App\Models\Submissao::query()->whereHas('inscricoes')->first();
if ($submissao) {
    $resposta = (new App\Http\Controllers\SubmissaoController())->destroy($submissao);
    $garantir($resposta->getStatusCode() === 409, 'Submissão com inscritos não retornou HTTP 409.');
    $garantir(App\Models\Submissao::query()->whereKey($submissao->id)->exists(), 'Submissão com inscritos foi excluída.');
    $verificados++;
}

$categoria = App\Models\Categoria::query()->whereHas('atividades', fn ($consulta) => $consulta->withTrashed())->first();
if ($categoria) {
    $resposta = (new App\Http\Controllers\CategoriaController())->destroy($categoria);
    $garantir($resposta->getStatusCode() === 409, 'Categoria vinculada não retornou HTTP 409.');
    $garantir(App\Models\Categoria::query()->whereKey($categoria->id)->exists(), 'Categoria vinculada foi excluída.');
    $verificados++;
}

echo "Bloqueios de exclusão verificados: {$verificados}\n";
