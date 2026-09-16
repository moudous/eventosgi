<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\InscricaoSubmissaoTrabalho;
use App\Models\Submissao;
use App\Services\ArmazemService;
use App\Services\ConteudoEditorFormularioService;
use App\Services\GiPermissionService;
use App\Services\SubmissaoResultadoNotificacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SubmissaoController
{
    public function index(Request $request, ArmazemService $armazem): View
    {
        return view('submissoes.index', [
            'estadoTabela' => $armazem->recuperar('submissoes', $request),
            'eventosFiltro' => Evento::withTrashed()->orderByDesc('created_at')->orderByDesc('id')->get(['id', 'nome']),
        ]);
    }

    public function dados(Request $request, ArmazemService $armazem): JsonResponse
    {
        $query = Submissao::query()->with('evento')->withCount(['trabalhos', 'inscricoes']);
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
        if ($submissao->temInscritos()) {
            $total = $submissao->inscricoes()->count();

            return response()->json([
                'message' => "Esta submissão possui {$total} inscrito(s) e não pode ser excluída.",
            ], 409);
        }

        $submissao->delete();

        return response()->json(['message' => 'Submissão excluída com sucesso.']);
    }

    public function inscritos(Request $request, Submissao $submissao, ArmazemService $armazem): View
    {
        $submissao->atualizarStatusDoPrazo();

        return view('submissoes.inscritos', [
            'submissao' => $submissao,
            'estadoTabela' => $armazem->recuperar('submissoes.inscritos.'.$submissao->id, $request),
            'permissoes' => app(GiPermissionService::class),
        ]);
    }

    public function visualizarTrabalho(Submissao $submissao, int $trabalho): View
    {
        $permissoes = app(GiPermissionService::class);
        $registro = $submissao->trabalhos()->withTrashed()->with(['inscricao', 'autores'])->whereKey($trabalho)->firstOrFail();

        return view('submissoes.visualizar-trabalho', [
            'submissao' => $submissao,
            'trabalho' => $registro,
            'podeVerAutores' => $permissoes->permite('submissoes.inscritos.trabalhos.autores'),
        ]);
    }

    public function visualizarEposter(Submissao $submissao, int $trabalho): BinaryFileResponse
    {
        $registro = $submissao->trabalhos()->withTrashed()->whereKey($trabalho)->firstOrFail();
        abort_unless($registro->temEposter(), 404);
        $caminho = storage_path('app/private/eposters/'.$registro->eposter_arquivo);
        abort_unless(is_file($caminho), 404);

        return response()->file($caminho, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="e-poster-trabalho-'.$registro->id.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function inscritosDados(Request $request, Submissao $submissao, ArmazemService $armazem): JsonResponse
    {
        $submissao->atualizarStatusDoPrazo();
        $permissoes = app(GiPermissionService::class);
        $podeAlterarStatus = $permissoes->permite('submissoes.trabalhos.alterar_status');
        $podeVerAutores = $permissoes->permite('submissoes.inscritos.trabalhos.autores');
        $relacionamentos = ['inscricao'];
        if ($podeVerAutores) $relacionamentos[] = 'autores';
        $query = $submissao->trabalhos()->withTrashed()->with($relacionamentos);
        $total = (clone $query)->count();
        $busca = trim((string) $request->input('search.value', ''));

        if ($busca !== '') {
            $query->where(function ($consulta) use ($busca, $podeVerAutores): void {
                $consulta->where('titulo_trabalho', 'like', "%{$busca}%")
                    ->orWhere('situacao', 'like', "%{$busca}%");
                if ($podeVerAutores) {
                    $consulta->orWhereHas('inscricao', fn ($inscrito) => $inscrito->where('email', 'like', "%{$busca}%"))
                        ->orWhereHas('autores', fn ($autores) => $autores->where('nome', 'like', "%{$busca}%"));
                }
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
                'email' => $podeVerAutores ? e($trabalho->inscricao?->email ?? '—') : '—',
                'autores' => $podeVerAutores ? e($trabalho->autores->pluck('nome')->implode(', ')) : '—',
                'status' => $trabalho->trashed()
                    ? '<span class="badge text-bg-danger">Apagado</span>'
                    : $this->rotuloStatus($trabalho->status, $podeAlterarStatus, $submissao, $trabalho),
                'situacao' => $trabalho->status === 'avaliado' ? e($trabalho->situacao ?: '—') : '—',
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

    public function avaliar(Request $request, Submissao $submissao, int $trabalho): JsonResponse
    {
        app(GiPermissionService::class)->exigir('submissoes.avaliar');
        $decisao = $request->validate([
            'situacao' => ['required', 'string', 'in:aprovado,reprovado'],
        ])['situacao'];
        $registro = $submissao->trabalhos()->whereKey($trabalho)->firstOrFail();
        $alterouDecisao = $registro->status !== 'avaliado' || $registro->situacao !== $decisao;
        $registro->update([
            'status' => 'avaliado',
            'situacao' => $decisao,
            'notificacao_resultado_versao' => $alterouDecisao
                ? max(1, (int) $registro->notificacao_resultado_versao + 1)
                : max(1, (int) $registro->notificacao_resultado_versao),
        ]);
        if ($alterouDecisao) {
            $registro->historicos()->create([
                'historico' => 'Situação alterada para '.$decisao,
                'usuario' => $this->usuarioGi($request),
                'dados' => ['situacao' => $decisao],
                'data_hora' => now(),
            ]);
        }

        return response()->json(['message' => 'Trabalho '.($decisao === 'aprovado' ? 'aprovado' : 'reprovado').' com sucesso.']);
    }

    public function alterarStatus(Request $request, Submissao $submissao, int $trabalho): JsonResponse
    {
        $status = $request->validate([
            'status' => ['required', 'string', 'in:rascunho,submetido,avaliado'],
        ])['status'];
        $registro = $submissao->trabalhos()->whereKey($trabalho)->firstOrFail();
        $statusAnterior = $registro->status;
        $registro->update([
            'status' => $status,
            'notificacao_resultado_versao' => $statusAnterior !== $status
                ? max(1, (int) $registro->notificacao_resultado_versao + 1)
                : max(1, (int) $registro->notificacao_resultado_versao),
        ]);
        if ($statusAnterior !== $status) {
            $registro->historicos()->create([
                'historico' => 'Status alterado para '.$this->nomeStatus($status),
                'usuario' => $this->usuarioGi($request),
                'dados' => ['status' => ['antes' => $statusAnterior, 'depois' => $status]],
                'data_hora' => now(),
            ]);
        }

        return response()->json(['message' => 'Status alterado para '.$this->nomeStatus($status).'.']);
    }

    public function restaurar(Request $request, Submissao $submissao, int $trabalho): JsonResponse
    {
        $registro = $submissao->trabalhos()->onlyTrashed()->whereKey($trabalho)->firstOrFail();
        $registro->restore();
        $registro->historicos()->create([
            'historico' => 'Trabalho restaurado', 'usuario' => $this->usuarioGi($request),
            'dados' => null, 'data_hora' => now(),
        ]);

        return response()->json(['message' => 'Trabalho restaurado com sucesso.']);
    }

    public function historico(Request $request, Submissao $submissao, int $trabalho): JsonResponse
    {
        $registro = $submissao->trabalhos()->withTrashed()->whereKey($trabalho)->firstOrFail();
        $query = $registro->historicos();
        $total = $query->count();
        $inicio = max(0, (int) $request->input('start', 0));
        $tamanho = min(100, max(1, (int) $request->input('length', 10)));
        $dados = $query->latest('data_hora')->latest('id')->skip($inicio)->take($tamanho)->get()->values()
            ->map(fn ($item, int $indice): array => [
                'numero' => $total - $inicio - $indice,
                'historico' => e($item->historico),
                'usuario' => e($item->usuario ?: '—'),
                'dados' => view('partials.historico-dados', ['dados' => $item->dados ?? []])->render(),
                'data_hora' => $item->data_hora?->format('d/m/Y H:i:s') ?? '—',
            ]);

        return response()->json(['draw' => (int) $request->input('draw'), 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => $dados]);
    }

    public function resumoNotificacao(
        Submissao $submissao,
        string $tipo,
        SubmissaoResultadoNotificacaoService $notificacoes,
    ): JsonResponse {
        app(GiPermissionService::class)->exigir('submissoes.avaliar');
        abort_unless(in_array($tipo, ['aprovados', 'reprovados'], true), 404);

        return response()->json($notificacoes->resumo($submissao, $tipo));
    }

    public function notificarResultados(
        Request $request,
        Submissao $submissao,
        string $tipo,
        SubmissaoResultadoNotificacaoService $notificacoes,
    ): JsonResponse {
        app(GiPermissionService::class)->exigir('submissoes.avaliar');
        abort_unless(in_array($tipo, ['aprovados', 'reprovados'], true), 404);
        $campos = $tipo === 'aprovados'
            ? ['assunto_principal', 'mensagem_principal', 'assunto_coautor', 'mensagem_coautor']
            : ['assunto', 'mensagem'];
        $regras = [];
        foreach ($campos as $campo) {
            $regras[$campo] = ['required', 'string', 'max:'.(str_starts_with($campo, 'assunto') ? 255 : 20000)];
        }
        $mensagens = $request->validate($regras);
        $resultado = $notificacoes->enviar($submissao, $tipo, $mensagens, $request);
        $mensagem = $resultado['enviados'].' e-mail(s) enviado(s) em '.$resultado['trabalhos'].' trabalho(s).';
        if ($resultado['falhas']) $mensagem .= ' '.$resultado['falhas'].' envio(s) falharam e continuarão pendentes.';
        if (! $resultado['enviados'] && ! $resultado['falhas']) $mensagem = 'Não há destinatários pendentes para esta notificação.';

        return response()->json(['message' => $mensagem, ...$resultado]);
    }

    public function excluirDefinitivamente(Submissao $submissao, int $trabalho): JsonResponse
    {
        $registro = $submissao->trabalhos()->onlyTrashed()->whereKey($trabalho)->firstOrFail();
        $titulo = $registro->titulo_trabalho;
        $arquivoEposter = $registro->eposter_arquivo;
        $registro->forceDelete();
        if ($arquivoEposter) \Illuminate\Support\Facades\File::delete(storage_path('app/private/eposters/'.$arquivoEposter));

        return response()->json(['message' => "O trabalho \"{$titulo}\" foi excluído definitivamente."]);
    }

    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'evento_id' => ['required', 'integer', 'exists:eventos,id'],
            'titulo' => ['required', 'string', 'max:255'],
            'informacoes' => ['nullable', 'string', 'max:500000'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['required', 'date', 'after:data_inicio'],
            'eposter_data_inicio' => ['required', 'date', 'after:data_fim'],
            'eposter_data_fim' => ['required', 'date', 'after:eposter_data_inicio'],
            'ativo' => ['required', 'boolean'],
            'mostrar_link_evento' => ['sometimes', 'boolean'],
            'mostrar_categoria_trabalho' => ['sometimes', 'boolean'],
            'mostrar_palavras_chave' => ['sometimes', 'boolean'],
            'min_palavras_chave' => ['required_if:mostrar_palavras_chave,1', 'nullable', 'integer', 'min:1', 'max:100'],
            'max_palavras_chave' => ['required_if:mostrar_palavras_chave,1', 'nullable', 'integer', 'min:1', 'max:100', 'gte:min_palavras_chave'],
            'mostrar_apresentacao' => ['sometimes', 'boolean'],
            'mostrar_aprovacao_comite_etica' => ['sometimes', 'boolean'],
            'mostrar_apoio_financeiro' => ['sometimes', 'boolean'],
            'qtde_resumo' => ['required', 'integer', 'min:1', 'max:100000'],
            'qtde_autores' => ['required', 'integer', 'min:1', 'max:100'],
            'modelo_trabalho' => ['nullable', 'string', 'max:1000000'],
            'personalizacao' => ['required', 'array:alterar_cor_fundo_pagina,cor_fundo_pagina'],
            'personalizacao.alterar_cor_fundo_pagina' => ['required', 'boolean'],
            'personalizacao.cor_fundo_pagina' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'min_palavras_chave.required_if' => 'Informe o mínimo de palavras-chave.',
            'max_palavras_chave.required_if' => 'Informe o máximo de palavras-chave.',
            'max_palavras_chave.gte' => 'O máximo de palavras-chave deve ser maior ou igual ao mínimo.',
            'evento_id.required' => 'Selecione o evento.',
            'titulo.required' => 'Informe o título da submissão.',
            'data_inicio.required' => 'Informe a data inicial.',
            'data_fim.required' => 'Informe a data final.',
            'data_fim.after' => 'A data final precisa ser posterior à data inicial.',
            'eposter_data_inicio.required' => 'Informe a data e hora inicial do envio do e-pôster.',
            'eposter_data_inicio.after' => 'O período de envio do e-pôster deve começar depois do fim da submissão dos trabalhos.',
            'eposter_data_fim.required' => 'Informe a data e hora final do envio do e-pôster.',
            'eposter_data_fim.after' => 'O fim do envio do e-pôster deve ser posterior ao seu início.',
            'qtde_resumo.required' => 'Informe a quantidade máxima de caracteres do resumo.',
            'qtde_resumo.integer' => 'A quantidade de caracteres do resumo deve ser um número inteiro.',
            'qtde_resumo.min' => 'A quantidade de caracteres do resumo deve ser maior que zero.',
            'qtde_resumo.max' => 'A quantidade de caracteres do resumo não pode ultrapassar 100.000.',
            'qtde_autores.required' => 'Informe a quantidade máxima de autores.',
            'qtde_autores.integer' => 'A quantidade de autores deve ser um número inteiro.',
            'qtde_autores.min' => 'A submissão deve permitir pelo menos um autor.',
            'qtde_autores.max' => 'A quantidade de autores não pode ultrapassar 100.',
        ]);

        $dados['min_palavras_chave'] = (int) ($dados['min_palavras_chave'] ?? 3);
        $dados['max_palavras_chave'] = (int) ($dados['max_palavras_chave'] ?? 6);
        $dados['informacoes'] = app(ConteudoEditorFormularioService::class)
            ->sanitizar($dados['informacoes'] ?? '') ?: null;
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

    private function usuarioGi(Request $request): string
    {
        $usuario = trim((string) $request->session()->get('gi_context.usuario.nome'))
            ?: 'Usuário GI '.(string) $request->session()->get('gi_context.usuario.id', 'não identificado');

        return Str::limit($usuario, 255, '');
    }
}
