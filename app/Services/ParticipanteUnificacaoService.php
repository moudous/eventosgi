<?php

namespace App\Services;

use App\Models\InscricaoAtividade;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Unifica cadastros duplicados de participante, replicando o comportamento da
 * unificacao manual do sistema de certificados (/participantes -> unificacao).
 *
 * O destino e sempre o cadastro de menor id; certificados legados (certificados)
 * e novos (lista_participantes) e demais vinculos passam a apontar para ele, e a
 * operacao fica registrada em unificacoes_realizadas para poder ser desfeita.
 *
 * Diferenca proposital em relacao a unificacao manual: quando uma mesma emissao
 * possui certificado gerado para mais de um dos cadastros duplicados, a manual
 * aborta e pede intervencao. Aqui o visitante nao teria como resolver isso, entao
 * as duas linhas sao mantidas e apenas repontadas para o destino - nenhum arquivo
 * gerado e descartado.
 */
class ParticipanteUnificacaoService
{
    /** @var list<string> Tabelas do banco de certificados que apenas apontam para o participante. */
    private const VINCULOS_SIMPLES = ['rubricas_participantes', 'assinaturas_template'];

    /** @var list<string> Tabelas que aceitam no maximo uma linha por participante. */
    private const VINCULOS_UNICOS = ['participantes_de_teste', 'responsaveis'];

    private function cert(): ConnectionInterface
    {
        return DB::connection('cert');
    }

    /**
     * Unifica os cadastros informados no de menor id.
     *
     * @param  list<int>  $ids
     * @return array{participante_id: int, participante_nome: string, removidos: int, certificados_legados: int, certificados_novos: int, unificacao_id: ?int}
     */
    public function unificar(array $ids, ?string $origem = null): array
    {
        $ids = collect($ids)->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
        abort_if(count($ids) < 2, 422, 'A unificação exige ao menos dois cadastros.');

        $resultado = $this->cert()->transaction(function () use ($ids, $origem): array {
            $participantes = $this->cert()->table('participantes')
                ->whereIn('id', $ids)->whereNull('excluido_em')->lockForUpdate()->get(['id', 'nome']);

            // Outra unificacao pode ter passado antes desta; nesse caso nao ha o que fazer.
            if ($participantes->count() < 2) {
                return ['participante_id' => (int) ($participantes->first()->id ?? $ids[0]), 'participante_nome' => (string) ($participantes->first()->nome ?? ''),
                    'removidos' => 0, 'certificados_legados' => 0, 'certificados_novos' => 0, 'unificacao_id' => null];
            }

            $ids = $participantes->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
            $antes = $this->capturar($ids);
            $destinoId = $ids[0];
            $destinoNome = (string) $participantes->firstWhere('id', $destinoId)->nome;
            $origensIds = array_values(array_diff($ids, [$destinoId]));

            $certificadosLegados = $this->cert()->table('certificados')->whereIn('participanteId', $origensIds)->count();
            $this->cert()->table('certificados')->whereIn('participanteId', $ids)
                ->update(['participanteId' => $destinoId, 'nome' => $destinoNome, 'atualizado_em' => now()]);

            $certificadosNovos = $this->repontarCertificadosNovos($ids, $origensIds, $destinoId);

            foreach (self::VINCULOS_SIMPLES as $tabela) {
                $this->cert()->table($tabela)->whereIn('participante_id', $origensIds)
                    ->update(['participante_id' => $destinoId, 'alterado_em' => now()]);
            }

            foreach (self::VINCULOS_UNICOS as $tabela) {
                $this->repontarVinculoUnico($tabela, $ids, $destinoId);
            }

            $removidos = $this->cert()->table('participantes')->whereIn('id', $origensIds)->delete();
            $unificacaoId = $this->registrar($antes, $ids, $destinoId, $destinoNome, $origem);

            return [
                'participante_id' => $destinoId,
                'participante_nome' => $destinoNome,
                'removidos' => $removidos,
                'certificados_legados' => $certificadosLegados,
                'certificados_novos' => $certificadosNovos,
                'unificacao_id' => $unificacaoId,
            ];
        });

        // As inscricoes ficam em outro banco (conexao padrao), fora da transacao acima.
        if ($resultado['removidos'] > 0) {
            InscricaoAtividade::query()
                ->whereIn('participante_id', array_values(array_diff($ids, [$resultado['participante_id']])))
                ->update(['participante_id' => $resultado['participante_id']]);
        }

        return $resultado;
    }

