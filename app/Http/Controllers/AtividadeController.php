<?php

namespace App\Http\Controllers;

use App\Models\Atividade;
use App\Models\Evento;
use App\Models\HistoricoAtividade;
use App\Models\InscricaoAtividade;
use App\Services\ArmazemService;
use App\Services\FormularioInscricaoService;
use App\Services\GiPermissionService;
use App\Services\PluginWordpressService;
use App\Services\HistoricoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\URL;

class AtividadeController
{
    public function index(Request $request, ArmazemService $armazem): View { return view('atividades.index', ['apagados' => false, 'estadoTabela' => $armazem->recuperar('atividades', $request)]); }
    public function apagados(Request $request, ArmazemService $armazem): View { return view('atividades.index', ['apagados' => true, 'estadoTabela' => $armazem->recuperar('atividades', $request)]); }

    public function dados(Request $request, ArmazemService $armazem): JsonResponse
    {
        $apagados = $request->boolean('apagados');
        $query = ($apagados ? Atividade::onlyTrashed() : Atividade::query())->with(['evento', 'criador:id,nome'])->withCount('inscricoes');
        $total = (clone $query)->count();
        $busca = trim((string) $request->input('search.value', ''));
        if ($busca !== '') $query->where(fn ($q) => $q->where('nome', 'like', "%{$busca}%")->orWhereHas('evento', fn ($e) => $e->where('nome', 'like', "%{$busca}%"))->orWhereHas('criador', fn ($u) => $u->where('nome', 'like', "%{$busca}%")));
        $filtrados = (clone $query)->count();
        $colunas = ['id', 'nome', 'evento_id', 'modalidade', 'data_inicio', 'data_fim', 'inscricoes_count', 'ativo', 'criado_por', 'created_at', 'updated_at', 'deleted_at'];
        $coluna = $colunas[(int) $request->input('order.0.column', 0)] ?? 'id';
        if ($coluna === 'criado_por') $coluna = \App\Models\Usuario::select('nome')->whereColumn('usuarios.id', 'atividades.criado_por')->limit(1);
        $direcao = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $inicio = max(0, (int) $request->input('start', 0));
        $tamanho = min(100, max(1, (int) $request->input('length', 10)));
        $armazem->salvar('atividades', $request, intdiv($inicio, $tamanho) + 1, $busca, $tamanho);
        $permissoes = app(GiPermissionService::class);
        $dados = $query->orderBy($coluna, $direcao)->skip($inicio)->take($tamanho)->get()->map(fn (Atividade $atividade) => [
            'inscricoes_count' => $atividade->inscricoes_count, 'id' => $atividade->id, 'nome' => e($atividade->nome), 'evento' => e($atividade->evento?->nome ?? '—'),
            'modalidade' => $atividade->modalidade ? strtoupper($atividade->modalidade) : '—',
            'data_inicio' => $atividade->data_inicio?->format('d/m/Y H:i') ?? '—', 'data_fim' => $atividade->data_fim?->format('d/m/Y H:i') ?? '—',
            'ativo' => view('eventos.partials.status', ['evento' => $atividade])->render(), 'criado_por' => e($atividade->criador?->nome ?? 'Usuário não encontrado'),
            'created_at' => $atividade->created_at?->format('d/m/Y H:i') ?? '—', 'updated_at' => $atividade->updated_at?->format('d/m/Y H:i') ?? '—',
            'deleted_at' => $atividade->deleted_at?->format('d/m/Y H:i') ?? '—',
            'acoes' => view('atividades.partials.acoes', ['atividade' => $atividade, 'apagados' => $apagados, 'permissoes' => $permissoes])->render(),
        ]);
        return response()->json(['draw' => (int) $request->input('draw'), 'recordsTotal' => $total, 'recordsFiltered' => $filtrados, 'data' => $dados]);
    }

