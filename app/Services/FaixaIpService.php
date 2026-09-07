<?php

namespace App\Services;

use App\Models\FaixaIpLiberada;
use Illuminate\Support\Facades\Cache;

/**
 * Faixas de rede liberadas dos limites por origem no envio de codigos.
 *
 * Numa instituicao a faixa de rede quase nao discrimina: uma turma inteira e um robo
 * saem pelo mesmo IP publico. Aqui o administrador declara quais redes sao dela -- o
 * wi-fi do campus, o laboratorio -- e nessas o balde por rede deixa de valer.
 *
 * Continuam valendo, dentro da faixa liberada, todas as protecoes que nao dependem da
 * rede: intervalo de reenvio, limite por e-mail, limite de enderecos por sessao do
 * navegador, teto da atividade, campo isca e selo do formulario.
 */
class FaixaIpService
{
    /** Chave do cache da lista ativa. Curta porque a leitura acontece a cada pedido de codigo. */
    private const CACHE = 'faixas-ip-liberadas';

    private const CACHE_SEGUNDOS = 300;

    /**
     * Converte o que o administrador digitou em notacao CIDR canonica, ou null quando
     * nao e um endereco nem uma faixa valida.
     *
     * Aceita endereco solto (vira /32 ou /128) e zera os bits fora do prefixo, para
     * "198.51.100.37/24" e "198.51.100.0/24" nao virarem dois registros da mesma rede.
     */
    public function normalizar(string $entrada): ?string
    {
        $entrada = trim($entrada);

        if ($entrada === '') return null;

        [$endereco, $prefixo] = array_pad(explode('/', $entrada, 2), 2, null);

        $binario = @inet_pton(trim((string) $endereco));

        if ($binario === false) return null;

        $bits = strlen($binario) * 8;

        if ($prefixo === null) {
            $prefixo = $bits;
        } elseif (! ctype_digit(trim($prefixo)) || (int) trim($prefixo) > $bits) {
            return null;
        } else {
            $prefixo = (int) trim($prefixo);
        }

        $rede = @inet_ntop($this->mascarar($binario, $prefixo));

        return $rede === false ? null : $rede.'/'.$prefixo;
    }

    /** O endereco esta dentro da faixa? Responde false quando as familias diferem. */
    public function contem(string $faixa, string $ip): bool
    {
        [$endereco, $prefixo] = array_pad(explode('/', $faixa, 2), 2, null);

        $rede = @inet_pton((string) $endereco);
        $alvo = @inet_pton($ip);

        if ($rede === false || $alvo === false || strlen($rede) !== strlen($alvo)) return false;

        $prefixo = $prefixo === null ? strlen($rede) * 8 : (int) $prefixo;

        return $this->mascarar($rede, $prefixo) === $this->mascarar($alvo, $prefixo);
    }

    /** O visitante veio de alguma faixa liberada e ativa? */
    public function liberado(?string $ip): bool
    {
        if ($ip === null || @inet_pton($ip) === false) return false;

        foreach ($this->ativas() as $faixa) {
            if ($this->contem($faixa, $ip)) return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function ativas(): array
    {
        return Cache::remember(
            self::CACHE,
            self::CACHE_SEGUNDOS,
            fn () => FaixaIpLiberada::query()->where('ativo', true)->orderBy('faixa')->pluck('faixa')->all(),
        );
    }

    /** Chamado a cada gravacao: sem isso a faixa nova levaria ate cinco minutos para valer. */
    public function esquecerCache(): void
    {
        Cache::forget(self::CACHE);
    }

    /** Zera os bits do endereco que ficam fora do prefixo. */
    private function mascarar(string $binario, int $prefixo): string
    {
        $bytesInteiros = intdiv($prefixo, 8);
        $bitsRestantes = $prefixo % 8;

        $mascarado = substr($binario, 0, $bytesInteiros);

        if ($bitsRestantes > 0 && $bytesInteiros < strlen($binario)) {
            $mascarado .= chr(ord($binario[$bytesInteiros]) & (0xFF << (8 - $bitsRestantes)) & 0xFF);
            $bytesInteiros++;
        }

        return str_pad($mascarado, strlen($binario), "\0");
    }
}
