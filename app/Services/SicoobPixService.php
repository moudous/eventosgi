<?php

namespace App\Services;

use App\Exceptions\PagamentoPixConfirmadoException;
use App\Models\InscricaoAtividade;
use App\Models\PixCobranca;
use App\Models\PixConfiguracao;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use RuntimeException;

class SicoobPixService
{
    private const OAUTH_SCOPES = 'openid cob.read cob.write pix.read webhook.read webhook.write';

    public function testarConexao(): void
    {
        $configuracao = $this->configuracaoAtiva();
        $resposta = $this->enviar($configuracao, 'get', rtrim($configuracao->api_url, '/').'/pix', [
            'inicio' => now()->subDay()->toIso8601String(),
            'fim' => now()->toIso8601String(),
        ]);
        if (! $resposta->successful()) $this->falha($resposta->status(), $resposta->json(), 'testar a conexão com o Sicoob');
    }

    /** @return array<string, mixed> */
    public function configurarWebhook(?string $url = null): array
    {
        $configuracao = $this->configuracaoAtiva();
        $url = $this->urlWebhook($url);
        $resposta = $this->enviar(
            $configuracao,
            'put',
            rtrim($configuracao->api_url, '/').'/webhook/'.rawurlencode($configuracao->chave_pix),
            ['webhookUrl' => $url],
        );
        if (! $resposta->successful()) $this->falha($resposta->status(), $resposta->json(), 'cadastrar o webhook PIX');

        return (array) $resposta->json();
    }

    /** @return array<string, mixed> */
    public function consultarWebhook(): array
    {
        $configuracao = $this->configuracaoAtiva();
        $resposta = $this->enviar(
            $configuracao,
            'get',
            rtrim($configuracao->api_url, '/').'/webhook/'.rawurlencode($configuracao->chave_pix),
        );
        if (! $resposta->successful()) $this->falha($resposta->status(), $resposta->json(), 'consultar o webhook PIX');

        return (array) $resposta->json();
    }

    public function removerWebhook(): void
    {
        $configuracao = $this->configuracaoAtiva();
        $resposta = $this->enviar(
            $configuracao,
            'delete',
            rtrim($configuracao->api_url, '/').'/webhook/'.rawurlencode($configuracao->chave_pix),
        );
        if (! $resposta->successful()) $this->falha($resposta->status(), $resposta->json(), 'remover o webhook PIX');
    }

    /** Cria a cobrança única com a soma dos valores associados às respostas. */
    public function gerarParaInscricao(InscricaoAtividade $inscricao): array
    {
        $pagamento = $inscricao->atividade->configuracaoPagamentoPix();
        $valorTotal = $inscricao->atividade->valorPagamentoPix($inscricao->resposta ?? []);
        if (! $pagamento || $valorTotal <= 0) return [];

        $configuracao = $this->configuracaoAtiva();
        if (! PixConfiguracao::chavePixValida($configuracao->chave_pix)) {
            throw new RuntimeException('A chave PIX configurada não tem um formato válido. Em Configuração, informe um CPF ou CNPJ sem pontuação, telefone no padrão +5511999999999, e-mail ou chave aleatória UUID.');
        }
        $ambiente = $this->ambienteEfetivo($configuracao);
        $campoCobranca = array_key_exists('pagamento_inscricao', $inscricao->atividade->formulario ?? [])
            ? 'pagamento_inscricao'
            : (string) (collect($inscricao->atividade->formulario['campos'] ?? [])->firstWhere('tipo', 'pagamento_pix')['nome'] ?? 'pagamento_inscricao');
        $existente = PixCobranca::query()
            ->where('inscricao_atividade_id', $inscricao->id)
            ->where('campo', $campoCobranca)->first();
        $deveRegenerarEmProducao = $existente
            && $existente->ambiente === 'sandbox'
            && $ambiente === 'producao';
        $deveRegenerarRemovida = $existente?->removidaSemPagamento() ?? false;
        if ($existente && ! $deveRegenerarEmProducao && ! $deveRegenerarRemovida) return [$existente];

        $valor = number_format($valorTotal, 2, '.', '');
        $txid = 'EVGI'.strtoupper(Str::random(28));
        $payload = [
            'calendario' => ['expiracao' => $pagamento['expiracao']],
            'valor' => ['original' => $valor],
            'chave' => $configuracao->chave_pix,
            'solicitacaoPagador' => Str::limit($pagamento['descricao'], 140, ''),
            'infoAdicionais' => [
                ['nome' => 'Inscrição', 'valor' => (string) $inscricao->id],
                ['nome' => 'Atividade', 'valor' => Str::limit($inscricao->atividade->nome, 50, '')],
            ],
        ];

        $participante = $inscricao->participante;
        $cpf = preg_replace('/\D+/', '', (string) $participante?->cpf);
        if ($participante && strlen($cpf) === 11) {
            $payload['devedor'] = ['cpf' => $cpf, 'nome' => Str::limit($participante->nome, 200, '')];
        }

        $sandbox = $ambiente === 'sandbox';
        $resposta = $this->enviar(
            $configuracao,
            $sandbox ? 'post' : 'put',
            rtrim($configuracao->api_url, '/').'/cob'.($sandbox ? '' : '/'.$txid),
            $payload,
        );
        if (! $resposta->successful()) $this->falha($resposta->status(), $resposta->json(), 'criar a cobrança');
        $dados = (array) $resposta->json();

        $atributos = [
            'inscricao_atividade_id' => $inscricao->id,
            'campo' => $campoCobranca,
            'ambiente' => $ambiente,
            'txid' => $dados['txid'] ?? $txid,
            'valor' => $valor,
            'status' => $dados['status'] ?? 'ATIVA',
            'pix_copia_cola' => $dados['pixCopiaECola'] ?? $dados['brcode'] ?? null,
            'location' => $dados['location'] ?? data_get($dados, 'loc.location'),
            'resposta_api' => $dados,
        ];
        if (! $existente) return [PixCobranca::create($atributos)];

        $existente->update($atributos + [
            'pago_em' => null,
            'pagador_nome' => null,
            'pagador_documento' => null,
            'end_to_end_id' => null,
        ]);

        return [$existente->refresh()];
    }