    public function create(): View { return view('atividades.create', ['eventos' => Evento::query()->where('ativo', true)->orderBy('nome')->get()]); }
    public function store(Request $request, HistoricoService $historico): RedirectResponse
    {
        $dados = $this->validar($request); $dados['criado_por'] = (int) $request->session()->get('gi_context.usuario.id');
        $atividade = Atividade::create($dados); $historico->atividade($atividade, 'Atividade Inserida', $atividade->only(['id', 'nome', 'ativo', 'criado_por', 'evento_id', 'modalidade', 'data_inicio', 'data_fim']), $request);
        return redirect()->route('atividades.index')->with('status', 'Atividade cadastrada com sucesso.');
    }
    public function show(Atividade $atividade): View { $atividade->load('evento'); return view('atividades.show', compact('atividade')); }
    public function edit(Atividade $atividade): View { return view('atividades.edit', ['atividade' => $atividade, 'eventos' => Evento::query()->where('ativo', true)->orWhereKey($atividade->evento_id)->orderBy('nome')->get()]); }
    public function formulario(Atividade $atividade): View { return view('atividades.formulario', ['atividade' => $atividade]); }
    public function salvarFormulario(Request $request, Atividade $atividade): RedirectResponse
    {
        $dados = $request->validate(['formulario' => ['required', 'json']]);
        $config = json_decode($dados['formulario'], true);
        $validator = validator(['config' => $config], [
            'config' => ['required', 'array'],
            'config.limitar_inscricoes' => ['sometimes', 'boolean'],
            'config.limite_inscricoes' => ['required_if:config.limitar_inscricoes,true', 'nullable', 'integer', 'min:1'],
            'config.mensagem_vagas_esgotadas' => ['nullable', 'string', 'max:2000'],
        ], [
            'config.limite_inscricoes.required_if' => 'Informe a quantidade de inscrições disponíveis ao ativar o limite.',
            'config.limite_inscricoes.integer' => 'A quantidade de inscrições deve ser um número inteiro.',
            'config.limite_inscricoes.min' => 'A quantidade de inscrições deve ser pelo menos 1.',
        ]);
        if ($validator->fails()) return back()->withErrors(['formulario' => $validator->errors()->first()])->withInput();
        $config['mensagem_vagas_esgotadas'] = trim($config['mensagem_vagas_esgotadas'] ?? '') ?: Atividade::MENSAGEM_VAGAS_ESGOTADAS;
        $atividade->update(['formulario' => $config]);
        return redirect()->route('atividades.formulario', $atividade)->with('status', 'Formulário salvo com sucesso.');
    }
    public function preview(Request $request, Atividade $atividade): View
    {
        abort_unless($atividade->formulario, 404);
        return view('atividades.formulario-publico', ['atividade' => $atividade, 'config' => $atividade->formulario]);
    }
    public function previewRedirect(Atividade $atividade): RedirectResponse
    {
        return redirect()->to(URL::temporarySignedRoute('atividades.formulario.preview', now()->addMinutes(30), $atividade));
    }
    public function inscrever(Request $request, Atividade $atividade, FormularioInscricaoService $servico): RedirectResponse
    {
        $resultado = $servico->inscrever($request, $atividade);
        if ($resultado['sucesso']) return back()->with('status', $resultado['mensagem']);
        if ($resultado['motivo'] === 'esgotado') return back()->with('vagas_esgotadas', $resultado['mensagem']);
        abort(403, $resultado['mensagem']);
    }
    public function inscricoes(Atividade $atividade): View { return view('atividades.inscricoes', ['atividade' => $atividade, 'inscricoes' => InscricaoAtividade::where('atividade_id', $atividade->id)->latest()->paginate(20)]); }
    public function exportarInscricoes(Atividade $atividade, string $formato)
    {
        return app(\App\Services\InscricoesExportService::class)->download($atividade, $formato);
    }
    public function baixarPlugin(PluginWordpressService $plugin) { return $plugin->download(); }
    public function previewLink(Atividade $atividade): JsonResponse { return response()->json(['url' => URL::temporarySignedRoute('atividades.formulario.preview', now()->addMinutes(30), $atividade)]); }
    public function update(Request $request, Atividade $atividade, HistoricoService $historico): RedirectResponse
    {
        $campos = ['nome', 'ativo', 'evento_id', 'modalidade', 'data_inicio', 'data_fim'];
        $antes = $atividade->only($campos); $atividade->update($this->validar($request));
        $mudancas = $historico->alteracoes($antes, $atividade->only($campos));
        if ($mudancas !== []) $historico->atividade($atividade, 'Atividade alterada', $mudancas, $request);
        return redirect()->route('atividades.index')->with('status', 'Atividade atualizada com sucesso.');
    }
    public function destroy(Request $request, Atividade $atividade, HistoricoService $historico): JsonResponse { $historico->atividade($atividade, 'Atividade excluída', $atividade->only(['id','nome','ativo','evento_id']), $request); $atividade->delete(); return response()->json(['message' => 'Atividade excluída com sucesso.']); }
    public function restore(Request $request, int $atividade, HistoricoService $historico): JsonResponse { $item=Atividade::onlyTrashed()->findOrFail($atividade); $item->restore(); $historico->atividade($item, 'Atividade restaurada', $item->only(['id','nome','ativo','evento_id']), $request); return response()->json(['message'=>'Atividade restaurada com sucesso.']); }
    public function forceDestroy(Request $request, int $atividade, HistoricoService $historico): JsonResponse { $item=Atividade::onlyTrashed()->findOrFail($atividade); $historico->atividade($item, 'Atividade excluída definitivamente', $item->only(['id','nome','ativo','evento_id']), $request); $item->forceDelete(); return response()->json(['message'=>'Atividade excluída definitivamente.']); }
    public function historico(Request $request, int $atividade): JsonResponse
    {
        $query=HistoricoAtividade::query()->where('atividade_id',$atividade); $total=$query->count(); $inicio=max(0,(int)$request->input('start')); $tamanho=min(100,max(1,(int)$request->input('length',10)));
        $dados=$query->latest('data_hora')->latest('id')->skip($inicio)->take($tamanho)->get()->values()->map(fn($item,$i)=>['numero'=>$total-$inicio-$i,'historico'=>e($item->historico),'usuario'=>$item->usuario??'—','dados'=>view('partials.historico-dados',['dados'=>$item->dados??[]])->render(),'data_hora'=>$item->data_hora?->format('d/m/Y H:i:s')??'—']);
        return response()->json(['draw'=>(int)$request->input('draw'),'recordsTotal'=>$total,'recordsFiltered'=>$total,'data'=>$dados]);
    }
    private function validar(Request $request): array { return $request->validate(['nome'=>['required','string','max:255'],'ativo'=>['required','boolean'],'evento_id'=>['required','integer','exists:eventos,id'],'modalidade'=>['nullable','in:ead,presencial'],'data_inicio'=>['nullable','date'],'data_fim'=>['nullable','date']]); }
}
