<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Atividade;
use App\Models\InscricaoAtividade;
use App\Models\PixConfiguracao;
use App\Models\PixCobranca;
use App\Http\Controllers\Api\SicoobPixWebhookController;
use App\Services\FormularioInscricaoService;
use App\Services\SicoobPixService;
use App\Services\CancelamentoInscricaoService;
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
    foreach (['12345678901', '12345678901234', '+5511999999999', 'receber@example.com', '123e4567-e89b-12d3-a456-426614174000'] as $chaveValida) {
        if (! PixConfiguracao::chavePixValida($chaveValida)) throw new RuntimeException('Uma chave PIX válida foi rejeitada: '.$chaveValida);
    }
    if (PixConfiguracao::chavePixValida('000201 código Pix copia e cola')) {
        throw new RuntimeException('Um código Pix copia e cola foi aceito como chave DICT.');
    }

    Schema::create('atividades', function (Blueprint $table): void {
        $table->id(); $table->string('nome'); $table->boolean('ativo')->default(true); $table->unsignedBigInteger('criado_por');
        $table->unsignedBigInteger('evento_id'); $table->longText('formulario')->nullable(); $table->string('hash_publica', 64)->nullable();
        $table->timestamps(); $table->softDeletes();
    });
    Schema::create('inscricoes_atividade', function (Blueprint $table): void {
        $table->id(); $table->unsignedBigInteger('atividade_id'); $table->unsignedBigInteger('participante_id')->nullable();
        $table->string('participante_email')->nullable(); $table->boolean('ativa')->nullable()->default(true);
        $table->timestamp('cancelada_em')->nullable(); $table->string('cancelamento_motivo')->nullable();
        $table->json('resposta'); $table->string('comprovante_hash', 64)->nullable();
        $table->timestamps();
    });
    (require __DIR__.'/../database/migrations/2026_09_14_000000_create_pix_configuracoes_and_cobrancas_tables.php')->up();
    (require __DIR__.'/../database/migrations/2026_09_14_010000_add_client_secret_to_pix_configuracoes_table.php')->up();
    (require __DIR__.'/../database/migrations/2026_09_14_020000_add_sandbox_token_to_pix_configuracoes_table.php')->up();
    (require __DIR__.'/../database/migrations/2026_09_15_000000_add_pagador_fields_to_pix_cobrancas_table.php')->up();
    (require __DIR__.'/../database/migrations/2026_09_17_020000_add_ambiente_to_pix_cobrancas.php')->up();

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

    $confirmarConsultaComHorarioUtc = false;
    Http::fake([
        PixConfiguracao::TOKEN_URL => Http::response(['access_token' => 'token-teste'], 200),
        PixConfiguracao::API_URL.'/cob/*' => function (Request $request) use (&$confirmarConsultaComHorarioUtc) {
            $txid = basename((string) parse_url($request->url(), PHP_URL_PATH));
            if ($request->method() === 'GET' && $confirmarConsultaComHorarioUtc) {
                return Http::response([
                    'txid' => $txid,
                    'status' => 'CONCLUIDA',
                    'pix' => [['txid' => $txid, 'horario' => '2026-09-17T23:17:00Z']],
                ], 200);
            }
            return Http::response([
                'txid' => $txid,
                'status' => $request->method() === 'PATCH' ? 'REMOVIDA_PELO_USUARIO_RECEBEDOR' : 'ATIVA',
                'pixCopiaECola' => '000201010212teste6304ABCD',
            ], 201);
        },
    ]);
    $servico = app(SicoobPixService::class);
    $cobranca = $servico->gerarParaInscricao($inscricao->load('atividade'))[0];
    if ($cobranca->valor !== '47.90' || $cobranca->ambiente !== 'producao' || ! $servico->qrCode($cobranca)) throw new RuntimeException('A cobrança/QR Code não foi gerada corretamente.');
    $requisicaoCobranca = collect(Http::recorded())->map(fn ($registro) => $registro[0])
        ->first(fn (Request $request) => str_contains($request->url(), '/cob/'));
    if (! $requisicaoCobranca
        || data_get($requisicaoCobranca->data(), 'valor.original') !== '47.90'
        || data_get($requisicaoCobranca->data(), 'chave') !== 'receber@example.com'
        || $requisicaoCobranca->header('client_id')[0] !== 'cliente-teste') {
        throw new RuntimeException('A requisição não usou o valor e as credenciais configurados no servidor.');
    }
    $cancelada = $servico->cancelar($cobranca);
    $requisicaoCancelamento = collect(Http::recorded())->map(fn ($registro) => $registro[0])
        ->first(fn (Request $request) => $request->method() === 'PATCH' && str_contains($request->url(), '/cob/'));
    if ($cancelada->status !== 'REMOVIDA_PELO_USUARIO_RECEBEDOR'
        || data_get($requisicaoCancelamento?->data(), 'status') !== 'REMOVIDA_PELO_USUARIO_RECEBEDOR') {
        throw new RuntimeException('A cobrança pendente não foi removida no Sicoob antes do cancelamento.');
    }
    app(CancelamentoInscricaoService::class)->cancelar($inscricao, 'Teste automatizado');
    $inscricaoCancelada = InscricaoAtividade::comCanceladas()->find($inscricao->id);
    if (InscricaoAtividade::find($inscricao->id)
        || ! $inscricaoCancelada?->cancelada_em
        || ! PixCobranca::where('inscricao_atividade_id', $inscricao->id)->exists()) {
        throw new RuntimeException('O cancelamento deve liberar a vaga sem apagar inscrição ou cobrança.');
    }

    $configuracao = PixConfiguracao::atual();
    $configuracao->update(['chave_pix' => 'chave pix copiada com texto e formato inválido']);
    try {
        $servico->gerarParaInscricao(InscricaoAtividade::create(['atividade_id' => $atividade->id, 'resposta' => []])->load('atividade'));
        throw new RuntimeException('Uma chave PIX inválida foi enviada ao Sicoob.');
    } catch (RuntimeException $erro) {
        if (! str_contains($erro->getMessage(), 'chave PIX configurada')) throw $erro;
    }
    $configuracao->update(['chave_pix' => 'receber@example.com']);

    $atividadeSemPix = new Atividade(['nome' => 'Gratuita', 'formulario' => ['campos' => [['nome' => 'nome_social', 'tipo' => 'text']]]]);
    $atividadeSemPix->id = 999; $atividadeSemPix->exists = true;
    $inscricaoSemPix = new InscricaoAtividade; $inscricaoSemPix->id = 999; $inscricaoSemPix->exists = true;
    $inscricaoSemPix->setRelation('atividade', $atividadeSemPix);
    if ($servico->gerarParaInscricao($inscricaoSemPix) !== []) throw new RuntimeException('Formulário sem PIX não deve acessar o banco.');

    $regras = app(FormularioInscricaoService::class)->regras($atividade);
    if (array_key_exists('pagamento', $regras)) throw new RuntimeException('O valor PIX não pode ser aceito do navegador.');

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
        || $sandbox->ambiente !== 'sandbox'
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

    $configuracao->update(['ambiente' => 'producao', 'api_url' => PixConfiguracao::API_URL, 'token_url' => PixConfiguracao::TOKEN_URL]);
    Http::fake([
        PixConfiguracao::TOKEN_URL => Http::response(['access_token' => 'token-producao'], 200),
        PixConfiguracao::API_URL.'/cob/*' => Http::response([
            'txid' => 'EVGIREGERADO123456789012345678', 'status' => 'ATIVA', 'pixCopiaECola' => '000201010212producao6304ABCD',
        ], 201),
    ]);
    $regenerada = $servico->gerarParaInscricao($inscricaoSandbox->load('atividade'))[0];
    if ($regenerada->id !== $sandbox->id || $regenerada->ambiente !== 'producao' || ! str_starts_with($regenerada->txid, 'EVGI')) {
        throw new RuntimeException('A cobrança do Sandbox não foi substituída corretamente ao migrar para produção.');
    }
    $confirmarConsultaComHorarioUtc = true;
    $confirmadaNoFusoLocal = $servico->consultar($regenerada);
    if ($confirmadaNoFusoLocal->pago_em?->format('Y-m-d H:i:s') !== '2026-09-17 20:17:00') {
        throw new RuntimeException('O horário UTC do Sicoob não foi convertido para America/Sao_Paulo.');
    }
    $confirmarConsultaComHorarioUtc = false;
    $regenerada->update(['status' => 'ATIVA', 'pago_em' => null, 'end_to_end_id' => null]);

    config(['pix.webhook_url' => 'https://eventosgi.fco.edu.br/api/sicoob']);
    $configuracao->forceFill(['updated_at' => now()->addSeconds(2)])->save();
    Http::fake([
        PixConfiguracao::TOKEN_URL => Http::response(['access_token' => 'token-webhook'], 200),
        PixConfiguracao::API_URL.'/webhook/*' => function (Request $request) {
            if ($request->method() === 'DELETE') return Http::response(null, 204);
            return Http::response(['webhookUrl' => 'https://eventosgi.fco.edu.br/api/sicoob'], 200);
        },
    ]);
    $webhook = $servico->configurarWebhook();
    if (($webhook['webhookUrl'] ?? null) !== 'https://eventosgi.fco.edu.br/api/sicoob') {
        throw new RuntimeException('O webhook não foi cadastrado com a URL-base configurada.');
    }
    $servico->consultarWebhook();
    $servico->removerWebhook();
    $requisicaoTokenWebhook = collect(Http::recorded())->map(fn ($registro) => $registro[0])
        ->last(fn (Request $request) => $request->url() === PixConfiguracao::TOKEN_URL);
    if (! str_contains((string) data_get($requisicaoTokenWebhook?->data(), 'scope'), 'webhook.write')) {
        throw new RuntimeException('O token OAuth não solicitou os escopos de webhook.');
    }

    $requisicaoWebhook = \Illuminate\Http\Request::create(
        '/api/sicoob/pix',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['pix' => [['txid' => $regenerada->txid]]], JSON_THROW_ON_ERROR),
    );
    $consultorWebhook = new class extends SicoobPixService {
        public function consultar(PixCobranca $cobranca): PixCobranca
        {
            $cobranca->update([
                'status' => 'CONCLUIDA',
                'pago_em' => now(),
                'end_to_end_id' => 'E1234567890123456789012345678901',
            ]);

            return $cobranca->refresh();
        }
    };
    $respostaWebhook = app(SicoobPixWebhookController::class)->receber($requisicaoWebhook, $consultorWebhook);
    if ($respostaWebhook->getStatusCode() !== 200 || ! $regenerada->fresh()->pagamentoConfirmado()) {
        throw new RuntimeException('O webhook não confirmou a cobrança após consultar o Sicoob.');
    }

    echo "OK: produção mTLS, Sandbox, webhook público, confirmação autenticada e valores protegidos no servidor.\n";
} finally {
    DB::disconnect('pix_test');
    @unlink($banco);
}
