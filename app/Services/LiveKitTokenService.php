<?php

namespace App\Services;

use App\Models\Transmissao;
use App\Models\LiveKitConfiguracao;
use Illuminate\Support\Str;
use RuntimeException;

class LiveKitTokenService
{
    /** Gera um JWT de curta duração; o segredo do LiveKit nunca chega ao navegador. */
    public function paraParticipante(Transmissao $transmissao, string $nome): array
    {
        $configuracao = LiveKitConfiguracao::atual();
        $url = rtrim((string) ($configuracao?->url ?: config('livekit.url')), '/');
        $chave = (string) ($configuracao?->api_key ?: config('livekit.api_key'));
        $segredo = (string) ($configuracao?->api_secret ?: config('livekit.api_secret'));
        if ($url === '' || $chave === '' || $segredo === '') {
            throw new RuntimeException('A videoconferência ainda não foi configurada. Defina LIVEKIT_URL, LIVEKIT_API_KEY e LIVEKIT_API_SECRET.');
        }

        $agora = time();
        $sala = 'transmissao-'.$transmissao->id;
        $cabecalho = $this->base64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $conteudo = $this->base64Url(json_encode([
            'iss' => $chave,
            'sub' => 'participante-'.Str::uuid(),
            'name' => $nome,
            'nbf' => $agora - 5,
            'exp' => $agora + 3600,
            'video' => [
                'room' => $sala,
                'roomJoin' => true,
                'canPublish' => true,
                'canSubscribe' => true,
                'canPublishData' => true,
                'canPublishSources' => ['camera', 'microphone', 'screen_share', 'screen_share_audio'],
            ],
        ], JSON_THROW_ON_ERROR));

        return ['url' => $url, 'token' => $cabecalho.'.'.$conteudo.'.'.$this->base64Url(hash_hmac('sha256', $cabecalho.'.'.$conteudo, $segredo, true))];
    }

    private function base64Url(string $valor): string
    {
        return rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
    }
}
