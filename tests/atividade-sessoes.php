<?php
// Execute com: php tests/atividade-sessoes.php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Atividade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
$schema = Schema::getFacadeRoot();
$schema->create('atividades', function ($table): void {
    $table->id(); $table->string('tipo')->default('atividade_evento'); $table->longText('formulario')->nullable();
    $table->timestamps(); $table->softDeletes();
});
$schema->create('inscricoes_atividade', function ($table): void {
    $table->id(); $table->unsignedBigInteger('atividade_id'); $table->json('resposta'); $table->timestamps();
});
$schema->create('eventos', fn ($table) => $table->id());
DB::table('eventos')->insert(['id' => 1]);
(require __DIR__.'/../database/migrations/2026_09_11_140000_add_formato_and_sessoes_to_atividades.php')->up();

$atividadeId = DB::table('atividades')->insertGetId([
    'tipo' => 'atividade_evento', 'formulario' => null, 'created_at' => now(), 'updated_at' => now(),
]);
$atividade = Atividade::findOrFail($atividadeId);
if ($atividade->formato !== 'simples') throw new RuntimeException('Atividade antiga não recebeu o formato simples.');

$atividade->update(['formato' => 'com_sessoes']);
$sessao = $atividade->sessoes()->create(['nome' => 'Manhã', 'limite_vagas' => 1, 'ativo' => true]);
DB::table('inscricoes_atividade')->insert([
    'atividade_id' => $atividade->id, 'sessao_atividade_id' => $sessao->id,
    'resposta' => '{}', 'created_at' => now(), 'updated_at' => now(),
]);

if ($sessao->fresh()->vagasRestantes() !== 0) throw new RuntimeException('A cota da sessão não foi consumida.');
if (! $atividade->fresh()->vagasEsgotadas()) throw new RuntimeException('A atividade deveria estar esgotada.');

$semLimite = $atividade->sessoes()->create(['nome' => 'Tarde', 'limite_vagas' => null, 'ativo' => true]);
if ($semLimite->vagasRestantes() !== null) throw new RuntimeException('Sessão sem cota não deveria ter saldo numérico.');
if ($atividade->fresh()->vagasEsgotadas()) throw new RuntimeException('Sessão sem limite deve manter a atividade aberta.');

$validar = new ReflectionMethod(App\Http\Controllers\AtividadeController::class, 'validar');
$request = Request::create('/', 'POST', [
    'nome' => 'Atividade com turmas', 'tipo' => 'atividade_evento', 'formato' => 'com_sessoes',
    'ativo' => 1, 'evento_id' => 1,
    'sessoes' => [['nome' => 'Noite', 'data_inicio' => '2026-10-01T19:00', 'data_fim' => '2026-10-01T22:00', 'limite_vagas' => 30]],
]);
$request->setLaravelSession(new Store('sessoes', new ArraySessionHandler(120)));
$app->instance('request', $request);
$dados = $validar->invoke(new App\Http\Controllers\AtividadeController, $request);
if (($dados['sessoes'][0]['limite_vagas'] ?? null) !== 30) throw new RuntimeException('As sessões não passaram pela validação do cadastro.');

echo "OK: compatibilidade de atividades simples e cotas de sessões validada.\n";
