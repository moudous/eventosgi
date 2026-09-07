<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Origem tecnica de um envio: IP, User-Agent bruto e o que da para extrair dele
 * (navegador, sistema operacional, tipo de aparelho, idioma).
 *
 * O projeto nao usa uma biblioteca de deteccao para nao acrescentar dependencia
 * por causa de um punhado de campos; a leitura cobre os agentes usuais e guarda o
 * User-Agent inteiro para quando a heuristica nao der conta.
 */
class DispositivoVisitanteService
{
    /** Navegadores em ordem de especificidade: Edge e Opera se declaram Chrome, e Chrome se declara Safari. */
    private const NAVEGADORES = [
        ['Edge', '/\bEdgi?[A-Z]?\/([\d.]+)/i'],
        ['Opera', '/\b(?:OPR|OPiOS)\/([\d.]+)/'],
        ['Samsung Internet', '/\bSamsungBrowser\/([\d.]+)/'],
        ['Firefox', '/\b(?:Firefox|FxiOS)\/([\d.]+)/'],
        ['Chrome', '/\b(?:Chrome|CriOS|Chromium)\/([\d.]+)/'],
        ['Safari', '/\bVersion\/([\d.]+).*\bSafari\//'],
        ['Internet Explorer', '/\b(?:MSIE\s|rv:)([\d.]+).*Trident\//'],
    ];

    private const SISTEMAS = [
        ['Windows', '/Windows NT ([\d.]+)/'],
        ['Android', '/Android ([\d.]+)/'],
        ['iOS', '/(?:iPhone OS|CPU OS) ([\d_]+)/'],
        ['macOS', '/Mac OS X ([\d_.]+)/'],
        ['Chrome OS', '/CrOS \S+ ([\d.]+)/'],
    ];

    /** Windows so informa a versao do kernel; a partir do 10 o UA nao distingue 10 de 11. */
    private const VERSOES_WINDOWS = ['10.0' => '10/11', '6.3' => '8.1', '6.2' => '8', '6.1' => '7', '6.0' => 'Vista', '5.1' => 'XP'];

    /**
     * @return array{ip: ?string, user_agent: ?string, dispositivo: array<string, mixed>}
     */
    public function capturar(Request $request): array
    {
        [$ip, $userAgent] = $this->doVisitante($request);

        return [
            'ip' => $ip,
            'user_agent' => $userAgent !== '' ? mb_substr($userAgent, 0, 512) : null,
            'dispositivo' => array_filter([
                ...$this->navegador($userAgent, $request),
                ...$this->sistema($userAgent, $request),
                'plataforma' => $this->plataforma($userAgent, $request),
                'idioma' => $this->idioma($request),
                'origem' => $this->origem($request),
                // Identificador derivado da sessao: correlaciona envios sem guardar o id em si.
                'sessao' => $this->sessao($request),
            ], fn ($valor) => $valor !== null && $valor !== ''),
        ];
    }

    /**
     * IP de quem preencheu o formulario, seguindo a mesma regra de doVisitante().
     *
     * Publico porque o limite de envios por IP precisa contar o visitante, nao o
     * servidor do consumidor: sem isso todo o WordPress dividiria um unico balde.
     */
    public function ipDoVisitante(Request $request): ?string
    {
        return $this->doVisitante($request)[0];
    }

    /**
     * Faixa de rede do visitante: /24 no IPv4, /64 no IPv6.
     *
     * Os limites de envio contam por faixa, nunca por endereco exato. Qualquer contratacao
     * de IPv6 vem com um /64 inteiro, o que rende enderecos praticamente ilimitados de
     * graca: um balde por endereco seria contornado trocando de IP a cada pedido, e o
     * limite existiria so no papel.
     */
    public function faixaDoVisitante(Request $request): ?string
    {
        $ip = $this->ipDoVisitante($request);

        if ($ip === null) return null;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return implode('.', array_slice(explode('.', $ip), 0, 3)).'.0/24';
        }

        $binario = @inet_pton($ip);

        if ($binario === false || strlen($binario) !== 16) return null;

        // Primeiros 64 bits: o prefixo que o provedor delega. Os 64 finais a propria
        // maquina escolhe, e e justamente ai que um robo trocaria de endereco.
        $prefixo = @inet_ntop(substr($binario, 0, 8).str_repeat("\0", 8));

