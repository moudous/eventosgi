<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\SubmissaoController;
use App\Http\Controllers\AvaliadorController;
use App\Models\Avaliador;
use App\Models\InscricaoSubmissaoTrabalho;
use App\Models\Submissao;
use App\Services\ArmazemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);

Schema::create('usuarios', function ($table) {
    $table->unsignedBigInteger('id')->primary(); $table->string('nome'); $table->string('email')->nullable();
    $table->string('foto_url')->nullable(); $table->boolean('ativo')->default(true); $table->timestamps();
});
Schema::create('eventos', function ($table) { $table->id(); $table->string('nome'); $table->softDeletes(); });
Schema::create('submissoes', function ($table) {
    $table->id(); $table->unsignedBigInteger('evento_id'); $table->string('titulo');
    $table->dateTime('data_inicio'); $table->dateTime('data_fim'); $table->boolean('ativo'); $table->timestamps();
});
Schema::create('inscritos_submissao', function ($table) {
    $table->id(); $table->unsignedBigInteger('submissao_id'); $table->string('email'); $table->string('senha');
    $table->unsignedInteger('credencial_versao')->default(1); $table->timestamps();
});
Schema::create('avaliadores', function ($table) {
    $table->id(); $table->string('nome'); $table->unsignedBigInteger('usuario_id')->nullable()->unique(); $table->timestamps();
});
Schema::create('inscritos_submissao_trabalhos', function ($table) {
    $table->id(); $table->unsignedBigInteger('inscrito_submissao_id'); $table->unsignedBigInteger('avaliador_id')->nullable();
    $table->string('titulo_trabalho'); $table->string('status')->default('submetido'); $table->string('situacao')->nullable();
    $table->decimal('nota')->nullable(); $table->unsignedInteger('notificacao_resultado_versao')->default(0);
    $table->string('eposter_arquivo')->nullable(); $table->timestamps(); $table->softDeletes();
});
Schema::create('submissao_trabalho_historicos', function ($table) {
    $table->id(); $table->unsignedBigInteger('inscrito_submissao_trabalho_id'); $table->string('historico');
    $table->string('usuario')->nullable(); $table->json('dados')->nullable(); $table->dateTime('data_hora'); $table->timestamps();
});

DB::table('usuarios')->insert([
    ['id' => 10, 'nome' => 'Avaliadora Ana', 'email' => 'ana@example.test'],
    ['id' => 20, 'nome' => 'Avaliador Beto', 'email' => 'beto@example.test'],
]);
DB::table('eventos')->insert(['id' => 1, 'nome' => 'Evento']);
DB::table('submissoes')->insert(['id' => 1, 'evento_id' => 1, 'titulo' => 'Chamada', 'data_inicio' => now()->subDay(), 'data_fim' => now()->addDay(), 'ativo' => 1]);
DB::table('inscritos_submissao')->insert(['id' => 1, 'submissao_id' => 1, 'email' => 'autor@example.test', 'senha' => 'x']);
$ana = Avaliador::create(['nome' => 'Ana', 'usuario_id' => 10]);
$beto = Avaliador::create(['nome' => 'Beto', 'usuario_id' => 20]);
$semUsuario = Avaliador::create(['nome' => 'Comissão externa', 'usuario_id' => null]);
DB::table('inscritos_submissao_trabalhos')->insert([
    ['id' => 1, 'inscrito_submissao_id' => 1, 'avaliador_id' => $ana->id, 'titulo_trabalho' => 'Trabalho da Ana', 'status' => 'submetido'],
    ['id' => 2, 'inscrito_submissao_id' => 1, 'avaliador_id' => $beto->id, 'titulo_trabalho' => 'Trabalho do Beto', 'status' => 'submetido'],
    ['id' => 3, 'inscrito_submissao_id' => 1, 'avaliador_id' => null, 'titulo_trabalho' => 'Sem avaliador', 'status' => 'submetido'],
]);

