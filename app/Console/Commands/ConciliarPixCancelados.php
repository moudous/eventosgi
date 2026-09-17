<?php

namespace App\Console\Commands;

use App\Models\PixCobranca;
use App\Services\SicoobPixService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConciliarPixCancelados extends Command
{
    protected $signature = 'pix:conciliar-canceladas';
    protected $description = 'Consulta cobranças de inscrições canceladas para detectar pagamentos tardios';

    public function handle(SicoobPixService $pix): int
    {
        $erros = 0;
        $confirmadas = 0;

        PixCobranca::query()
            ->whereNull('pago_em')
            ->where('status', '!=', 'CONCLUIDA')
            ->whereHas('inscricao', fn ($inscricao) => $inscricao
                ->withoutGlobalScope('ativas')
                ->whereNull('ativa')
                ->where('cancelada_em', '>=', now()->subDays(7)))
            ->orderBy('id')
            ->eachById(function (PixCobranca $cobranca) use ($pix, &$erros, &$confirmadas): void {
                try {
                    $atualizada = $pix->consultar($cobranca);
                    if (! $atualizada->pagamentoConfirmado()) return;

                    $confirmadas++;
                    Log::critical('Pagamento PIX confirmado após o cancelamento da inscrição.', [
                        'pix_cobranca_id' => $atualizada->id,
                        'inscricao_atividade_id' => $atualizada->inscricao_atividade_id,
                        'txid' => $atualizada->txid,
                        'valor' => $atualizada->valor,
                    ]);
                } catch (Throwable $erro) {
                    $erros++;
                    report($erro);
                }
            }, 100);

        $this->info("Conciliação concluída: {$confirmadas} pagamento(s) tardio(s), {$erros} erro(s).");

        return $erros > 0 ? self::FAILURE : self::SUCCESS;
    }
}