        return $prefixo === false ? null : $prefixo.'/64';
    }

    /**
     * IP e User-Agent de quem preencheu o formulario.
     *
     * Numa chamada de API o remetente e o servidor do consumidor, nao o visitante; nesse
     * caso valem os cabecalhos que ele repassa, aceitos so depois do token conferido em
     * VerificarTokenFormulario.
     *
     * @return array{0: ?string, 1: string}
     */
    private function doVisitante(Request $request): array
    {
        $ip = $request->ip();
        $userAgent = trim((string) $request->userAgent());

        if ($request->attributes->get('formulario_autenticado') === true) {
            $repassado = trim((string) $request->header('X-Visitante-Ip', ''));
            if (filter_var($repassado, FILTER_VALIDATE_IP)) $ip = $repassado;

            $agenteRepassado = trim((string) $request->header('X-Visitante-User-Agent', ''));
            if ($agenteRepassado !== '') $userAgent = $agenteRepassado;
        }

        return [$ip, $userAgent];
    }

    /** Texto curto para exibir em telas e planilhas. */
    public function resumo(?array $dispositivo): string
    {
        if (! $dispositivo) return '—';

        $navegador = trim(($dispositivo['navegador'] ?? '').' '.($dispositivo['navegador_versao'] ?? ''));
        $sistema = trim(($dispositivo['sistema'] ?? '').' '.($dispositivo['sistema_versao'] ?? ''));
        $partes = array_filter([$navegador, $sistema !== '' ? 'no '.$sistema : '']);

        $texto = implode(' ', $partes);

        // Robos e clientes de linha de comando nao trazem navegador nem sistema:
        // sem isto o resumo ficaria vazio justamente nos envios que mais interessam.
        if ($texto === '') return ucfirst((string) ($dispositivo['plataforma'] ?? '')) ?: '—';

        if (! empty($dispositivo['plataforma'])) $texto .= " ({$dispositivo['plataforma']})";

        return $texto;
    }

    /**
     * @return array{navegador?: string, navegador_versao?: string}
     */
    private function navegador(string $userAgent, Request $request): array
    {
        foreach (self::NAVEGADORES as [$nome, $padrao]) {
            if (preg_match($padrao, $userAgent, $achado)) {
                return ['navegador' => $nome, 'navegador_versao' => explode('.', $achado[1])[0]];
            }
        }

        // Client Hints chegam so em contexto seguro, mas quando chegam sao mais confiaveis.
        if (! preg_match_all('/"([^"]+)";\s*v="(\d+)"/', (string) $request->header('Sec-CH-UA', ''), $achados, PREG_SET_ORDER)) {
            return [];
        }

        // A lista traz uma marca de enfeite ("Not_A Brand") e a generica ("Chromium")
        // ao lado da real ("Google Chrome"); a util e a que sobra depois de descartar as duas.
        $marcas = array_values(array_filter($achados, fn (array $marca): bool => ! preg_match('/not.*brand/i', $marca[1])));
        if ($marcas === []) return [];

        $escolhida = null;
        foreach ($marcas as $marca) {
            if (strcasecmp($marca[1], 'Chromium') !== 0) { $escolhida = $marca; break; }
        }

        $escolhida ??= $marcas[0];

        return ['navegador' => $escolhida[1], 'navegador_versao' => $escolhida[2]];
    }

    /**
     * @return array{sistema?: string, sistema_versao?: string}
     */
    private function sistema(string $userAgent, Request $request): array
    {
        foreach (self::SISTEMAS as [$nome, $padrao]) {
            if (! preg_match($padrao, $userAgent, $achado)) continue;

            $versao = str_replace('_', '.', $achado[1]);
            if ($nome === 'Windows') $versao = self::VERSOES_WINDOWS[$versao] ?? $versao;

            return ['sistema' => $nome, 'sistema_versao' => $versao];
        }

        if (str_contains($userAgent, 'Linux')) return ['sistema' => 'Linux'];

        $plataforma = trim((string) $request->header('Sec-CH-UA-Platform', ''), '"');

        return $plataforma !== '' ? ['sistema' => $plataforma] : [];
    }

    private function plataforma(string $userAgent, Request $request): ?string
    {
        if (preg_match('/bot|crawler|spider|slurp|curl|wget|python-requests|headless/i', $userAgent)) return 'robô';
        if (str_contains($userAgent, 'iPad') || (str_contains($userAgent, 'Android') && ! str_contains($userAgent, 'Mobile'))) return 'tablet';
        if ($request->header('Sec-CH-UA-Mobile') === '?1') return 'celular';
        if (preg_match('/Mobile|iPhone|iPod|Android/i', $userAgent)) return 'celular';

        return $userAgent !== '' ? 'computador' : null;
    }

    private function idioma(Request $request): ?string
    {
        $cabecalho = $request->attributes->get('formulario_autenticado') === true
            ? (string) $request->header('X-Visitante-Idioma', '')
            : (string) $request->header('Accept-Language', '');
        if ($cabecalho === '') return null;

        // "pt-BR,pt;q=0.9,en;q=0.8" -> "pt-BR"
        return mb_substr(trim(explode(';', explode(',', $cabecalho)[0])[0]), 0, 20) ?: null;
    }

    /** Site que encaminhou o visitante, util quando o formulario e aberto pelo WordPress. */
    private function origem(Request $request): ?string
    {
        $referencia = $request->attributes->get('formulario_autenticado') === true
            ? (string) $request->header('X-Visitante-Origem', '')
            : (string) $request->headers->get('referer', '');

        return $referencia !== '' ? mb_substr(strtok($referencia, '?') ?: $referencia, 0, 255) : null;
    }

    private function sessao(Request $request): ?string
    {
        if (! $request->hasSession() || ! $request->session()->isStarted()) return null;

        return substr(hash('sha256', $request->session()->getId()), 0, 16);
    }
}
