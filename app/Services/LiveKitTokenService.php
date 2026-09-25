<?php

namespace App\Services;

use App\Models\Transmissao;
use App\Models\LiveKitConfiguracao;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class LiveKitTokenService
{
    /** Gera um JWT de curta duração; o segredo do LiveKit nunca chega ao navegador. */
    public function paraParticipante(Transmissao $transmissao, string $nome, bool $administrador = false): array
    {
        [$url, $chave, $segredo] = $this->credenciais();

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
                'roomAdmin' => $administrador,
                'canPublishSources' => ['camera', 'microphone', 'screen_share', 'screen_share_audio'],
            ],
        ], JSON_THROW_ON_ERROR));

        return [
            'url' => $url,
            'token' => $cabecalho.'.'.$conteudo.'.'.$this->base64Url(hash_hmac('sha256', $cabecalho.'.'.$conteudo, $segredo, true)),
            'administrador' => $administrador,
        ];
    }

    /** Silencia ou reativa uma faixa publicada usando a API administrativa do LiveKit. */
    public function definirMudo(Transmissao $transmissao, string $identidade, string $trackSid, bool $mudo): void
    {
        [$url, $chave, $segredo] = $this->credenciais();
        $agora = time();
        $cabecalho = $this->base64Url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $conteudo = $this->base64Url(json_encode([
            'iss' => $chave,
            'sub' => 'moderador-'.Str::uuid(),
            'nbf' => $agora - 5,
            'exp' => $agora + 60,
            'video' => ['room' => 'transmissao-'.$transmissao->id, 'roomAdmin' => true],
        ], JSON_THROW_ON_ERROR));
        $token = $cabecalho.'.'.$conteudo.'.'.$this->base64Url(hash_hmac('sha256', $cabecalho.'.'.$conteudo, $segredo, true));
        $apiUrl = preg_replace('/^wss:/i', 'https:', preg_replace('/^ws:/i', 'http:', $url))
            .'/twirp/livekit.RoomService/MutePublishedTrack';

        $resposta = Http::withToken($token)->acceptJson()->asJson()->timeout(10)->post($apiUrl, [
            'room' => 'transmissao-'.$transmissao->id,
            'identity' => $identidade,
            'track_sid' => $trackSid,
            'muted' => $mudo,
        ]);
        if (! $resposta->successful()) {
            report(new RuntimeException('LiveKit não aceitou a alteração da mídia: '.$resposta->status()));
            throw new RuntimeException('Não foi possível alterar a câmera ou o microfone deste participante.');
        }
    }

    private function credenciais(): array
    {
        $configuracao = LiveKitConfiguracao::atual();
        $url = rtrim((string) ($configuracao?->url ?: config('livekit.url')), '/');
        $chave = (string) ($configuracao?->api_key ?: config('livekit.api_key'));
        $segredo = (string) ($configuracao?->api_secret ?: config('livekit.api_secret'));
        if ($url === '' || $chave === '' || $segredo === '') {
            throw new RuntimeException('A videoconferência ainda não foi configurada. Defina LIVEKIT_URL, LIVEKIT_API_KEY e LIVEKIT_API_SECRET.');
        }

        return [$url, $chave, $segredo];
    }

    private function base64Url(string $valor): string
    {
        return rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
    }
}
