<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Atividade;
use App\Models\InscricaoAtividade;
use App\Models\PixConfiguracao;
use App\Services\FormularioInscricaoService;
use App\Services\SicoobPixService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

$banco = tempnam(sys_get_temp_dir(), 'eventosgi-pix-test-');
config(['database.default' => 'pix_test', 'database.connections.pix_test' => [
    'driver' => 'sqlite', 'database' => $banco, 'prefix' => '', 'foreign_key_constraints' => false,
]]);
DB::purge('pix_test');

try {
    Schema::create('atividades', function (Blueprint $table): void {
        $table->id(); $table->string('nome'); $table->boolean('ativo')->default(true); $table->unsignedBigInteger('criado_por');
        $table->unsignedBigInteger('evento_id'); $table->longText('formulario')->nullable(); $table->string('hash_publica', 64)->nullable();
        $table->timestamps(); $table->softDeletes();
    });
    Schema::create('inscricoes_atividade', function (Blueprint $table): void {
        $table->id(); $table->unsignedBigInteger('atividade_id'); $table->unsignedBigInteger('participante_id')->nullable();
        $table->string('participante_email')->nullable(); $table->json('resposta'); $table->string('comprovante_hash', 64)->nullable();
        $table->timestamps();
    });
    (require __DIR__.'/../database/migrations/2026_09_14_000000_create_pix_configuracoes_and_cobrancas_tables.php')->up();
    (require __DIR__.'/../database/migrations/2026_09_14_010000_add_client_secret_to_pix_configuracoes_table.php')->up();
    (require __DIR__.'/../database/migrations/2026_09_14_020000_add_sandbox_token_to_pix_configuracoes_table.php')->up();

    PixConfiguracao::create([
        'ativo' => true, 'ambiente' => 'producao', 'client_id' => 'cliente-teste', 'chave_pix' => 'receber@example.com',
        'certificado_pem' => 'certificado-teste', 'chave_privada_pem' => 'chave-teste',
        'token_url' => PixConfiguracao::TOKEN_URL, 'api_url' => PixConfiguracao::API_URL,
    ]);
    $configuracaoBruta = (array) DB::table('pix_configuracoes')->first();
    if (str_contains($configuracaoBruta['client_id'], 'cliente-teste') || str_contains($configuracaoBruta['chave_pix'], 'receber@example.com')) {
        throw new RuntimeException('As credenciais PIX devem permanecer criptografadas no banco.');
    }
    $atividade = Atividade::create(['nome' => 'Curso seguro', 'ativo' => true, 'criado_por' => 1, 'evento_id' => 1, 'formulario' => ['campos' => [
        ['nome' => 'pagamento', 'label' => 'Pagamento', 'tipo' => 'pagamento_pix', 'valor_pix' => 47.9, 'expiracao_pix' => 1800],
    ]]]);
    $inscricao = InscricaoAtividade::create(['atividade_id' => $atividade->id, 'resposta' => []]);

    Http::fake([
        PixConfiguracao::TOKEN_URL => Http::response(['access_token' => 'token-teste'], 200),
        PixConfiguracao::API_URL.'/cob/*' => Http::response([
            'txid' => 'EVGITESTE1234567890123456789012', 'status' => 'ATIVA', 'pixCopiaECola' => '000201010212teste6304ABCD',
        ], 201),
    ]);
    $servico = app(SicoobPixService::class);
    $cobranca = $servico->gerarParaInscricao($inscricao->load('atividade'))[0];
    if ($cobranca->valor !== '47.90' || ! $servico->qrCode($cobranca)) throw new RuntimeException('A cobrança/QR Code não foi gerada corretamente.');
    $requisicaoCobranca = collect(Http::recorded())->map(fn ($registro) => $registro[0])
        ->first(fn (Request $request) => str_contains($request->url(), '/cob/'));
    if (! $requisicaoCobranca
        || data_get($requisicaoCobranca->data(), 'valor.original') !== '47.90'
        || data_get($requisicaoCobranca->data(), 'chave') !== 'receber@example.com'
        || $requisicaoCobranca->header('client_id')[0] !== 'cliente-teste') {
        throw new RuntimeException('A requisição não usou o valor e as credenciais configurados no servidor.');
    }

    $atividadeSemPix = new Atividade(['nome' => 'Gratuita', 'formulario' => ['campos' => [['nome' => 'nome_social', 'tipo' => 'text']]]]);
    $atividadeSemPix->id = 999; $atividadeSemPix->exists = true;
    $inscricaoSemPix = new InscricaoAtividade; $inscricaoSemPix->id = 999; $inscricaoSemPix->exists = true;
    $inscricaoSemPix->setRelation('atividade', $atividadeSemPix);
    if ($servico->gerarParaInscricao($inscricaoSemPix) !== []) throw new RuntimeException('Formulário sem PIX não deve acessar o banco.');

    $regras = app(FormularioInscricaoService::class)->regras($atividade);
    if (array_key_exists('pagamento', $regras)) throw new RuntimeException('O valor PIX não pode ser aceito do navegador.');

    $configuracao = PixConfiguracao::atual();
    $configuracao->update([
        'ambiente' => 'sandbox', 'sandbox_token' => 'bearer-sandbox',
        'api_url' => PixConfiguracao::SANDBOX_API_URL, 'token_url' => null,
    ]);
    $atividadeSandbox = Atividade::create(['nome' => 'Curso sandbox', 'ativo' => true, 'criado_por' => 1, 'evento_id' => 1, 'formulario' => ['campos' => [
        ['nome' => 'pix_sandbox', 'tipo' => 'pagamento_pix', 'valor_pix' => 1.25],
    ]]]);
    $inscricaoSandbox = InscricaoAtividade::create(['atividade_id' => $atividadeSandbox->id, 'resposta' => []]);
    Http::fake([
        PixConfiguracao::SANDBOX_API_URL.'/cob' => Http::response([
            'txid' => 'SANDBOX12345678901234567890123', 'status' => 'ATIVA', 'brcode' => '000201010212sandbox6304ABCD',
        ], 200),
    ]);
    $sandbox = $servico->gerarParaInscricao($inscricaoSandbox->load('atividade'))[0];
    $requisicaoSandbox = collect(Http::recorded())->map(fn ($registro) => $registro[0])->first();
    if ($sandbox->pix_copia_cola !== '000201010212sandbox6304ABCD'
        || $requisicaoSandbox?->method() !== 'POST'
        || $requisicaoSandbox?->header('Authorization')[0] !== 'Bearer bearer-sandbox') {
        throw new RuntimeException('O Sandbox deve usar POST /cob e o token Bearer estático, sem OAuth/mTLS.');
    }
    Http::fake([PixConfiguracao::SANDBOX_API_URL.'/pix*' => Http::response(['pix' => []], 200)]);
    $servico->testarConexao();
    $testeConexao = collect(Http::recorded())->map(fn ($registro) => $registro[0])
        ->first(fn (Request $request) => str_contains($request->url(), '/pix?'));
    if ($testeConexao?->method() !== 'GET' || ! str_contains($testeConexao->url(), 'inicio=')) {
        throw new RuntimeException('O teste de conexão deve consultar o Sandbox sem criar transações.');
    }
    echo "OK: produção mTLS, Sandbox por token estático e valores PIX protegidos no servidor.\n";
} finally {
    DB::disconnect('pix_test');
    @unlink($banco);
}