$check = function ($esperado, $atual, string $mensagem): void {
    if ($esperado !== $atual) throw new RuntimeException("{$mensagem}: ".json_encode($atual));
};
$request = function (array $permissoes, int $usuarioId, array $input = []): Request {
    $request = Request::create('/', 'GET', $input + ['start' => 0, 'length' => 20, 'draw' => 1]);
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('gi_context', ['usuario' => ['id' => $usuarioId, 'nome' => 'Teste'], 'permissoes' => $permissoes]);
    app()->instance('request', $request);
    return $request;
};
$controller = new SubmissaoController();
$submissao = Submissao::findOrFail(1);
$armazem = new ArmazemService();

$resposta = $controller->inscritosDados($request(['submissoes.avaliar_meus_trabalhos'], 10), $submissao, $armazem)->getData(true);
$check(1, $resposta['recordsTotal'], 'A permissão de trabalhos próprios deve limitar a listagem');
$check('Trabalho da Ana', html_entity_decode($resposta['data'][0]['titulo_trabalho']), 'Trabalho incorreto no escopo próprio');

$resposta = $controller->inscritosDados($request(['submissoes.avaliar'], 10), $submissao, $armazem)->getData(true);
$check(3, $resposta['recordsTotal'], 'A permissão geral deve listar todos os trabalhos');

$distribuicao = Request::create('/', 'PATCH', ['avaliador_id' => $semUsuario->id]);
$distribuicao->setLaravelSession(app('session.store'));
$distribuicao->session()->put('gi_context', ['usuario' => ['id' => 10, 'nome' => 'Gestor'], 'permissoes' => ['submissoes.inscritos']]);
app()->instance('request', $distribuicao);
$controller->alterarAvaliador($distribuicao, $submissao, 3);
$check($semUsuario->id, InscricaoSubmissaoTrabalho::find(3)->avaliador_id, 'Avaliador sem usuário não foi associado pelo nome');

$tentativa = Request::create('/', 'PATCH', ['situacao' => 'aprovado']);
$tentativa->setLaravelSession(app('session.store'));
$tentativa->session()->put('gi_context', ['usuario' => ['id' => 10, 'nome' => 'Ana'], 'permissoes' => ['submissoes.avaliar_meus_trabalhos']]);
app()->instance('request', $tentativa);
try {
    $controller->avaliar($tentativa, $submissao, 2);
    throw new RuntimeException('Avaliadora conseguiu avaliar trabalho de outro avaliador.');
} catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
    $check(403, $exception->getStatusCode(), 'Status incorreto ao bloquear trabalho alheio');
}

$controller->avaliar($tentativa, $submissao, 1);
$check('avaliado', InscricaoSubmissaoTrabalho::find(1)->status, 'Trabalho próprio não foi avaliado');

$geral = Request::create('/', 'PATCH', ['situacao' => 'reprovado']);
$geral->setLaravelSession(app('session.store'));
$geral->session()->put('gi_context', ['usuario' => ['id' => 10, 'nome' => 'Ana'], 'permissoes' => ['submissoes.avaliar']]);
app()->instance('request', $geral);
$controller->avaliar($geral, $submissao, 2);
$check('avaliado', InscricaoSubmissaoTrabalho::find(2)->status, 'Permissão geral não avaliou trabalho alheio');

$listagem = Request::create('/avaliadores/dados', 'GET', ['draw' => 1, 'start' => 0, 'length' => 10]);
$listagem->setLaravelSession(app('session.store'));
$listagem->session()->put('gi_context.permissoes', ['avaliadores.listar', 'avaliadores.visualizar']);
app()->instance('request', $listagem);
$dadosAvaliadores = (new AvaliadorController())->dados($listagem, $armazem)->getData(true);
$check(3, $dadosAvaliadores['recordsTotal'], 'A DataTable de avaliadores não retornou o total esperado');
$check(3, count($dadosAvaliadores['data']), 'A DataTable de avaliadores não montou as linhas esperadas');

echo "OK: DataTable de avaliadores, vínculo, listagem própria, listagem geral e bloqueio de avaliação alheia.\n";