    public function consultar(PixCobranca $cobranca): PixCobranca
    {
        $configuracao = $this->configuracaoAtiva();
        $resposta = $this->enviar($configuracao, 'get', rtrim($configuracao->api_url, '/').'/cob/'.$cobranca->txid);
        if (! $resposta->successful()) $this->falha($resposta->status(), $resposta->json(), 'consultar a cobrança');
        $dados = (array) $resposta->json();
        $pixRecebido = (array) data_get($dados, 'pix.0', []);
        $pago = ($dados['status'] ?? '') === 'CONCLUIDA' || $pixRecebido !== [];
        $documento = data_get($pixRecebido, 'pagador.cpf') ?: data_get($pixRecebido, 'pagador.cnpj');
        $horario = data_get($pixRecebido, 'horario');
        try {
            $pagoEm = $horario
                ? Carbon::parse($horario)->setTimezone(config('app.timezone'))
                : ($cobranca->pago_em ?? now());
        } catch (\Throwable) {
            $pagoEm = $cobranca->pago_em ?? now();
        }
        $cobranca->update([
            'status' => $pago ? 'CONCLUIDA' : ($dados['status'] ?? $cobranca->status),
            'pix_copia_cola' => $dados['pixCopiaECola'] ?? $dados['brcode'] ?? $cobranca->pix_copia_cola,
            'location' => $dados['location'] ?? data_get($dados, 'loc.location') ?? $cobranca->location,
            'resposta_api' => $dados,
            'pagador_nome' => data_get($pixRecebido, 'pagador.nome') ?: $cobranca->pagador_nome,
            'pagador_documento' => $documento ? preg_replace('/\D+/', '', (string) $documento) : $cobranca->pagador_documento,
            'end_to_end_id' => data_get($pixRecebido, 'endToEndId') ?: $cobranca->end_to_end_id,
            'pago_em' => $pago ? $pagoEm : null,
        ]);

        if ($pago) $this->confirmarInscricao($cobranca);

        return $cobranca->refresh();
    }

    private function confirmarInscricao(PixCobranca $cobranca): void
    {
        DB::transaction(function () use ($cobranca): void {
            $inscricao = InscricaoAtividade::query()->withoutGlobalScope('ativas')
                ->whereKey($cobranca->inscricao_atividade_id)->lockForUpdate()->first();
            if (! $inscricao || $inscricao->ativa || $inscricao->cancelada_em) return;

            $inscricao->forceFill([
                'ativa' => true,
                'codigo_qr' => ! empty($inscricao->atividade?->formulario['registrar_presenca_qrcode'])
                    ? app(PresencaQrService::class)->novoCodigo()
                    : null,
            ])->save();
        });

        $inscricao = InscricaoAtividade::query()->find($cobranca->inscricao_atividade_id);
        if ($inscricao) app(DistribuicaoVagasService::class)->recalcular($inscricao->atividade);
    }

