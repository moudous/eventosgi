<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Atividade;
use App\Services\InscricoesExportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

config([
    'database.default' => 'teste_exportacao',
    'database.connections.teste_exportacao' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
    'database.connections.cert' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
]);
DB::purge('teste_exportacao');
DB::purge('cert');

Schema::connection('teste_exportacao')->create('inscricoes_atividade', function (Blueprint $table): void {
    $table->id();
    $table->unsignedBigInteger('atividade_id');
    $table->unsignedBigInteger('participante_id')->nullable();
    $table->string('participante_email')->nullable();
    $table->json('resposta')->nullable();
    $table->string('ip')->nullable();
    $table->text('user_agent')->nullable();
    $table->json('dispositivo')->nullable();
    $table->boolean('presente')->default(false);
    $table->dateTime('data_presenca')->nullable();
    $table->unsignedBigInteger('presenca_validada_por')->nullable();
    $table->string('codigo_qr')->nullable();
    $table->timestamps();
});
Schema::connection('cert')->create('participantes', function (Blueprint $table): void {
    $table->id();
    $table->string('nome');
    $table->dateTime('excluido_em')->nullable();
});

DB::connection('cert')->table('participantes')->insert(['id' => 9, 'nome' => 'Pessoa Teste']);
DB::connection('teste_exportacao')->table('inscricoes_atividade')->insert([
    'id' => 7,
    'atividade_id' => 40,
    'participante_id' => 9,
    'participante_email' => 'pessoa@example.com',
    'resposta' => json_encode(['disponibilidade' => '1']),
    'ip' => '127.0.0.1',
    'dispositivo' => json_encode(['sessao' => 'segredo']),
    'codigo_qr' => 'EVGI-TESTE',
    'presente' => false,
    'created_at' => now(),
    'updated_at' => now(),
]);

$atividade = (new Atividade)->forceFill([
    'id' => 40,
    'nome' => 'Atividade Teste',
    'formulario' => ['campos' => [[
        'nome' => 'disponibilidade',
        'label' => 'Declaro ter disponibilidade para participar das reuniões',
        'tipo' => 'checkbox',
        'opcoes' => [],
    ]]],
]);
$atividade->exists = true;

$servico = app(InscricoesExportService::class);
$disponiveis = collect($servico->camposDisponiveis($atividade))->keyBy('chave');
if (! $disponiveis['resposta:disponibilidade']['marcado'] || $disponiveis['codigo_qr']['marcado'] || $disponiveis['ip']['marcado'] || $disponiveis['sessao']['marcado']) {
    throw new RuntimeException('A seleção inicial dos campos da exportação está incorreta.');
}

$csv = $servico->download($atividade, 'csv', ['id', 'resposta:disponibilidade'])->getContent();
if (! str_contains($csv, 'Declaro ter disponibilidade para participar das reuniões') || ! str_contains($csv, 'Sim')) {
    throw new RuntimeException('O checkbox marcado não foi exportado como Sim.');
}
if (str_contains($csv, 'Código QR') || str_contains($csv, 'EVGI-TESTE') || str_contains($csv, '127.0.0.1') || str_contains($csv, 'segredo')) {
    throw new RuntimeException('A exportação incluiu campos técnicos que não foram selecionados.');
}

DB::connection('teste_exportacao')->table('inscricoes_atividade')->update(['resposta' => json_encode([])]);
$csvNao = $servico->download($atividade, 'csv', ['resposta:disponibilidade'])->getContent();
if (! str_contains($csvNao, 'Não')) {
    throw new RuntimeException('O checkbox não marcado não foi exportado como Não.');
}

foreach (['ods', 'xls', 'xlsx'] as $formato) {
    $arquivo = $servico->download($atividade, $formato, ['id', 'resposta:disponibilidade']);
    if (strlen((string) $arquivo->getContent()) < 100) {
        throw new RuntimeException("O arquivo {$formato} não foi gerado.");
    }
}

echo "OK: seleção de colunas e checkbox Sim/Não exportados em todos os formatos.\n";
