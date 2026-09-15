<?php

namespace App\Services;

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
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use RuntimeException;

class SicoobPixService
{
    public function testarConexao(): void
    {
        $configuracao = $this->configuracaoAtiva();
        $resposta = $this->enviar($configuracao, 'get', rtrim($configuracao->api_url, '/').'/pix', [
            'inicio' => now()->subDay()->toIso8601String(),
            'fim' => now()->toIso8601String(),
        ]);
        if (! $resposta->successful()) $this->falha($resposta->status(), $resposta->json(), 'testar a conexão com o Sicoob');
    }

    /** Cria as cobranças dos campos PIX da inscrição que ainda não foram geradas. */
    public function gerarParaInscricao(InscricaoAtividade $inscricao): array
    {
        $camposPix = array_values(array_filter(
            $inscricao->atividade->formulario['campos'] ?? [],
            fn (array $campo) => ($campo['tipo'] ?? '') === 'pagamento_pix' && ! empty($campo['nome']),
        ));
        if ($camposPix === []) return [];

        $configuracao = $this->configuracaoAtiva();
        $criadas = [];

        foreach ($camposPix as $campo) {
            $existente = PixCobranca::query()
                ->where('inscricao_atividade_id', $inscricao->id)
                ->where('campo', $campo['nome'])->first();
            if ($existente) {
                $criadas[] = $existente;
                continue;
            }

            $valor = number_format((float) ($campo['valor_pix'] ?? 0), 2, '.', '');
            if ((float) $valor <= 0) throw new RuntimeException('O valor do campo PIX não foi configurado.');

            $txid = 'EVGI'.strtoupper(Str::random(28));
            $payload = [
                'calendario' => ['expiracao' => min(86400, max(300, (int) ($campo['expiracao_pix'] ?? 3600)))],
                'valor' => ['original' => $valor],
                'chave' => $configuracao->chave_pix,
                'solicitacaoPagador' => Str::limit((string) ($campo['descricao_pix'] ?? $inscricao->atividade->nome), 140, ''),
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

            $sandbox = $configuracao->ambiente === 'sandbox';
            $resposta = $this->enviar(
                $configuracao,
                $sandbox ? 'post' : 'put',
                rtrim($configuracao->api_url, '/').'/cob'.($sandbox ? '' : '/'.$txid),
                $payload,
            );
            if (! $resposta->successful()) $this->falha($resposta->status(), $resposta->json(), 'criar a cobrança');
            $dados = (array) $resposta->json();

            $criadas[] = PixCobranca::create([
                'inscricao_atividade_id' => $inscricao->id,
                'campo' => $campo['nome'],
                'txid' => $dados['txid'] ?? $txid,
                'valor' => $valor,
                'status' => $dados['status'] ?? 'ATIVA',
                'pix_copia_cola' => $dados['pixCopiaECola'] ?? $dados['brcode'] ?? null,
                'location' => $dados['location'] ?? data_get($dados, 'loc.location'),
                'resposta_api' => $dados,
            ]);
        }

        return $criadas;
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
            $pagoEm = $horario ? Carbon::parse($horario) : ($cobranca->pago_em ?? now());
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
            $chaveCache = 'sicoob-pix-token-'.$configuracao->id.'-'.$configuracao->updated_at?->timestamp;
            $token = Cache::remember($chaveCache, now()->addMinutes(4), function () use ($configuracao, $opcoes): string {
                $formulario = [
                    'grant_type' => 'client_credentials',
                    'client_id' => $configuracao->client_id,
                    'scope' => 'openid cob.read cob.write pix.read',
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

    private function falha(int $status, mixed $dados, string $acao): never
    {
        $mensagem = is_array($dados) ? ($dados['detail'] ?? $dados['message'] ?? $dados['error_description'] ?? null) : null;
        throw new RuntimeException('Não foi possível '.$acao.' (HTTP '.$status.').'.($mensagem ? ' '.$mensagem : ''));
    }
}
