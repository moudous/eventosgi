<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\InscricaoSubmissaoTrabalho;
use App\Models\Submissao;
use App\Services\ArmazemService;
use App\Services\GiPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubmissaoController
{
    public function index(Request $request, ArmazemService $armazem): View
    {
        return view('submissoes.index', [
            'estadoTabela' => $armazem->recuperar('submissoes', $request),
            'eventosFiltro' => Evento::withTrashed()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function dados(Request $request, ArmazemService $armazem): JsonResponse
    {
        $query = Submissao::query()->with('evento')->withCount('trabalhos');
        $total = (clone $query)->count();
        $filtroEvento = max(0, (int) $request->input('filtro_evento', 0));
        if ($filtroEvento > 0) $query->where('evento_id', $filtroEvento);
        $busca = trim((string) $request->input('search.value', ''));

        if ($busca !== '') {
            $query->where(function ($consulta) use ($busca): void {
                $consulta->where('titulo', 'like', "%{$busca}%")
                    ->orWhereHas('evento', fn ($evento) => $evento->where('nome', 'like', "%{$busca}%"));
                if (ctype_digit($busca)) $consulta->orWhere('id', (int) $busca);
            });
        }

        $filtrados = (clone $query)->count();
        $colunas = ['id', 'evento_id', 'titulo', 'data_inicio', 'data_fim', 'ativo', 'trabalhos_count', 'created_at'];
        $coluna = $colunas[(int) $request->input('order.0.column', 0)] ?? 'id';
        $direcao = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $inicio = max(0, (int) $request->input('start', 0));
        $tamanho = min(100, max(1, (int) $request->input('length', 10)));
        $armazem->salvar('submissoes', $request, intdiv($inicio, $tamanho) + 1, $busca, $tamanho, [
            'filtro_evento' => $filtroEvento,
        ]);
        $permissoes = app(GiPermissionService::class);

        $dados = $query->orderBy($coluna, $direcao)->skip($inicio)->take($tamanho)->get()
            ->map(fn (Submissao $submissao): array => [
                'id' => $submissao->id,
                'evento' => e($submissao->evento?->nome ?? '—'),
                'titulo' => e($submissao->titulo),
                'data_inicio' => $submissao->data_inicio?->format('d/m/Y H:i') ?? '—',
                'data_fim' => $submissao->data_fim?->format('d/m/Y H:i') ?? '—',
                'ativo' => '<span class="badge '.($submissao->ativo ? 'text-bg-success' : 'text-bg-secondary').'">'.($submissao->ativo ? 'Ativa' : 'Inativa').'</span>',
                'inscricoes_count' => $submissao->trabalhos_count,
                'created_at' => $submissao->created_at?->format('d/m/Y H:i') ?? '—',
                'acoes' => view('submissoes.partials.acoes', [
                    'submissao' => $submissao,
                    'permissoes' => $permissoes,
                ])->render(),
            ]);

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtrados,
            'data' => $dados,
        ]);
    }

    public function create(): View
    {
        return view('submissoes.form', [
            'submissao' => new Submissao(),
            'eventos' => Evento::query()->where('ativo', true)->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $submissao = Submissao::create($this->validar($request));

        return redirect()->route('submissoes.edit', $submissao)->with('status', 'Submissão cadastrada com sucesso.');
    }

    public function edit(Submissao $submissao): View
    {
        return view('submissoes.form', [
            'submissao' => $submissao,
            'eventos' => Evento::query()->where('ativo', true)->orWhereKey($submissao->evento_id)->orderBy('nome')->get(),
        ]);
    }

    public function update(Request $request, Submissao $submissao): RedirectResponse
    {
        $submissao->update($this->validar($request));

        return redirect()->route('submissoes.edit', $submissao)->with('status', 'Submissão atualizada com sucesso.');
    }

    public function alternar(Submissao $submissao): JsonResponse
    {
        $submissao->update(['ativo' => ! $submissao->ativo]);

        return response()->json(['message' => 'Submissão '.($submissao->ativo ? 'ativada' : 'desativada').' com sucesso.']);
    }

    public function destroy(Submissao $submissao): JsonResponse
    {
        $total = $submissao->trabalhos()->count();
        $submissao->delete();

        return response()->json([
            'message' => 'Submissão excluída com sucesso.'.($total ? " {$total} trabalho(s) vinculado(s) também foram removidos." : ''),
        ]);
    }

    public function inscritos(Request $request, Submissao $submissao, ArmazemService $armazem): View
    {
        $submissao->atualizarStatusDoPrazo();

        return view('submissoes.inscritos', [
            'submissao' => $submissao,
            'estadoTabela' => $armazem->recuperar('submissoes.inscritos.'.$submissao->id, $request),
        ]);
    }

    public function inscritosDados(Request $request, Submissao $submissao, ArmazemService $armazem): JsonResponse
    {
        $submissao->atualizarStatusDoPrazo();
        $permissoes = app(GiPermissionService::class);
        $podeAlterarStatus = $permissoes->permite('submissoes.trabalhos.alterar_status');
        $query = $submissao->trabalhos()->withTrashed()->with(['autores', 'inscricao']);
        $total = (clone $query)->count();
        $busca = trim((string) $request->input('search.value', ''));

        if ($busca !== '') {
            $query->where(function ($consulta) use ($busca): void {
                $consulta->where('titulo_trabalho', 'like', "%{$busca}%")
                    ->orWhereHas('inscricao', fn ($inscrito) => $inscrito->where('email', 'like', "%{$busca}%"))
                    ->orWhere('situacao', 'like', "%{$busca}%")
                    ->orWhereHas('autores', fn ($autores) => $autores->where('nome', 'like', "%{$busca}%"));
            });
        }

        $tabelaTrabalhos = 'inscritos_submissao_trabalhos';
        $filtrados = (clone $query)->count();
        $colunas = [
            $tabelaTrabalhos.'.id',
            $tabelaTrabalhos.'.titulo_trabalho',
            'inscritos_submissao.email',
            $tabelaTrabalhos.'.id',
            $tabelaTrabalhos.'.status',
            $tabelaTrabalhos.'.nota',
            $tabelaTrabalhos.'.situacao',
            $tabelaTrabalhos.'.updated_at',
        ];
        $coluna = $colunas[(int) $request->input('order.0.column', 0)] ?? $tabelaTrabalhos.'.id';
        $direcao = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $inicio = max(0, (int) $request->input('start', 0));
        $tamanho = min(100, max(1, (int) $request->input('length', 10)));
        $armazem->salvar(
            'submissoes.inscritos.'.$submissao->id,
            $request,
            intdiv($inicio, $tamanho) + 1,
            $busca,
            $tamanho,
        );

        $dados = $query->orderBy($coluna, $direcao)->skip($inicio)->take($tamanho)->get()
            ->map(fn (InscricaoSubmissaoTrabalho $trabalho): array => [
                'id' => $trabalho->id,
                'titulo_trabalho' => e($trabalho->titulo_trabalho),
                'email' => e($trabalho->inscricao?->email ?? '—'),
                'autores' => e($trabalho->autores->pluck('nome')->implode(', ')),
                'status' => $trabalho->trashed()
                    ? '<span class="badge text-bg-danger">Apagado</span>'
                    : $this->rotuloStatus($trabalho->status, $podeAlterarStatus, $submissao, $trabalho),
                'nota' => $trabalho->nota ?? '—',
                'situacao' => e($trabalho->situacao ?: '—'),
                'updated_at' => $trabalho->updated_at?->format('d/m/Y H:i') ?? '—',
                'acoes' => view('submissoes.partials.acoes-trabalho', [
                    'submissao' => $submissao,
                    'trabalho' => $trabalho,
                    'permissoes' => $permissoes,
                ])->render(),
            ]);

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtrados,
            'data' => $dados,
        ]);
    }

    public function alterarStatus(Request $request, Submissao $submissao, int $trabalho): JsonResponse
    {
        $status = $request->validate([
            'status' => ['required', 'string', 'in:rascunho,submetido,avaliado'],
        ])['status'];
        $registro = $submissao->trabalhos()->whereKey($trabalho)->firstOrFail();
        $registro->update(['status' => $status]);

        return response()->json(['message' => 'Status alterado para '.$this->nomeStatus($status).'.']);
    }

    public function restaurar(Submissao $submissao, int $trabalho): JsonResponse
    {
        $registro = $submissao->trabalhos()->onlyTrashed()->whereKey($trabalho)->firstOrFail();
        $registro->restore();

        return response()->json(['message' => 'Trabalho restaurado com sucesso.']);
    }

    public function excluirDefinitivamente(Submissao $submissao, int $trabalho): JsonResponse
    {
        $registro = $submissao->trabalhos()->onlyTrashed()->whereKey($trabalho)->firstOrFail();
        $titulo = $registro->titulo_trabalho;
        $registro->forceDelete();

        return response()->json(['message' => "O trabalho \"{$titulo}\" foi excluído definitivamente."]);
    }

    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'evento_id' => ['required', 'integer', 'exists:eventos,id'],
            'titulo' => ['required', 'string', 'max:255'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['required', 'date', 'after:data_inicio'],
            'ativo' => ['required', 'boolean'],
            'qtde_resumo' => ['required', 'integer', 'min:1', 'max:100000'],
            'qtde_autores' => ['required', 'integer', 'min:1', 'max:100'],
            'modelo_trabalho' => ['nullable', 'string', 'max:1000000'],
            'personalizacao' => ['required', 'array:alterar_cor_fundo_pagina,cor_fundo_pagina'],
            'personalizacao.alterar_cor_fundo_pagina' => ['required', 'boolean'],
            'personalizacao.cor_fundo_pagina' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'evento_id.required' => 'Selecione o evento.',
            'titulo.required' => 'Informe o título da submissão.',
            'data_inicio.required' => 'Informe a data inicial.',
            'data_fim.required' => 'Informe a data final.',
            'data_fim.after' => 'A data final precisa ser posterior à data inicial.',
            'qtde_resumo.required' => 'Informe a quantidade máxima de caracteres do resumo.',
            'qtde_resumo.integer' => 'A quantidade de caracteres do resumo deve ser um número inteiro.',
            'qtde_resumo.min' => 'A quantidade de caracteres do resumo deve ser maior que zero.',
            'qtde_resumo.max' => 'A quantidade de caracteres do resumo não pode ultrapassar 100.000.',
            'qtde_autores.required' => 'Informe a quantidade máxima de autores.',
            'qtde_autores.integer' => 'A quantidade de autores deve ser um número inteiro.',
            'qtde_autores.min' => 'A submissão deve permitir pelo menos um autor.',
            'qtde_autores.max' => 'A quantidade de autores não pode ultrapassar 100.',
        ]);

        $dados['modelo_trabalho'] = $this->limparHtml((string) ($dados['modelo_trabalho'] ?? '')) ?: null;
        $dados['personalizacao']['alterar_cor_fundo_pagina'] = (bool) $dados['personalizacao']['alterar_cor_fundo_pagina'];
        $dados['personalizacao']['cor_fundo_pagina'] = strtolower($dados['personalizacao']['cor_fundo_pagina']);

        return $dados;
    }

    private function limparHtml(string $html): string
    {
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><ul><ol><li><h1><h2><h3><h4><blockquote><table><thead><tbody><tr><th><td>');

        return trim((string) preg_replace_callback('/<([a-z][a-z0-9]*)\b([^>]*)>/i', function (array $partes): string {
            preg_match('/\bclass=["\'][^"\']*\b(ql-align-(?:center|right|justify))\b[^"\']*["\']/i', $partes[2], $classe);

            return '<'.strtolower($partes[1]).(isset($classe[1]) ? ' class="'.strtolower($classe[1]).'"' : '').'>';
        }, $html));
    }

    private function rotuloStatus(string $status, bool $podeAlterar = false, ?Submissao $submissao = null, ?InscricaoSubmissaoTrabalho $trabalho = null): string
    {
        $rotulos = $this->statusDisponiveis();
        [$texto, $cor] = $rotulos[$status] ?? [ucfirst($status), 'secondary'];

        if ($podeAlterar && $submissao && $trabalho) {
            $opcoes = collect($rotulos)->map(fn (array $dados, string $valor): string => '<option value="'.e($valor).'"'.($valor === $status ? ' selected' : '').'>'.e($dados[0]).'</option>')->implode('');

            return '<select class="form-select form-select-sm status-trabalho-select" style="min-width:130px" data-status-url="'.e(route('submissoes.inscritos.alterar-status', [$submissao, $trabalho])).'" data-status-atual="'.e($status).'">'.$opcoes.'</select>';
        }

        return '<span class="badge text-bg-'.$cor.'">'.e($texto).'</span>';
    }

    private function statusDisponiveis(): array
    {
        return ['rascunho' => ['Rascunho', 'warning'], 'submetido' => ['Submetido', 'info'], 'avaliado' => ['Avaliado', 'success']];
    }

    private function nomeStatus(string $status): string
    {
        return $this->statusDisponiveis()[$status][0] ?? ucfirst($status);
    }
}
