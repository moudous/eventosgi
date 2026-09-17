<?php

namespace App\Services;

use App\Exceptions\PagamentoPixConfirmadoException;
use App\Models\InscricaoAtividade;
use Illuminate\Support\Facades\DB;

class CancelamentoInscricaoService
{
    public function __construct(
        private readonly SicoobPixService $pix,
        private readonly DistribuicaoVagasService $distribuicao,
    ) {}

    public function cancelar(InscricaoAtividade $inscricao, string $motivo): InscricaoAtividade
    {
        $cobrancas = $inscricao->cobrancasPix()->orderBy('id')->get();
        if ($cobrancas->contains->pagamentoConfirmado()) throw new PagamentoPixConfirmadoException;

        // O PATCH no PSP é a barreira decisiva: se o pagamento concluiu durante a
        // operação, o Sicoob recusa a remoção e a inscrição permanece ativa.
        foreach ($cobrancas as $cobranca) $this->pix->cancelar($cobranca);

        $cancelada = DB::transaction(function () use ($inscricao, $motivo): InscricaoAtividade {
            $registro = InscricaoAtividade::query()->whereKey($inscricao->id)->lockForUpdate()->firstOrFail();
            if ($registro->cobrancasPix()->confirmadas()->lockForUpdate()->exists()) {
                throw new PagamentoPixConfirmadoException;
            }
            $registro->forceFill([
                'ativa' => null,
                'cancelada_em' => now(),
                'cancelamento_motivo' => $motivo,
            ])->save();

            return $registro;
        });

        $this->distribuicao->recalcular($cancelada->atividade()->firstOrFail());

        return $cancelada;
    }
}
