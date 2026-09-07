<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Disparo de e-mail pela API do GI.
 *
 * Existem tres caminhos, e qual deles vale depende de quem disparou:
 *
 * 1. Sessao da aplicacao externa (POST /api/integracoes/v1/email-disparos), autenticada
 *    com o access_token que o GI devolve na troca do codigo em /auth/gi. E o caminho que
 *    o gi-starter-aprova usa, e so existe enquanto ha um usuario logado pelo GI.
 *
 * 2. Credenciais da aplicacao (POST /api/integracoes/v1/email-disparos/aplicacao), com
 *    client_id e client_secret. E o caminho do formulario publico: o visitante e anonimo
 *    e nao existe access_token de sessao, mas a aplicacao se identifica sozinha.
 *
 * 3. Token da API GI (POST /api/v1/email-disparos), usado apenas se GI_API_TOKEN estiver
 *    definido e nao houver credenciais configuradas.
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

        $tokenDaSessao = $this->tokenDaSessao();
        if ($tokenDaSessao !== null) return $this->enviarPelaSessao($tokenDaSessao, $payload);

        if ($this->temCredenciais()) return $this->enviarPelasCredenciais($payload);

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
        if (! $resposta->successful()) {
            throw new RuntimeException((string) ($resposta->json('message')
                ?: "O GI respondeu HTTP {$resposta->status()} em {$caminho}."));
        }

        $id = $resposta->json('data.id');

        return $id === null ? null : (int) $id;
    }
}
