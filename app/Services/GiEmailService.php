<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Disparo de e-mail pela API do GI.
 *
 * Existem tres caminhos, tentados nesta ordem:
 *
 * 1. Credenciais da aplicacao (POST /api/integracoes/v1/email-disparos/aplicacao), com
 *    client_id e client_secret. A aplicacao se identifica sozinha, entao o disparo nao
 *    depende de haver alguem logado no GI. E o caminho de todo e-mail que sai daqui.
 *
 * 2. Sessao da aplicacao externa (POST /api/integracoes/v1/email-disparos), autenticada
 *    com o access_token que o GI devolve na troca do codigo em /auth/gi. So existe
 *    enquanto ha um usuario logado pelo GI, e vale apenas sem credenciais configuradas.
 *
 * 3. Token da API GI (POST /api/v1/email-disparos), usado apenas se GI_API_TOKEN estiver
 *    definido e nao houver nem credenciais nem sessao.
 *
 * Em todos os casos a aplicacao precisa declarar email-disparos.enviar no GI.
 */
class GiEmailService
{
    /**
     * @param  array<string, mixed>  $extras  Campos opcionais da API (cc, cco, prioridade, agendado_para, anexos).
     * @return int|null Id do disparo criado no GI, quando informado na resposta.
     */
    public function enviar(string $email, ?string $nome, string $assunto, string $conteudoHtml, ?string $idExterno = null, array $extras = []): ?int
    {
        $remetente = array_filter([
            'nome' => trim((string) config('gi.email_remetente_nome')) ?: null,
            'email' => trim((string) config('gi.email_remetente_email')) ?: null,
        ]);

        $payload = array_filter([
            'assunto' => $assunto,
            'destinatario' => array_filter(['email' => $email, 'nome' => $nome ?: null]),
            'remetente' => $remetente ?: null,
            'conteudo_html' => $conteudoHtml,
            'id_externo' => $idExterno,
            ...$extras,
        ], fn ($valor) => $valor !== null && $valor !== []);

        // Credenciais primeiro: quem responde pelo disparo e a aplicacao, nao quem estiver
        // logado. O access_token da sessao aparece por acaso -- o organizador abre a previa
        // do formulario ainda logado no GI -- e falha de dois jeitos: expira em 2 horas e so
        // carrega email-disparos.enviar se o perfil dele tiver essa permissao. Preferi-lo
        // fazia a previa recusar o envio enquanto o formulario publico, anonimo, enviava.
        if ($this->temCredenciais()) return $this->enviarPelasCredenciais($payload);

        $tokenDaSessao = $this->tokenDaSessao();
        if ($tokenDaSessao !== null) return $this->enviarPelaSessao($tokenDaSessao, $payload);

        return $this->enviarPeloTokenDaApi($payload);
    }

    private function temCredenciais(): bool
    {
        return trim((string) config('gi.client_id')) !== '' && trim((string) config('gi.client_secret')) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function enviarPelasCredenciais(array $payload): ?int
    {
        $smtpId = filter_var(config('gi.smtp_configuracao_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($smtpId !== false) $payload['smtp_configuracao_id'] = $smtpId;

        $resposta = Http::acceptJson()->timeout(15)
            ->withHeaders([
                'X-Client-Id' => trim((string) config('gi.client_id')),
                'X-Client-Secret' => trim((string) config('gi.client_secret')),
            ])
            ->post(rtrim((string) config('gi.gi_url'), '/').'/api/integracoes/v1/email-disparos/aplicacao', $payload);

        return $this->resultado($resposta, '/api/integracoes/v1/email-disparos/aplicacao');
    }

    /** access_token gravado na sessao pela troca do codigo em /auth/gi, quando existir. */
    private function tokenDaSessao(): ?string
    {
        if (! app()->bound('session') || ! app('session')->isStarted()) {
            return null;
        }

        $token = trim((string) session('gi_context.access_token', ''));

        return $token !== '' ? $token : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function enviarPelaSessao(string $token, array $payload): ?int
    {
        $smtpId = filter_var(config('gi.smtp_configuracao_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($smtpId !== false) $payload['smtp_configuracao_id'] = $smtpId;

        return $this->postar('/api/integracoes/v1/email-disparos', $token, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function enviarPeloTokenDaApi(array $payload): ?int
    {
        $token = trim((string) config('gi.api_token'));
        $sistemaId = filter_var(config('gi.sistema_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $smtpId = filter_var(config('gi.smtp_configuracao_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($token === '' || $sistemaId === false || $smtpId === false) {
            throw new GiEmailNaoConfiguradoException(
                'O envio de e-mail pelo GI não está configurado. Defina GI_CLIENT_ID e GI_CLIENT_SECRET '
                .'(ou, alternativamente, GI_API_TOKEN e GI_SISTEMA_ID) no .env.'
            );
        }

        return $this->postar('/api/v1/email-disparos', $token, [
            'sistema_id' => $sistemaId,
            'smtp_configuracao_id' => $smtpId,
            ...$payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postar(string $caminho, string $token, array $payload): ?int
    {
        return $this->resultado(
            Http::withToken($token)->acceptJson()->timeout(15)
                ->post(rtrim((string) config('gi.gi_url'), '/').$caminho, $payload),
            $caminho,
        );
    }

    private function resultado(\Illuminate\Http\Client\Response $resposta, string $caminho): ?int
    {
        // Status e caminho sempre na mensagem: quem le o log precisa saber qual dos tres
        // caminhos falhou, e o visitante ve so o aviso generico de quem chamou este servico.
        if (! $resposta->successful()) {
            $mensagem = trim((string) $resposta->json('message'));

            throw new RuntimeException("O GI respondeu HTTP {$resposta->status()} em {$caminho}"
                .($mensagem !== '' ? ": {$mensagem}" : '.'));
        }

        $id = $resposta->json('data.id');

        return $id === null ? null : (int) $id;
    }
}
