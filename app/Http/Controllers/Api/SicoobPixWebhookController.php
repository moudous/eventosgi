<?php

namespace App\Http\Controllers\Api;

use App\Models\PixCobranca;
use App\Services\SicoobPixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SicoobPixWebhookController
{
    public function status(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function receber(Request $request, SicoobPixService $sicoob): JsonResponse
    {
        $pixRecebidos = $request->input('pix', []);
        if (! is_array($pixRecebidos)) {
            return response()->json(['message' => 'Notificação PIX inválida.'], 422);
        }

        $txids = collect($pixRecebidos)
            ->take(100)
            ->map(fn (mixed $pix): string => is_array($pix) ? trim((string) ($pix['txid'] ?? '')) : '')
            ->filter(fn (string $txid): bool => preg_match('/^[A-Za-z0-9]{1,35}$/', $txid) === 1)
            ->unique()
            ->values();

        // Uma chave PIX pode ter cobranças de outros sistemas. Só consultamos TXIDs
        // conhecidos localmente, evitando transformar esta rota pública em proxy da API.
        $cobrancas = PixCobranca::query()->whereIn('txid', $txids)->get()->keyBy('txid');
        $confirmadas = 0;
        $ignoradas = $txids->count() - $cobrancas->count();
        $erros = 0;

        foreach ($cobrancas as $cobranca) {
            $processada = Cache::lock('pix-webhook:'.$cobranca->txid, 30)->get(function () use ($cobranca, $sicoob, &$confirmadas, &$erros): void {
                try {
                    $atual = $cobranca->fresh();
                    if (! $atual || $atual->pago_em !== null) return;

                    $inscricaoCancelada = $atual->inscricao?->cancelada_em !== null;
                    $atualizada = $sicoob->consultar($atual);
                    if (! $atualizada->pagamentoConfirmado()) return;

                    $confirmadas++;
                    if ($inscricaoCancelada) {
                        Log::critical('Pagamento PIX confirmado após o cancelamento da inscrição.', [
                            'pix_cobranca_id' => $atualizada->id,
                            'inscricao_atividade_id' => $atualizada->inscricao_atividade_id,
                            'txid' => $atualizada->txid,
                            'valor' => $atualizada->valor,
                        ]);
                    }
                } catch (Throwable $erro) {
                    $erros++;
                    report($erro);
                }
            });

            if ($processada === false) $ignoradas++;
        }

        Log::info('Notificação do webhook PIX processada.', [
            'txids_recebidos' => $txids->count(),
            'txids_conhecidos' => $cobrancas->count(),
            'confirmadas' => $confirmadas,
            'ignoradas' => $ignoradas,
            'erros' => $erros,
        ]);

        // Falhas transitórias retornam erro para o PSP poder reenviar a notificação.
        return response()->json([
            'recebido' => true,
            'confirmadas' => $confirmadas,
            'ignoradas' => $ignoradas,
        ], $erros > 0 ? 503 : 200);
    }
}
