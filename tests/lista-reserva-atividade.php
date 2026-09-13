<?php
// Execute com: php tests/lista-reserva-atividade.php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Atividade;
use App\Services\DistribuicaoVagasService;
use App\Services\FormularioInscricaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
Schema::create('atividades', function ($table): void {
    $table->id(); $table->string('formato')->default('simples'); $table->json('formulario')->nullable(); $table->timestamps(); $table->softDeletes();
});
Schema::create('inscricoes_atividade', function ($table): void {
    $table->id(); $table->unsignedBigInteger('atividade_id'); $table->unsignedBigInteger('sessao_atividade_id')->nullable();
    $table->unsignedBigInteger('participante_id')->nullable(); $table->string('participante_email')->nullable();
    $table->boolean('lista_reserva')->default(false); $table->json('resposta');
    $table->string('ip')->nullable(); $table->text('user_agent')->nullable(); $table->json('dispositivo')->nullable();
    $table->string('comprovante_hash', 64)->nullable()->unique(); $table->string('codigo_qr')->nullable(); $table->timestamps();
});

$config = [
    'limitar_inscricoes' => true,
    'limite_inscricoes' => 2,
    'apos_encerrar_vagas' => 'lista_reserva',
    'lista_reserva_sem_limite' => false,
    'limite_lista_reserva' => 1,
    'campos' => [],
];
DB::table('atividades')->insert(['id' => 1, 'formato' => 'simples', 'formulario' => json_encode($config), 'created_at' => now(), 'updated_at' => now()]);
DB::table('inscricoes_atividade')->insert([
    ['atividade_id' => 1, 'lista_reserva' => false, 'resposta' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ['atividade_id' => 1, 'lista_reserva' => false, 'resposta' => '{}', 'created_at' => now(), 'updated_at' => now()],
]);

$atividade = Atividade::findOrFail(1);
$distribuicao = app(DistribuicaoVagasService::class)->recalcular($atividade, salvar: false);
if (($distribuicao['distribuicao_vagas']['total']['usadas'] ?? null) !== 2) {
    throw new RuntimeException('As vagas regulares não foram contabilizadas corretamente.');
}
if (! $atividade->vagasRegularesEsgotadas() || ! $atividade->aceitandoListaReserva() || $atividade->vagasEsgotadas()) {
    throw new RuntimeException('A lista de reserva não abriu após o encerramento das vagas regulares.');
}

$resultado = app(FormularioInscricaoService::class)->inscrever(Request::create('/', 'POST'), $atividade);
if (! $resultado['sucesso'] || ! $resultado['lista_reserva']) {
    throw new RuntimeException('A inscrição além do limite não foi registrada como lista de reserva.');
}
$atividade->refresh();
$distribuicao = app(DistribuicaoVagasService::class)->recalcular($atividade, salvar: false);
if (($distribuicao['distribuicao_vagas']['total']['usadas'] ?? null) !== 2) {
    throw new RuntimeException('A inscrição além do limite consumiu uma vaga regular.');
}
if (! $atividade->vagasEsgotadas() || $atividade->aceitandoListaReserva()) {
    throw new RuntimeException('O limite da lista de reserva não encerrou as inscrições.');
}

$atividade->formulario = array_replace($config, ['lista_reserva_sem_limite' => true, 'limite_lista_reserva' => null]);
if ($atividade->vagasEsgotadas() || ! $atividade->aceitandoListaReserva()) {
    throw new RuntimeException('A lista de reserva sem limite foi encerrada indevidamente.');
}

echo "OK: abertura, limite, modo ilimitado e cotas regulares da lista de reserva validados.\n";
