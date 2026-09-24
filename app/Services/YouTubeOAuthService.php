<?php

namespace App\Services;

use App\Models\YouTubeConexao;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class YouTubeOAuthService
{
    private const AUTORIZAR_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const CANAL_URL = 'https://www.googleapis.com/youtube/v3/channels';

    public function urlDeAutorizacao(string $state): string
    {
        $this->validarConfiguracao();

        return self::AUTORIZAR_URL.'?'.http_build_query([
            'client_id' => config('youtube.client_id'),
            'redirect_uri' => config('youtube.redirect_uri'),
            'response_type' => 'code',
            'scope' => config('youtube.scope'),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function conectar(string $codigo, ?int $usuarioId): YouTubeConexao
    {
        $this->validarConfiguracao();

        $resposta = Http::asForm()->acceptJson()->timeout(20)->post(self::TOKEN_URL, [
            'code' => $codigo,
            'client_id' => config('youtube.client_id'),
            'client_secret' => config('youtube.client_secret'),
            'redirect_uri' => config('youtube.redirect_uri'),
            'grant_type' => 'authorization_code',
        ]);

        if (! $resposta->successful() || ! $resposta->json('access_token')) {
            throw new RuntimeException('O Google não aceitou a autorização. Tente conectar a conta novamente.');
        }

        $token = (string) $resposta->json('access_token');
        $canal = Http::withToken($token)->acceptJson()->timeout(20)->get(self::CANAL_URL, [
            'part' => 'snippet',
            'mine' => 'true',
        ]);
        $item = $canal->json('items.0');
        if (! $canal->successful() || ! is_array($item) || ! isset($item['id'])) {
            throw new RuntimeException('Nenhum canal do YouTube foi encontrado para a conta autorizada.');
        }

        $conexaoAnterior = YouTubeConexao::atual();

        $dados = [
            'canal_id' => (string) $item['id'],
            'canal_titulo' => (string) ($item['snippet']['title'] ?? 'Canal do YouTube'),
            'access_token' => $token,
            'refresh_token' => (string) ($resposta->json('refresh_token') ?: $conexaoAnterior?->refresh_token),
            'expira_em' => CarbonImmutable::now()->addSeconds(max(0, (int) $resposta->json('expires_in', 3600))),
            'conectado_por' => $usuarioId,
        ];
        if ($conexaoAnterior) {
            $conexaoAnterior->update($dados);

            return $conexaoAnterior->refresh();
        }

        return YouTubeConexao::create($dados);
    }

    private function validarConfiguracao(): void
    {
        if (! config('youtube.client_id') || ! config('youtube.client_secret') || ! config('youtube.redirect_uri')) {
            throw new RuntimeException('Configure GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET e GOOGLE_REDIRECT_URI no arquivo .env.');
        }
    }
}
