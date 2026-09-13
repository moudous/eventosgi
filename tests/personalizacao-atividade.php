<?php
// Execute com: php tests/personalizacao-atividade.php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\AtividadeController;
use App\Models\Atividade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);
Schema::create('eventos', fn ($table) => $table->id());
DB::table('eventos')->insert(['id' => 1]);

$atividade = new Atividade;
$atividade->forceFill([
    'tipo' => 'somente_inscricao',
    'formato' => 'simples',
    'personalizacao' => [
        'imagem' => '11111111-1111-1111-1111-111111111111.jpg',
        'posicao' => 'esquerda',
        'borda' => false,
        'cor_borda' => '#ffffff',
        'usar_formatacao_evento' => true,
        'tipo' => 'degrade', 'degrade_inicio' => '#102a43', 'degrade_fim' => '#176b87',
        'cor_solida' => '#102a43', 'cor_fonte' => '#ffffff',
        'cor_borda_card' => '#ffffff',
        'alterar_cor_fundo_pagina' => false,
        'cor_fundo_pagina' => '#ffffff',
        'fundo_pagina_tipo' => 'cor',
        'imagem_fundo_card' => '22222222-2222-2222-2222-222222222222.jpg',
        'imagem_fundo_pagina' => '33333333-3333-3333-3333-333333333333.jpg',
    ],
]);

$validar = new ReflectionMethod(AtividadeController::class, 'validar');
$dadosBase = [
    'nome' => 'Atividade', 'tipo' => 'somente_inscricao', 'formato' => 'simples',
    'ativo' => 1, 'evento_id' => 1,
    'personalizacao' => [
        'posicao' => 'esquerda', 'borda' => 0, 'cor_borda' => '#ffffff',
        'usar_formatacao_evento' => 0, 'tipo' => 'transparente_borda',
        'degrade_inicio' => '#102a43', 'degrade_fim' => '#176b87',
        'cor_solida' => '#102a43', 'cor_fonte' => '#ffffff',
        'cor_borda_card' => '#ffffff',
        'alterar_cor_fundo_pagina' => 0, 'cor_fundo_pagina' => '#ffffff',
        'fundo_pagina_tipo' => 'cor',
    ],
];
$executar = function (array $remocoes) use ($app, $atividade, $dadosBase, $validar): array {
    $request = Request::create('/', 'PUT', $dadosBase + $remocoes);
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('gi_context.permissoes', ['atividades.personalizar']);
    $app->instance('request', $request);

    return $validar->invoke(new AtividadeController, $request, $atividade);
};

$preservada = $executar(['remover_imagem_atividade' => 0]);
if ($preservada['personalizacao']['imagem'] !== '11111111-1111-1111-1111-111111111111.jpg') {
    throw new RuntimeException('A imagem existente não foi preservada.');
}
if ($preservada['personalizacao']['tipo'] !== 'transparente_borda') {
    throw new RuntimeException('O fundo transparente com borda não foi aceito.');
}
$atividade->forceFill(['personalizacao' => $preservada['personalizacao']]);
if ($atividade->fundoFormulario() !== 'transparent' || $atividade->bordaFormulario() !== '1px solid #ffffff') {
    throw new RuntimeException('O estilo transparente com borda não foi aplicado.');
}
if ($preservada['personalizacao']['imagem_fundo_card'] !== '22222222-2222-2222-2222-222222222222.jpg'
    || $preservada['personalizacao']['imagem_fundo_pagina'] !== '33333333-3333-3333-3333-333333333333.jpg') {
    throw new RuntimeException('As imagens de fundo não foram preservadas ao alternar a formatação.');
}
$removida = $executar(['remover_imagem_atividade' => 1, 'remover_imagem_fundo_card_atividade' => 1, 'remover_imagem_fundo_pagina_atividade' => 1]);
if ($removida['personalizacao']['imagem'] !== null || $removida['personalizacao']['imagem_fundo_card'] !== null || $removida['personalizacao']['imagem_fundo_pagina'] !== null) {
    throw new RuntimeException('A remoção da imagem não foi aplicada.');
}

echo "OK: preservação e remoção das imagens da atividade validadas.\n";