    /**
     * Move as linhas de lista_participantes para o destino, sem deixar duas linhas
     * da mesma emissao quando so uma delas tem certificado gerado.
     *
     * @param  list<int>  $ids
     * @param  list<int>  $origensIds
     */
    private function repontarCertificadosNovos(array $ids, array $origensIds, int $destinoId): int
    {
        $linhas = $this->cert()->table('lista_participantes')->whereIn('participante_id', $ids)->lockForUpdate()->get();
        $movidas = $linhas->whereIn('participante_id', $origensIds)->count();

        foreach ($linhas->groupBy('novo_certificado_id') as $emissao) {
            $geradas = $emissao->filter(fn (object $linha): bool => collect([
                $linha->codigo ?? null, $linha->codigo_img ?? null, $linha->arquivo_pdf ?? null, $linha->arquivo_img ?? null,
            ])->contains(fn ($valor): bool => filled($valor)));

            // Mais de um certificado gerado na mesma emissao: preserva as duas linhas em vez de escolher uma.
            if ($geradas->count() > 1) {
                $this->cert()->table('lista_participantes')->whereIn('id', $emissao->pluck('id'))
                    ->where('participante_id', '<>', $destinoId)
                    ->update(['participante_id' => $destinoId, 'alterado_em' => now()]);

                continue;
            }

            $mantida = $geradas->first() ?? $emissao->firstWhere('participante_id', $destinoId) ?? $emissao->first();
            $descartar = $this->descartaveis($emissao, $mantida);

            if ($descartar->isNotEmpty()) {
                $this->cert()->table('novos_certificados')->whereIn('lista_participantes_id', $descartar)
                    ->update(['lista_participantes_id' => $mantida->id, 'alterado_em' => now()]);
                $this->cert()->table('lista_participantes')->whereIn('id', $descartar)->delete();
            }

            if ((int) $mantida->participante_id !== $destinoId) {
                $this->cert()->table('lista_participantes')->where('id', $mantida->id)
                    ->update(['participante_id' => $destinoId, 'alterado_em' => now()]);
            }
        }

        return $movidas;
    }

    /**
     * @param  Collection<int, object>  $emissao
     * @return Collection<int, int>
     */
    private function descartaveis(Collection $emissao, object $mantida): Collection
    {
        return $emissao->pluck('id')->reject(fn ($id): bool => (int) $id === (int) $mantida->id)->map(fn ($id): int => (int) $id)->values();
    }

    /**
     * Mantem uma unica linha da tabela e a aponta para o destino, repontando o que dependia das demais.
     *
     * @param  list<int>  $ids
     */
    private function repontarVinculoUnico(string $tabela, array $ids, int $destinoId): void
    {
        $linhas = $this->cert()->table($tabela)->whereIn('participante_id', $ids)->lockForUpdate()->get();
        if ($linhas->isEmpty()) return;

        $mantida = $linhas->firstWhere('participante_id', $destinoId) ?? $linhas->first();
        $descartar = $this->descartaveis($linhas, $mantida);

        if ($descartar->isNotEmpty()) {
            if ($tabela === 'responsaveis') {
                $this->cert()->table('novos_certificados')->whereIn('responsavel_id', $descartar)
                    ->update(['responsavel_id' => $mantida->id, 'alterado_em' => now()]);
            }
            $this->cert()->table($tabela)->whereIn('id', $descartar)->delete();
        }

        $this->cert()->table($tabela)->where('id', $mantida->id)
            ->update(['participante_id' => $destinoId, 'alterado_em' => now()]);
    }

    /**
     * Fotografia das linhas afetadas, no mesmo formato usado pelo sistema de certificados,
     * para que /desfazerunificacao consiga reverter a operacao.
     *
     * @param  list<int>  $ids
     * @return array<string, list<array<string, mixed>>>
     */
    private function capturar(array $ids): array
    {
        $novos = $this->linhas('lista_participantes', 'participante_id', $ids);
        $responsaveis = $this->linhas('responsaveis', 'participante_id', $ids);

        return [
            'participantes' => $this->linhas('participantes', 'id', $ids),
            'certificados' => $this->linhas('certificados', 'participanteId', $ids),
            'lista_participantes' => $novos,
            'rubricas_participantes' => $this->linhas('rubricas_participantes', 'participante_id', $ids),
            'participantes_de_teste' => $this->linhas('participantes_de_teste', 'participante_id', $ids),
            'assinaturas_template' => $this->linhas('assinaturas_template', 'participante_id', $ids),
            'responsaveis' => $responsaveis,
            'novos_certificados' => $this->cert()->table('novos_certificados')
                ->where(fn ($consulta) => $consulta
                    ->whereIn('lista_participantes_id', collect($novos)->pluck('id')->all())
                    ->orWhereIn('responsavel_id', collect($responsaveis)->pluck('id')->all()))
                ->orderBy('id')->get()->map(fn (object $linha): array => (array) $linha)->all(),
        ];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $antes
     * @param  list<int>  $ids
     */
    private function registrar(array $antes, array $ids, int $destinoId, string $destinoNome, ?string $origem): int
    {
        $excluidos = collect($antes['participantes'])
            ->reject(fn (array $participante): bool => (int) $participante['id'] === $destinoId)
            ->map(fn (array $participante): array => [
                'id' => (int) $participante['id'],
                'nome' => $participante['nome'] ?? null,
                'email' => $participante['email'] ?? null,
            ])->values()->all();

        return (int) $this->cert()->table('unificacoes_realizadas')->insertGetId([
            'participante_novo_id' => $destinoId,
            'participante_novo_nome' => $destinoNome,
            'participantes_excluidos' => json_encode($excluidos, JSON_UNESCAPED_UNICODE),
            'usuario_id' => null,
            'usuario_nome' => mb_substr($origem ?: 'Formulário público de inscrição', 0, 150),
            'dados_antes' => json_encode($antes, JSON_UNESCAPED_UNICODE),
            'dados_depois' => json_encode([
                'destino_criado' => false,
                'participante_destino_id' => $destinoId,
                'participante_destino_nome' => $destinoNome,
            ], JSON_UNESCAPED_UNICODE),
            'status' => 'realizada',
            'criado_em' => now(),
            'alterado_em' => now(),
        ]);
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function linhas(string $tabela, string $coluna, array $ids): array
    {
        return $this->cert()->table($tabela)->whereIn($coluna, $ids)->orderBy('id')->get()
            ->map(fn (object $linha): array => (array) $linha)->all();
    }
}