    public function cancelar(PixCobranca $cobranca): PixCobranca
    {
        if ($cobranca->pagamentoConfirmado()) throw new PagamentoPixConfirmadoException;
        if ($cobranca->removidaSemPagamento()) return $cobranca;

        $configuracao = $this->configuracaoAtiva();
        $ambiente = $this->ambienteEfetivo($configuracao);
        if ($cobranca->ambiente && $cobranca->ambiente !== $ambiente) {
            // Uma cobrança do simulador não movimenta dinheiro e não pode ser gerida
            // pelo endpoint de produção depois da troca de ambiente.
            if ($cobranca->ambiente === 'sandbox') {
                $cobranca->update(['status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR']);
                return $cobranca->refresh();
            }
            throw new RuntimeException('A cobrança PIX pertence a outro ambiente e não pôde ser cancelada com segurança.');
        }

        $atualizada = $this->consultar($cobranca);
        if ($atualizada->pagamentoConfirmado()) throw new PagamentoPixConfirmadoException;
        if ($atualizada->removidaSemPagamento()) return $atualizada;

        $resposta = $this->enviar(
            $configuracao,
            'patch',
            rtrim($configuracao->api_url, '/').'/cob/'.$cobranca->txid,
            ['status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR'],
        );
        if (! $resposta->successful()) {
            // Um pagamento pode concluir entre o GET e o PATCH. Reconsultar transforma
            // essa corrida em bloqueio de cancelamento, sem perder o vínculo financeiro.
            $aposFalha = $this->consultar($cobranca);
            if ($aposFalha->pagamentoConfirmado()) throw new PagamentoPixConfirmadoException;
            $this->falha($resposta->status(), $resposta->json(), 'cancelar a cobrança');
        }

        $dados = (array) $resposta->json();
        $cobranca->update([
            'status' => $dados['status'] ?? 'REMOVIDA_PELO_USUARIO_RECEBEDOR',
            'resposta_api' => $dados
                ? array_replace_recursive((array) $cobranca->resposta_api, $dados)
                : $cobranca->resposta_api,
        ]);

        return $cobranca->refresh();
    }

    /** @return array{imagem: string, codigo: string}|null */
    public function qrCode(PixCobranca $cobranca): ?array
    {
        if (! $cobranca->pix_copia_cola) return null;
        $resultado = Builder::create()->writer(new PngWriter)->data($cobranca->pix_copia_cola)
            ->encoding(new Encoding('ISO-8859-1'))->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->size(280)->margin(12)->roundBlockSizeMode(RoundBlockSizeMode::Margin)->build();

        return ['imagem' => $resultado->getDataUri(), 'codigo' => $cobranca->pix_copia_cola];
    }

    private function enviar(PixConfiguracao $configuracao, string $metodo, string $url, array $dados = []): Response
    {
        if ($configuracao->ambiente === 'sandbox') {
            if (! $configuracao->sandbox_token) throw new RuntimeException('Informe o Access Token do Sandbox na Configuração PIX.');
            $requisicao = Http::withToken($configuracao->sandbox_token)
                ->withHeaders(['client_id' => $configuracao->client_id])->acceptJson()->timeout(20);

            return $metodo === 'get' ? $requisicao->get($url, $dados) : $requisicao->{$metodo}($url, $dados);
        }

        return $this->comCertificado($configuracao, function (array $opcoes) use ($configuracao, $metodo, $url, $dados): Response {
            $chaveCache = 'sicoob-pix-token-'.$configuracao->id.'-'.$configuracao->updated_at?->timestamp.'-'.sha1(self::OAUTH_SCOPES);
            $token = Cache::remember($chaveCache, now()->addMinutes(4), function () use ($configuracao, $opcoes): string {
                $formulario = [
                    'grant_type' => 'client_credentials',
                    'client_id' => $configuracao->client_id,
                    'scope' => self::OAUTH_SCOPES,
                ];
                if ($configuracao->client_secret) $formulario['client_secret'] = $configuracao->client_secret;
                $resposta = Http::withOptions($opcoes)->asForm()->acceptJson()->timeout(20)->post($configuracao->token_url, $formulario);
                if (! $resposta->successful() || ! $resposta->json('access_token')) {
                    $this->falha($resposta->status(), $resposta->json(), 'autenticar no Sicoob');
                }
                return (string) $resposta->json('access_token');
            });
            $requisicao = Http::withOptions($opcoes)->withToken($token)->withHeaders(['client_id' => $configuracao->client_id])->acceptJson()->timeout(20);

            return $metodo === 'get'
                ? $requisicao->get($url, $dados)
                : $requisicao->{$metodo}($url, $dados);
        });
    }

