<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\HistoricoEvento;
use App\Services\ArmazemService;
use App\Services\GiPermissionService;
use App\Services\HistoricoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventoController
{
    public function index(Request $request, ArmazemService $armazem): View
    {
        return view('eventos.index', [
            'apagados' => false,
            'estadoTabela' => $armazem->recuperar('eventos', $request),
        ]);
    }

    public function apagados(Request $request, ArmazemService $armazem): View
    {
        return view('eventos.index', [
            'apagados' => true,
            'estadoTabela' => $armazem->recuperar('eventos', $request),
        ]);
    }

    public function dados(Request $request, ArmazemService $armazem): JsonResponse
    {
        $apagados = $request->boolean('apagados');
        $query = $apagados ? Evento::onlyTrashed() : Evento::query();
        $total = (clone $query)->count();
        $busca = trim((string) $request->input('search.value', ''));

        if ($busca !== '') {
            $query->where(function ($query) use ($busca): void {
                $query->where('nome', 'like', "%{$busca}%");
                if (ctype_digit($busca)) {
                    $query->orWhere('id', (int) $busca);
                }
            });
        }

        $filtrados = (clone $query)->count();
        $colunas = ['id', 'nome', 'ativo', 'created_at', 'updated_at', 'deleted_at'];
        $coluna = $colunas[(int) $request->input('order.0.column', 0)] ?? 'id';
        $direcao = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $inicio = max(0, (int) $request->input('start', 0));
        $tamanho = min(100, max(1, (int) $request->input('length', 10)));
        $armazem->salvar('eventos', $request, intdiv($inicio, $tamanho) + 1, $busca, $tamanho);
        $permissoes = app(GiPermissionService::class);

        $dados = $query->orderBy($coluna, $direcao)->skip($inicio)->take($tamanho)->get()->map(
            fn (Evento $evento): array => [
                'id' => $evento->id,
                'nome' => e($evento->nome),
                'ativo' => view('eventos.partials.status', ['evento' => $evento])->render(),
                'created_at' => $evento->created_at?->format('d/m/Y H:i') ?? '—',
                'updated_at' => $evento->updated_at?->format('d/m/Y H:i') ?? '—',
                'deleted_at' => $evento->deleted_at?->format('d/m/Y H:i') ?? '—',
                'acoes' => view('eventos.partials.acoes', [
                    'evento' => $evento,
                    'apagados' => $apagados,
                    'permissoes' => $permissoes,
                ])->render(),
            ],
        );

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtrados,
            'data' => $dados,
        ]);
    }

    public function create(): View
    {
        return view('eventos.create');
    }

    public function store(Request $request, HistoricoService $historico): RedirectResponse
    {
        $evento = Evento::create($this->validar($request));
        $historico->evento($evento, 'Evento Inserido', $evento->only(['id', 'nome', 'ativo']), $request);

        return redirect()->route('eventos.index')->with('status', 'Evento cadastrado com sucesso.');
    }

    public function show(Evento $evento): View
    {
        return view('eventos.show', compact('evento'));
    }

    public function edit(Evento $evento): View
    {
        return view('eventos.edit', compact('evento'));
    }

    public function update(Request $request, Evento $evento, HistoricoService $historico): RedirectResponse
    {
        $antes = $evento->only(['nome', 'ativo']);
        $evento->update($this->validar($request, $evento));
        $alteracoes = $historico->alteracoes($antes, $evento->only(['nome', 'ativo']));
        if ($alteracoes !== []) $historico->evento($evento, 'Evento alterado', $alteracoes, $request);

        return redirect()->route('eventos.index')->with('status', 'Evento atualizado com sucesso.');
    }

    public function destroy(Request $request, Evento $evento, HistoricoService $historico): JsonResponse
    {
        $historico->evento($evento, 'Evento excluído', $evento->only(['id', 'nome', 'ativo']), $request);
        $evento->delete();

        return response()->json(['message' => 'Evento excluído com sucesso.']);
    }

    public function restore(Request $request, int $evento, HistoricoService $historico): JsonResponse
    {
        $registro = Evento::onlyTrashed()->findOrFail($evento);
        $registro->restore();
        $historico->evento($registro, 'Evento restaurado', $registro->only(['id', 'nome', 'ativo']), $request);

        return response()->json(['message' => 'Evento restaurado com sucesso.']);
    }

    public function forceDestroy(Request $request, int $evento, HistoricoService $historico): JsonResponse
    {
        $registro = Evento::onlyTrashed()->findOrFail($evento);
        $historico->evento($registro, 'Evento excluído definitivamente', $registro->only(['id', 'nome', 'ativo']), $request);
        $registro->forceDelete();

        return response()->json(['message' => 'Evento excluído definitivamente.']);
    }

    public function historico(Request $request, int $evento): JsonResponse
    {
        $query = HistoricoEvento::query()->where('evento_id', $evento);
        $total = $query->count();
        $inicio = max(0, (int) $request->input('start', 0));
        $tamanho = min(100, max(1, (int) $request->input('length', 10)));
        $dados = $query->latest('data_hora')->latest('id')->skip($inicio)->take($tamanho)->get()->values()->map(fn ($item, $indice) => [
            'numero' => $total - $inicio - $indice,
            'historico' => e($item->historico),
            'usuario' => $item->usuario ?? '—',
            'dados' => view('partials.historico-dados', ['dados' => $item->dados ?? []])->render(),
            'data_hora' => $item->data_hora?->format('d/m/Y H:i:s') ?? '—',
        ]);
        return response()->json(['draw' => (int) $request->input('draw'), 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => $dados]);
    }

    private function validar(Request $request, ?Evento $evento = null): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'ativo' => ['required', 'boolean'],
        ]);
    }
}
