<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Services\ArmazemService;
use App\Services\GiPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Categorias das atividades.
 *
 * A categoria e opcional na atividade, mas uma vez usada nao pode ser excluida nem
 * desativada: a atividade ficaria apontando para algo que sumiu, ou classificada por
 * uma categoria que a tela de cadastro nao oferece mais.
 */
class CategoriaController
{
    public function index(Request $request, ArmazemService $armazem): View
    {
        return view('categorias.index', ['estadoTabela' => $armazem->recuperar('categorias', $request)]);
    }

    public function dados(Request $request, ArmazemService $armazem): JsonResponse
    {
        $query = Categoria::query()->withCount(['atividades' => fn ($consulta) => $consulta->withTrashed()]);
        $total = (clone $query)->count();
        $busca = trim((string) $request->input('search.value', ''));

        if ($busca !== '') {
            $query->where(function ($consulta) use ($busca): void {
                $consulta->where('nome', 'like', "%{$busca}%");
                if (ctype_digit($busca)) $consulta->orWhere('id', (int) $busca);
            });
        }

        $filtrados = (clone $query)->count();
        $colunas = ['id', 'nome', 'atividades_count', 'ativo', 'created_at', 'updated_at'];
        $coluna = $colunas[(int) $request->input('order.0.column', 0)] ?? 'id';
        $direcao = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $inicio = max(0, (int) $request->input('start', 0));
        $tamanho = min(100, max(1, (int) $request->input('length', 10)));
        $armazem->salvar('categorias', $request, intdiv($inicio, $tamanho) + 1, $busca, $tamanho);
        $permissoes = app(GiPermissionService::class);

        $dados = $query->orderBy($coluna, $direcao)->skip($inicio)->take($tamanho)->get()->map(
            fn (Categoria $categoria): array => [
                'id' => $categoria->id,
                'nome' => e($categoria->nome),
                'atividades_count' => $categoria->atividades_count,
                'ativo' => view('categorias.partials.status', ['categoria' => $categoria])->render(),
                'created_at' => $categoria->created_at?->format('d/m/Y H:i') ?? '—',
                'updated_at' => $categoria->updated_at?->format('d/m/Y H:i') ?? '—',
                'acoes' => view('categorias.partials.acoes', ['categoria' => $categoria, 'permissoes' => $permissoes])->render(),
            ],
        );

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtrados,
            'data' => $dados,
        ]);
    }

    public function create(GiPermissionService $permissoes): View
    {
        return view('categorias.create', ['permissoes' => $permissoes]);
    }

    public function store(Request $request, GiPermissionService $permissoes): RedirectResponse
    {
        Categoria::create($this->validar($request, null, $permissoes));

        return redirect()->route('categorias.index')->with('status', 'Categoria cadastrada com sucesso.');
    }

    public function show(Categoria $categoria): View
    {
        $categoria->loadCount(['atividades' => fn ($consulta) => $consulta->withTrashed()]);

        return view('categorias.show', compact('categoria'));
    }

    public function edit(Categoria $categoria, GiPermissionService $permissoes): View
    {
        return view('categorias.edit', compact('categoria') + ['permissoes' => $permissoes]);
    }

    public function update(Request $request, Categoria $categoria, GiPermissionService $permissoes): RedirectResponse
    {
        $dados = $this->validar($request, $categoria, $permissoes);

        if (array_key_exists('ativo', $dados) && ! $dados['ativo'] && $categoria->temAtividades()) {
            return back()->withInput()->withErrors(['ativo' => $this->motivoDoBloqueio($categoria, 'desativada')]);
        }

        $categoria->update($dados);

        return redirect()->route('categorias.index')->with('status', 'Categoria atualizada com sucesso.');
    }

    /** Liga e desliga a categoria direto na listagem, sem passar pelo formulario. */
    public function alternar(Categoria $categoria): JsonResponse
    {
        if ($categoria->ativo && $categoria->temAtividades()) {
            return response()->json(['message' => $this->motivoDoBloqueio($categoria, 'desativada')], 409);
        }

        $categoria->update(['ativo' => ! $categoria->ativo]);

        return response()->json(['message' => 'Categoria '.($categoria->ativo ? 'ativada' : 'desativada').' com sucesso.']);
    }

    public function destroy(Categoria $categoria): JsonResponse
    {
        if ($categoria->temAtividades()) {
            return response()->json(['message' => $this->motivoDoBloqueio($categoria, 'excluída')], 409);
        }

        $categoria->delete();

        return response()->json(['message' => 'Categoria excluída com sucesso.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?Categoria $categoria, GiPermissionService $permissoes): array
    {
        $regras = ['nome' => ['required', 'string', 'max:255', Rule::unique('categorias', 'nome')->ignore($categoria)]];

        // O status so entra quando o perfil pode mexer nele: sem a permissao o campo nem
        // aparece na tela, e aceita-lo aqui deixaria a regra valendo so no HTML.
        if ($permissoes->permite('categorias.ativar_desativar')) {
            $regras['ativo'] = ['required', 'boolean'];
        }

        // Mensagens escritas aqui porque o projeto nao tem arquivos de traducao: sem
        // isto a tela mostraria "validation.unique" para quem repete um nome.
        return $request->validate($regras, [
            'nome.required' => 'Informe o nome da categoria.',
            'nome.max' => 'O nome da categoria deve ter no máximo 255 caracteres.',
            'nome.unique' => 'Já existe uma categoria com este nome.',
            'ativo.required' => 'Informe o status da categoria.',
            'ativo.boolean' => 'O status da categoria é inválido.',
        ]);
    }

    private function motivoDoBloqueio(Categoria $categoria, string $acao): string
    {
        $total = $categoria->atividades()->withTrashed()->count();

        return "Esta categoria está vinculada a {$total} atividade".($total === 1 ? '' : 's')
            ." e não pode ser {$acao}.";
    }
}
