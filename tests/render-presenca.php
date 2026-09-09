<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Atividade;
use App\Models\InscricaoAtividade;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ViewErrorBag;

config(['database.connections.cert' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
Schema::connection('cert')->create('participantes', function (Blueprint $table): void {
    $table->id();
    $table->string('nome');
    $table->dateTime('excluido_em')->nullable();
});

$request = Request::create('/atividades/40/inscricoes');
$request->setLaravelSession(new Store('presenca', new ArraySessionHandler(120)));
$request->session()->put('gi_context.permissoes', ['atividades.validador_qr']);
$app->instance('request', $request);
$app->instance('session', $request->session());
view()->share('errors', new ViewErrorBag);

$atividade = (new Atividade)->forceFill(['id' => 40, 'nome' => 'Atividade', 'formulario' => ['campos' => [[
    'nome' => 'disponibilidade',
    'label' => 'Declaro ter disponibilidade para participar das reuniões',
    'tipo' => 'checkbox',
    'opcoes' => [],
]]]]);
$atividade->exists = true;
$inscricao = (new InscricaoAtividade)->forceFill([
    'id' => 7, 'atividade_id' => 40, 'resposta' => [], 'dispositivo' => [],
    'presente' => true, 'data_presenca' => now(), 'created_at' => now(),
]);
$inscricao->exists = true;
$inscricoes = new LengthAwarePaginator(collect([$inscricao]), 1, 20, 1, ['path' => $request->url()]);

$camposExportacao = [
    ['chave' => 'id', 'rotulo' => 'ID', 'grupo' => 'Campos principais', 'marcado' => true],
    ['chave' => 'codigo_qr', 'rotulo' => 'Código QR', 'grupo' => 'Dados técnicos', 'marcado' => false],
];
$html = view('atividades.inscricoes', compact('atividade', 'inscricoes', 'camposExportacao') + ['pesquisar' => ''])->render();
if (! str_contains($html, '<th>Presença</th>') || ! str_contains($html, '>Presente</span>')) {
    throw new RuntimeException('A coluna e o estado de presença não foram renderizados.');
}
if (! str_contains($html, 'title="Remover presença"') || ! str_contains($html, 'aria-label="Visualizar respostas"') || ! str_contains($html, 'id="respostaDataPresenca"')) {
    throw new RuntimeException('As ações por ícone da inscrição não foram renderizadas.');
}
if (! str_contains($html, 'id="exportacaoCamposModal"') || ! str_contains($html, 'value="id" data-padrao="1" checked') || ! str_contains($html, 'value="codigo_qr" data-padrao="0"')) {
    throw new RuntimeException('A seleção de campos da exportação não foi renderizada com os padrões esperados.');
}
if (! str_contains($html, 'Declaro ter disponibilidade para participar das reuniões') || ! str_contains($html, '>Não</span>')) {
    throw new RuntimeException('O checkbox simples sem resposta não foi exibido como Não.');
}

$dadosConfirmacao = [
    'mensagem' => 'Confira os dados.', 'status' => 'confirmacao', 'codigo_qr' => 'EVGI-TESTE',
    'inscricao' => 7, 'participante' => 'Pessoa Teste', 'email' => 'pessoa@example.com',
    'cpf' => '12345678901', 'atividade' => 'Atividade', 'evento' => 'Evento',
    'data_inscricao' => '09/09/2026 10:00:00', 'data_presenca' => null,
];
$request->session()->flash('presenca_confirmacao', $dadosConfirmacao);
$validador = view('atividades.validador-presenca')->render();
if (! str_contains($validador, 'Pessoa Teste') || ! str_contains($validador, 'Validar presença') || ! str_contains($validador, 'Não validar')) {
    throw new RuntimeException('A etapa de confirmação da presença não foi renderizada.');
}
if (! str_contains($validador, 'id="cameraErro"') || ! str_contains($validador, 'window.isSecureContext') || ! str_contains($validador, 'areaCamera.classList.remove')) {
    throw new RuntimeException('O diagnóstico e a inicialização móvel da câmera não foram renderizados.');
}
$request->session()->forget('presenca_confirmacao');
$request->session()->flash('presenca_resultado', [...$dadosConfirmacao, 'mensagem' => 'Validação de presença cancelada.', 'status' => 'cancelada']);
$resultadoCancelado = view('atividades.validador-presenca')->render();
if (! str_contains($resultadoCancelado, 'Validação de presença cancelada.') || ! str_contains($resultadoCancelado, 'Pessoa Teste')) {
    throw new RuntimeException('O resultado cancelado não manteve os dados da pessoa na tela.');
}
$index = view('atividades.index', [
    'apagados' => false,
    'estadoTabela' => ['por_pagina' => 10, 'page' => 1, 'pesquisar' => '', 'filtro_evento' => 0],
    'eventosFiltro' => collect(),
])->render();
if (! str_contains($index, 'Validar presença') || ! str_contains($index, route('atividades.validador-presenca'))) {
    throw new RuntimeException('O acesso ao validador não foi exibido na listagem de atividades.');
}

preg_match_all('#<script(?:\s[^>]*)?>(.*?)</script>#s', $html."\n".$validador."\n".$index, $scripts);
file_put_contents('/tmp/presenca-renderizada.js', implode("\n", $scripts[1]));

echo "OK: presença, ações e validador renderizados.\n";
