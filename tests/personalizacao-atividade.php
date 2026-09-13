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
        'alterar_cor_fundo_pagina' => false,
        'cor_fundo_pagina' => '#ffffff',
    ],
]);

$validar = new ReflectionMethod(AtividadeController::class, 'validar');
$dadosBase = [
    'nome' => 'Atividade', 'tipo' => 'somente_inscricao', 'formato' => 'simples',
    'ativo' => 1, 'evento_id' => 1,
    'personalizacao' => [
        'posicao' => 'esquerda', 'borda' => 0, 'cor_borda' => '#ffffff',
        'alterar_cor_fundo_pagina' => 0, 'cor_fundo_pagina' => '#ffffff',
    ],
];
$executar = function (int $remover) use ($app, $atividade, $dadosBase, $validar): array {
    $request = Request::create('/', 'PUT', $dadosBase + ['remover_imagem_atividade' => $remover]);
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('gi_context.permissoes', ['atividades.personalizar']);
    $app->instance('request', $request);

    return $validar->invoke(new AtividadeController, $request, $atividade);
};

if ($executar(0)['personalizacao']['imagem'] !== '11111111-1111-1111-1111-111111111111.jpg') {
    throw new RuntimeException('A imagem existente não foi preservada.');
}
if ($executar(1)['personalizacao']['imagem'] !== null) {
    throw new RuntimeException('A remoção da imagem não foi aplicada.');
}

echo "OK: preservação e remoção da imagem da atividade validadas.\n";
