<?php

namespace App\Http\Controllers;

use App\Models\Atividade;
use App\Models\Evento;
use App\Models\PixCobranca;
use App\Services\GiPermissionService;
use App\Services\RecebimentosPixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecebimentoPixController
{
    public function index(?Atividade $atividade = null): View
    {
        if ($atividade) abort_unless($atividade->temPagamentoPix(), 404);
        $atividades = Atividade::query()->whereNotNull('formulario')->orderBy('nome')->get(['id', 'nome', 'evento_id', 'formulario'])
            ->filter->temPagamentoPix()->values();

        return view('recebimentos.index', [
            'atividadeSelecionada' => $atividade,
            'eventos' => Evento::query()->whereIn('id', $atividades->pluck('evento_id')->unique())->orderBy('nome')->get(['id', 'nome']),
            'atividades' => $atividades,
        ]);
    }

    public function dados(Request $request, RecebimentosPixService $servico, ?Atividade $atividade = null): JsonResponse
    {
        if ($atividade) abort_unless($atividade->temPagamentoPix(), 404);
        $base = $servico->consulta($request, $atividade);
        $total = (clone $base)->count();
        $servico->aplicarPesquisa($base, (string) $request->input('search.value', ''));
        $filtrados = (clone $base)->count();
        $colunas = ['id', 'pago_em', null, null, 'pagador_nome', 'pagador_documento', null, 'valor', 'txid', null];
        $coluna = $colunas[(int) $request->input('order.0.column', 1)] ?? 'pago_em';
        $direcao = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $inicio = max(0, (int) $request->input('start', 0));
        $tamanho = min(100, max(1, (int) $request->input('length', 10)));
        $podeVisualizar = app(GiPermissionService::class)->permite(
            $atividade ? 'atividades.recebimentos.visualizar' : 'recebimentos.visualizar',
            $request,
        );
        $dados = $base->orderBy($coluna ?: 'pago_em', $direcao)->orderByDesc('id')->skip($inicio)->take($tamanho)->get()
            ->map(function (PixCobranca $cobranca) use ($atividade, $podeVisualizar): array {
                $inscricao = $cobranca->inscricao;
                return [
                    'id' => $cobranca->id,
                    'data_hora' => $cobranca->pago_em?->format('d/m/Y H:i:s') ?? '—',
                    'evento' => e($inscricao?->atividade?->evento?->nome ?? '—'),
                    'atividade' => e($inscricao?->atividade?->nome ?? '—'),
                    'pagador' => e($cobranca->pagador_nome ?: $inscricao?->participante?->nome ?: 'Não informado'),
                    'documento' => e(RecebimentosPixService::formatarDocumento($cobranca->pagador_documento ?: $inscricao?->participante?->cpf) ?: '—'),
                    'email' => e($inscricao?->participante_email ?: '—'),
                    'valor' => 'R$ '.number_format((float) $cobranca->valor, 2, ',', '.'),
                    'txid' => '<code>'.e($cobranca->txid).'</code>',
                    'acoes' => $podeVisualizar
                        ? '<a class="btn btn-sm btn-outline-primary" href="'.e($atividade ? route('atividades.recebimentos.show', [$atividade, $cobranca]) : route('recebimentos.show', $cobranca)).'" title="Visualizar recebimento" aria-label="Visualizar recebimento"><i class="bi bi-eye-fill"></i></a>'
                        : '<span class="text-muted">—</span>',
                ];
            });

        return response()->json(['draw' => (int) $request->input('draw'), 'recordsTotal' => $total, 'recordsFiltered' => $filtrados, 'data' => $dados]);
    }

    public function show(PixCobranca $recebimento): View
    {
        abort_unless($recebimento->pago_em, 404);
        $recebimento->load(['inscricao.atividade.evento', 'inscricao.participante']);
        return view('recebimentos.show', compact('recebimento'));
    }

    public function showAtividade(Atividade $atividade, PixCobranca $recebimento): View
    {
        abort_unless($atividade->temPagamentoPix(), 404);
        abort_unless($recebimento->pago_em && (int) $recebimento->inscricao?->atividade_id === $atividade->id, 404);
        $recebimento->load(['inscricao.atividade.evento', 'inscricao.participante']);

        return view('recebimentos.show', compact('recebimento'));
    }

    public function exportar(Request $request, RecebimentosPixService $servico, ?Atividade $atividade = null)
    {
        if ($atividade) abort_unless($atividade->temPagamentoPix(), 404);
        return $servico->exportar($request, $atividade);
    }
}
