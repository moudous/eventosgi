<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Quantos certificados cada participante tem, nas duas gerações do sistema.
 *
 * "Antigos" sao os da tabela certificados, do modelo anterior; "novos" sao os vinculos
 * em lista_participantes, do modelo atual. As duas convivem, entao um participante pode
 * ter certificados de um lado, do outro ou dos dois.
 *
 * A contagem da listagem e feita em duas consultas agrupadas sobre os ids da pagina, e
 * nao com uma subconsulta por linha: certificados nao tem indice em participanteId, e
 * uma subconsulta por linha varreria as treze mil linhas dez vezes a cada pagina.
 */
class ParticipanteCertificadoService
{
    /**
     * @param  list<int>  $participanteIds
     * @return array<int, array{antigos: int, novos: int, total: int}>
     */
    public function contarPorParticipante(array $participanteIds): array
    {
        if ($participanteIds === []) return [];

        $antigos = DB::connection('cert')->table('certificados')
            ->whereIn('participanteId', $participanteIds)
            // Certificado apagado nao conta: o vinculo deixou de existir para o usuario.
            ->whereNull('apagado_em')
            ->groupBy('participanteId')
            ->selectRaw('participanteId as participante, count(*) as total')
            ->pluck('total', 'participante');

        $novos = DB::connection('cert')->table('lista_participantes')
            ->whereIn('participante_id', $participanteIds)
            ->groupBy('participante_id')
            ->selectRaw('participante_id as participante, count(*) as total')
            ->pluck('total', 'participante');

        $contagem = [];

        foreach ($participanteIds as $id) {
            $antigo = (int) ($antigos[$id] ?? 0);
            $novo = (int) ($novos[$id] ?? 0);
            $contagem[$id] = ['antigos' => $antigo, 'novos' => $novo, 'total' => $antigo + $novo];
        }

        return $contagem;
    }

    /** Este participante tem algum certificado, de qualquer uma das duas gerações? */
    public function possuiCertificados(int $participanteId): bool
    {
        return ($this->contarPorParticipante([$participanteId])[$participanteId]['total'] ?? 0) > 0;
    }
}
