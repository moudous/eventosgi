<?php

namespace App\Http\Controllers;

use App\Models\Avaliador;
use App\Models\Usuario;
use App\Services\ArmazemService;
use App\Services\GiPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AvaliadorController
{
    public function index(Request $request, ArmazemService $armazem): View
    {
        return view('avaliadores.index', [
            'estadoTabela' => $armazem->recuperar('avaliadores', $request),
        ]);
    }

    public function dados(Request $request, ArmazemService $armazem): JsonResponse
    {
        $query = Avaliador::query()->with('usuario')->withCount('trabalhos');
        $total = (clone $query)->count();
        $busca = trim((string) $request->input('search.value', ''));
        if ($busca !== '') {
            $query->where(function ($consulta) use ($busca): void {
                $consulta->where('nome', 'like', "%{$busca}%")
                    ->orWhereHas('usuario', fn ($usuario) => $usuario
                        ->where('nome', 'like', "%{$busca}%")
                        ->orWhere('email', 'like', "%{$busca}%"));
                if (ctype_digit($busca)) $consulta->orWhere('id', (int) $busca);
            });
        }

        $filtrados = (clone $query)->count();
        $colunas = ['id', 'nome', 'usuario_id', 'trabalhos_count', 'created_at', 'updated_at'];
        $coluna = $colunas[(int) $request->input('order.0.column', 0)] ?? 'id';
        $direcao = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $inicio = max(0, (int) $request->input('start', 0));
        $tamanho = min(100, max(1, (int) $request->input('length', 10)));
        $armazem->salvar('avaliadores', $request, intdiv($inicio, $tamanho) + 1, $busca, $tamanho);
        $permissoes = app(GiPermissionService::class);

        $dados = $query->orderBy($coluna, $direcao)->skip($inicio)->take($tamanho)->get()
            ->map(fn (Avaliador $avaliador): array => [
                'id' => $avaliador->id,
                'nome' => e($avaliador->nome),
                'usuario' => view('avaliadores.partials.usuario', ['usuario' => $avaliador->usuario])->render(),
                'trabalhos_count' => $avaliador->trabalhos_count,
                'created_at' => $avaliador->created_at?->format('d/m/Y H:i') ?? '—',
                'updated_at' => $avaliador->updated_at?->format('d/m/Y H:i') ?? '—',
                'acoes' => view('avaliadores.partials.acoes', [
                    'avaliador' => $avaliador,
                    'permissoes' => $permissoes,
                ])->render(),
            ]);

        return response()->json([
            'draw' => (int) $request->input('draw', 0), 'recordsTotal' => $total,
            'recordsFiltered' => $filtrados, 'data' => $dados,
        ]);
    }

    public function create(): View
    {
        return view('avaliadores.create', [
            'avaliador' => new Avaliador(),
            'usuarios' => $this->usuariosDisponiveis(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Avaliador::create($this->validar($request));

        return redirect()->route('avaliadores.index')->with('status', 'Avaliador cadastrado com sucesso.');
    }

    public function show(Avaliador $avaliador): View
    {
        $avaliador->load('usuario')->loadCount('trabalhos');

        return view('avaliadores.show', compact('avaliador'));
    }

    public function edit(Avaliador $avaliador): View
    {
        return view('avaliadores.edit', [
            'avaliador' => $avaliador,
            'usuarios' => $this->usuariosDisponiveis($avaliador),
        ]);
    }

    public function update(Request $request, Avaliador $avaliador): RedirectResponse
    {
        $avaliador->update($this->validar($request, $avaliador));

        return redirect()->route('avaliadores.index')->with('status', 'Avaliador atualizado com sucesso.');
    }

    public function destroy(Avaliador $avaliador): JsonResponse
    {
        $avaliador->delete();

        return response()->json(['message' => 'Avaliador excluído com sucesso. Os trabalhos vinculados ficaram sem avaliador.']);
    }

    private function validar(Request $request, ?Avaliador $avaliador = null): array
    {
        return $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'usuario_id' => [
                'nullable', 'integer', 'exists:usuarios,id',
                Rule::unique('avaliadores', 'usuario_id')->ignore($avaliador),
            ],
        ], [
            'nome.required' => 'Informe o nome completo do avaliador.',
            'nome.max' => 'O nome deve ter no máximo 255 caracteres.',
            'usuario_id.exists' => 'O usuário selecionado não existe.',
            'usuario_id.unique' => 'Este usuário já está cadastrado como avaliador.',
        ]);
    }

    private function usuariosDisponiveis(?Avaliador $avaliador = null)
    {
        return Usuario::query()->where('ativo', true)
            ->where(fn ($query) => $query->whereDoesntHave('avaliador')
                ->when($avaliador, fn ($q) => $q->orWhereKey($avaliador->usuario_id)))
            ->orderBy('nome')->get(['id', 'nome', 'email', 'foto_url']);
    }
}