    private function comCertificado(PixConfiguracao $configuracao, callable $acao): mixed
    {
        $certificado = tempnam(sys_get_temp_dir(), 'evgi-pix-cert-');
        $chave = tempnam(sys_get_temp_dir(), 'evgi-pix-key-');
        if ($certificado === false || $chave === false) throw new RuntimeException('Não foi possível preparar o certificado PIX.');
        try {
            file_put_contents($certificado, $configuracao->certificado_pem);
            file_put_contents($chave, $configuracao->chave_privada_pem);
            chmod($certificado, 0600);
            chmod($chave, 0600);
            $opcoes = ['cert' => $certificado, 'ssl_key' => $configuracao->senha_chave ? [$chave, $configuracao->senha_chave] : $chave];
            return $acao($opcoes);
        } finally {
            @unlink($certificado);
            @unlink($chave);
        }
    }

    private function configuracaoAtiva(): PixConfiguracao
    {
        $configuracao = PixConfiguracao::atual();
        if (! $configuracao?->ativo) throw new RuntimeException('A API PIX do Sicoob ainda não está ativa em Configuração.');
        if ($configuracao->ambiente === 'sandbox' && (! $configuracao->client_id || ! $configuracao->sandbox_token)) {
            throw new RuntimeException('Preencha o Client ID e o Access Token do Sandbox na Configuração PIX.');
        }
        return $configuracao;
    }

    private function ambienteEfetivo(PixConfiguracao $configuracao): string
    {
        $host = mb_strtolower((string) parse_url($configuracao->api_url, PHP_URL_HOST));

        return $configuracao->ambiente === 'sandbox' || str_starts_with($host, 'sandbox.')
            ? 'sandbox'
            : 'producao';
    }

    private function urlWebhook(?string $url): string
    {
        $url = rtrim(trim((string) ($url ?: config('pix.webhook_url'))), '/');
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        $caminho = (string) parse_url($url, PHP_URL_PATH);
        $hostEhIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $ipEhPublico = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;

        if (! filter_var($url, FILTER_VALIDATE_URL)
            || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || $host === ''
            || $host === 'localhost'
            || ($hostEhIp && ! $ipEhPublico)
            || str_ends_with($caminho, '/pix')) {
            throw new RuntimeException('Configure PIX_WEBHOOK_URL com uma URL-base HTTPS pública, sem o sufixo /pix.');
        }

        return $url;
    }

    private function falha(int $status, mixed $dados, string $acao): never
    {
        $mensagem = is_array($dados) ? ($dados['detail'] ?? $dados['message'] ?? $dados['error_description'] ?? null) : null;
        $violacoes = collect(is_array($dados) ? ($dados['violacoes'] ?? $dados['violations'] ?? []) : [])
            ->map(function (mixed $violacao): ?string {
                if (is_string($violacao)) return trim($violacao) ?: null;
                if (! is_array($violacao)) return null;

                $propriedade = $violacao['propriedade'] ?? $violacao['property'] ?? $violacao['campo'] ?? null;
                $razao = $violacao['razao'] ?? $violacao['reason'] ?? $violacao['mensagem'] ?? $violacao['message'] ?? null;
                if (! is_string($razao) || trim($razao) === '') return null;

                return is_string($propriedade) && trim($propriedade) !== ''
                    ? trim($propriedade).': '.trim($razao)
                    : trim($razao);
            })
            ->filter()->unique()->take(3)->implode(' ');

        if ($violacoes !== '') $mensagem = trim((string) $mensagem.' '.$violacoes);
        throw new RuntimeException('Não foi possível '.$acao.' (HTTP '.$status.').'.($mensagem ? ' '.$mensagem : ''));
    }
}
