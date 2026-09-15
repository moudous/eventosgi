<?php

// php tests/exclusao-inscricao-atividade.php — valida permissão, confirmação e bloqueio PIX.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\AtividadeController;
use App\Models\{Atividade, InscricaoAtividade, PixCobranca};
use App\Services\{DistribuicaoVagasService, GiPermissionService, HistoricoService};
use Illuminate\Http\Request;
use Illuminate\Session\{ArraySessionHandler, Store};
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => ':memory:',
    'session.driver' => 'array',
    'filesystems.disks.local.root' => sys_get_temp_dir().'/eventosgi-exclusao-inscricao-local',
    'filesystems.disks.public.root' => sys_get_temp_dir().'/eventosgi-exclusao-inscricao-public',
]);

Schema::create('atividades', function ($t): void {
    $t->id(); $t->string('nome'); $t->json('formulario')->nullable(); $t->string('hash_publica', 64);
    $t->timestamps(); $t->softDeletes();
});
Schema::create('inscricoes_atividade', function ($t): void {
    $t->id(); $t->unsignedBigInteger('atividade_id'); $t->unsignedBigInteger('sessao_atividade_id')->nullable();
    $t->unsignedBigInteger('participante_id')->nullable(); $t->string('participante_email')->nullable();
    $t->boolean('lista_reserva')->default(false); $t->json('resposta'); $t->string('comprovante_hash', 64)->nullable();
    $t->boolean('presente')->default(false); $t->timestamps();
});
Schema::create('pix_cobrancas', function ($t): void {
    $t->id(); $t->unsignedBigInteger('inscricao_atividade_id')->index(); $t->string('campo');
    $t->string('txid')->unique(); $t->decimal('valor', 12, 2); $t->string('status'); $t->timestamps();
});
Schema::create('historico_atividades', function ($t): void {
    $t->id(); $t->unsignedBigInteger('atividade_id'); $t->string('historico');
    $t->unsignedBigInteger('usuario')->nullable(); $t->json('dados')->nullable(); $t->timestamp('data_hora');
});

function conferirExclusao(bool $condicao, string $mensagem): void
{
    if (! $condicao) throw new RuntimeException($mensagem);
}

function requisicaoExclusao(array $dados, array $permissoes): Request
{
    global $app;
    $request = Request::create('/', 'DELETE', $dados, [], [], ['HTTP_ACCEPT' => 'application/json']);
    $request->setLaravelSession(new Store('teste', new ArraySessionHandler(120)));
    $request->session()->put('gi_context.permissoes', $permissoes);
    $app->instance('request', $request);

    return $request;
}

$historico = new HistoricoService;
$distribuicao = new class extends DistribuicaoVagasService {
    public int $chamadas = 0;
    public function recalcular(Atividade $atividade, ?array $config = null, bool $salvar = true): array
    {
        $this->chamadas++;
        return [];
    }
};
$controller = new AtividadeController;
$permissoes = new GiPermissionService;
$atividade = Atividade::create(['nome' => 'Atividade teste', 'formulario' => ['campos' => []]]);

$semConfirmar = $atividade->inscricoes()->create(['participante_email' => 'sem-confirmar@example.test', 'resposta' => []]);
try {
    $controller->excluirInscricao(requisicaoExclusao([], ['atividades.inscricoes.excluir']), $atividade, $semConfirmar, $permissoes, $historico, $distribuicao);
    throw new RuntimeException('Exclusão sem confirmação foi aceita.');
} catch (ValidationException) {}
conferirExclusao($semConfirmar->fresh() !== null, 'Confirmação ausente não pode excluir a inscrição.');

$semPermissao = $atividade->inscricoes()->create(['participante_email' => 'sem-permissao@example.test', 'resposta' => []]);
try {
    $controller->excluirInscricao(requisicaoExclusao(['confirmacao' => true], ['atividades.inscritos']), $atividade, $semPermissao, $permissoes, $historico, $distribuicao);
    throw new RuntimeException('Exclusão sem permissão foi aceita.');
} catch (HttpException $erro) {
    conferirExclusao($erro->getStatusCode() === 403, 'Exclusão sem permissão deve retornar HTTP 403.');
}
conferirExclusao($semPermissao->fresh() !== null, 'Usuário sem permissão excluiu a inscrição.');

$comPix = $atividade->inscricoes()->create(['participante_email' => 'pix@example.test', 'resposta' => []]);
PixCobranca::create(['inscricao_atividade_id' => $comPix->id, 'campo' => 'pagamento', 'txid' => 'PIXTESTE123', 'valor' => 10, 'status' => 'CRIADA']);
try {
    $controller->excluirInscricao(requisicaoExclusao(['confirmacao' => true], ['atividades.inscricoes.excluir']), $atividade, $comPix, $permissoes, $historico, $distribuicao);
    throw new RuntimeException('Inscrição com PIX foi excluída.');
} catch (HttpException $erro) {
    conferirExclusao($erro->getStatusCode() === 409, 'Inscrição com PIX deve retornar HTTP 409.');
}
conferirExclusao($comPix->fresh() !== null && PixCobranca::where('inscricao_atividade_id', $comPix->id)->exists(), 'Bloqueio deve preservar inscrição e PIX.');

Storage::fake('local');
Storage::fake('public');
$arquivo = 'inscricoes/teste/anexo.pdf';
Storage::disk('local')->put($arquivo, 'teste');
Storage::disk('public')->put($arquivo, 'legado');
$excluivel = $atividade->inscricoes()->create([
    'participante_email' => 'excluir@example.test',
    'resposta' => ['anexo' => [$arquivo]],
]);
$resposta = $controller->excluirInscricao(
    requisicaoExclusao(['confirmacao' => true], ['atividades.inscricoes.excluir']),
    $atividade,
    $excluivel,
    $permissoes,
    $historico,
    $distribuicao,
);
conferirExclusao($resposta->getStatusCode() === 200 && $excluivel->fresh() === null, 'Inscrição sem PIX não foi excluída.');
conferirExclusao(! Storage::disk('local')->exists($arquivo) && ! Storage::disk('public')->exists($arquivo), 'Anexos da inscrição excluída devem ser removidos.');
conferirExclusao(DB::table('historico_atividades')->where('historico', 'Inscrição excluída')->exists(), 'Exclusão deve constar no histórico da atividade.');
conferirExclusao($distribuicao->chamadas === 1, 'Distribuição de vagas deve ser recalculada após excluir.');

$rota = app('router')->getRoutes()->getByName('atividades.inscricoes.destroy');
conferirExclusao($rota !== null && in_array('gi.permission:atividades.inscricoes.excluir', $rota->gatherMiddleware(), true), 'Rota deve exigir a nova permissão.');
$view = file_get_contents(resource_path('views/atividades/inscricoes.blade.php'));
conferirExclusao(str_contains($view, 'confirmarExclusaoInscricao') && str_contains($view, 'possui_pix'), 'Tela deve possuir confirmação e bloqueio visual para PIX.');

echo "OK: permissão, confirmação, bloqueio PIX, histórico, anexos e recálculo de vagas.\n";
