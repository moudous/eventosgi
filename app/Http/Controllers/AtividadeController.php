<?php

namespace App\Http\Controllers;

use App\Models\Atividade;
use App\Models\Categoria;
use App\Models\Evento;
use App\Models\HistoricoAtividade;
use App\Models\InscricaoAtividade;
use App\Models\Participante;
use App\Rules\EmailValido;
use App\Services\ArmazemService;
use App\Services\FormularioInscricaoService;
use App\Services\IdentificacaoParticipanteService;
use App\Services\GiPermissionService;
use App\Services\HistoricoService;
use App\Services\LimiteEnvioCodigoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Validator;

class AtividadeController
{
    public function index(Request $request, ArmazemService $armazem): View { return view('atividades.index', ['apagados' => false, 'estadoTabela' => $armazem->recuperar('atividades', $request), 'eventosFiltro' => Evento::withTrashed()->orderBy('nome')->get(['id', 'nome'])]); }
    public function apagados(Request $request, ArmazemService $armazem): View { return view('atividades.index', ['apagados' => true, 'estadoTabela' => $armazem->recuperar('atividades', $request), 'eventosFiltro' => Evento::withTrashed()->orderBy('nome')->get(['id', 'nome'])]); }

    public function dados(Request $request, ArmazemService $armazem): JsonResponse
    {
        $apagados = $request->boolean('apagados');
        $query = ($apagados ? Atividade::onlyTrashed() : Atividade::query())->with(['evento', 'criador:id,nome'])->withCount('inscricoes');
        $total = (clone $query)->count();
        $filtroEvento = max(0, (int) $request->input('filtro_evento', 0));
        if ($filtroEvento > 0) $query->where('evento_id', $filtroEvento);
        $busca = trim((string) $request->input('search.value', ''));
        if ($busca !== '') $query->where(fn ($q) => $q->where('nome', 'like', "%{$busca}%")->orWhereHas('evento', fn ($e) => $e->where('nome', 'like', "%{$busca}%"))->orWhereHas('criador', fn ($u) => $u->where('nome', 'like', "%{$busca}%")));
        $filtrados = (clone $query)->count();
        $colunas = ['id', 'nome', 'evento_id', 'modalidade', 'data_inicio', 'data_fim', 'inscricoes_count', 'ativo', 'criado_por', 'created_at', 'updated_at', 'deleted_at'];
        $coluna = $colunas[(int) $request->input('order.0.column', 0)] ?? 'id';
        if ($coluna === 'criado_por') $coluna = \App\Models\Usuario::select('nome')->whereColumn('usuarios.id', 'atividades.criado_por')->limit(1);
        $direcao = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $inicio = max(0, (int) $request->input('start', 0));
        $tamanho = min(100, max(1, (int) $request->input('length', 10)));
        $armazem->salvar('atividades', $request, intdiv($inicio, $tamanho) + 1, $busca, $tamanho, ['filtro_evento' => $filtroEvento]);
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

    public function create(): View { return view('atividades.create', ['eventos' => Evento::query()->where('ativo', true)->orderBy('nome')->get(), 'categorias' => $this->categoriasDisponiveis()]); }
    public function store(Request $request, HistoricoService $historico): RedirectResponse
    {
        $dados = $this->validar($request); $dados['criado_por'] = (int) $request->session()->get('gi_context.usuario.id');
        $atividade = Atividade::create($dados); $historico->atividade($atividade, 'Atividade Inserida', $atividade->only(['id', 'nome', 'ativo', 'criado_por', 'evento_id', 'modalidade', 'data_inicio', 'data_fim']), $request);
        return redirect()->route('atividades.index')->with('status', 'Atividade cadastrada com sucesso.');
    }
    public function show(Atividade $atividade): View { $atividade->load(['evento', 'categoria']); return view('atividades.show', compact('atividade')); }
    public function edit(Atividade $atividade): View { return view('atividades.edit', ['atividade' => $atividade, 'eventos' => Evento::query()->where('ativo', true)->orWhereKey($atividade->evento_id)->orderBy('nome')->get(), 'categorias' => $this->categoriasDisponiveis($atividade)]); }

    /**
     * Categorias oferecidas no combo: as ativas e, na edicao, tambem a que ja esta
     * escolhida -- desativada depois, ela sumiria da lista e a atividade perderia a
     * classificacao no primeiro salvamento.
     */
    private function categoriasDisponiveis(?Atividade $atividade = null): \Illuminate\Support\Collection
    {
        return Categoria::query()
            ->where('ativo', true)
            ->when($atividade?->categoria_id, fn ($consulta, $id) => $consulta->orWhereKey($id))
            ->orderBy('nome')->get();
    }
    public function formulario(Atividade $atividade, GiPermissionService $permissoes): View { return view('atividades.formulario', ['atividade' => $atividade, 'permissoes' => $permissoes]); }
    public function salvarFormulario(Request $request, Atividade $atividade): RedirectResponse
    {
        $dados = $request->validate(['formulario' => ['required', 'json']]);
        $config = json_decode($dados['formulario'], true);
        $validator = validator(['config' => $config], [
            'config' => ['required', 'array'],
            'config.limitar_inscricoes' => ['sometimes', 'boolean'],
            'config.limite_inscricoes' => ['required_if:config.limitar_inscricoes,true', 'nullable', 'integer', 'min:1'],
            'config.mensagem_vagas_esgotadas' => ['nullable', 'string', 'max:2000'],
            'config.mensagem_ja_inscrito' => ['nullable', 'string', 'max:2000'],
            'config.mensagem_identificacao' => ['nullable', 'string', 'max:2000'],
        ], [
            'config.limite_inscricoes.required_if' => 'Informe a quantidade de inscrições disponíveis ao ativar o limite.',
            'config.limite_inscricoes.integer' => 'A quantidade de inscrições deve ser um número inteiro.',
            'config.limite_inscricoes.min' => 'A quantidade de inscrições deve ser pelo menos 1.',
        ]);
        if ($validator->fails()) return back()->withErrors(['formulario' => $validator->errors()->first()])->withInput();

        // Sem atividades.formulario.estrutura o bloco Estrutura nem e exibido, entao o
        // JSON chega sem campo nenhum. Mantemos o que ja estava gravado: salvar a
        // configuracao nao pode apagar os campos que alguem sem essa permissao nao viu.
        if (! app(GiPermissionService::class)->permite('atividades.formulario.estrutura')) {
            foreach (['campos', 'rows', 'grupos', 'fieldsets'] as $chave) {
                $config[$chave] = $atividade->formulario[$chave] ?? [];
            }
        }
        $config['mensagem_vagas_esgotadas'] = trim($config['mensagem_vagas_esgotadas'] ?? '') ?: Atividade::MENSAGEM_VAGAS_ESGOTADAS;
        $config['mensagem_ja_inscrito'] = trim($config['mensagem_ja_inscrito'] ?? '') ?: Atividade::MENSAGEM_JA_INSCRITO;
        $config['mensagem_identificacao'] = trim($config['mensagem_identificacao'] ?? '') ?: Atividade::MENSAGEM_IDENTIFICACAO;
        $atividade->update(['formulario' => $config]);
        return redirect()->route('atividades.formulario', $atividade)->with('status', 'Formulário salvo com sucesso.');
    }
    public function preview(Request $request, Atividade $atividade, IdentificacaoParticipanteService $identificacao, FormularioInscricaoService $servico): View
    {
        abort_unless($atividade->formulario, 404);

        $sessao = $identificacao->daSessao($request, $atividade);
        $participante = $sessao ? $identificacao->participanteDaSessao($request, $atividade) : null;

        // O cadastro pode ter sido removido ou unificado depois da identificacao.
        if ($sessao && ! $participante) {
            $identificacao->esquecer($request, $atividade);
            $sessao = null;
        }

        return view('atividades.formulario-publico', [
            'atividade' => $atividade,
            'config' => $atividade->formulario,
            'identificacao' => $sessao,
            'participante' => $participante,
            'estado' => $servico->estado($atividade, $participante, $sessao['email'] ?? null),
            // Selo novo a cada exibicao: e ele que prova, no pedido de codigo, que houve
            // um formulario aberto antes.
            'selo' => $identificacao->selo(),
        ]);
    }
    /**
     * Pagina publica de inscricao em uma atividade.
     *
     * Mesma tela da previa, sem a assinatura: e para ca que a pagina do evento manda quem
     * clica numa atividade, e e ela que um visitante de fora abre. So atividade ativa e
     * com formulario publicado; as protecoes contra abuso sao as mesmas da previa, porque
     * o POST cai no mesmo inscrever().
     */
    public function inscricaoPublica(Request $request, Atividade $atividade, IdentificacaoParticipanteService $identificacao, FormularioInscricaoService $servico): View
    {
        abort_unless($atividade->ativo, 404);

        return $this->preview($request, $atividade, $identificacao, $servico);
    }

    public function previewRedirect(Atividade $atividade): RedirectResponse
    {
        return redirect()->to(URL::temporarySignedRoute('atividades.formulario.preview', now()->addMinutes(30), $atividade));
    }

    /**
     * Unico destino POST do formulario publico. A URL e assinada, entao as etapas de
     * identificacao (pedir codigo, conferir codigo, trocar de e-mail) reaproveitam a
     * mesma assinatura e se distinguem pelo campo "acao".
     */
    public function inscrever(Request $request, Atividade $atividade, FormularioInscricaoService $servico, IdentificacaoParticipanteService $identificacao, LimiteEnvioCodigoService $limites): RedirectResponse
    {
        abort_unless($atividade->formulario, 404);
        if ($request->routeIs('inscricoes.publica.enviar')) abort_unless($atividade->ativo, 404);

        return match ((string) $request->input('acao')) {
            'solicitar_codigo' => $this->solicitarCodigo($request, $atividade, $identificacao, $servico, $limites),
            'validar_codigo' => $this->validarCodigo($request, $atividade, $identificacao),
            'validar_senha' => $this->validarSenha($request, $atividade, $identificacao),
            'trocar_email' => $this->trocarEmail($request, $atividade, $identificacao),
            default => $this->registrarInscricao($request, $atividade, $servico, $identificacao),
        };
    }

    private function trocarEmail(Request $request, Atividade $atividade, IdentificacaoParticipanteService $identificacao): RedirectResponse
    {
        $identificacao->esquecer($request, $atividade);

        return back();
    }

    private function solicitarCodigo(Request $request, Atividade $atividade, IdentificacaoParticipanteService $identificacao, FormularioInscricaoService $servico, LimiteEnvioCodigoService $limites): RedirectResponse
    {
        // validateWithBag: a etapa de identificacao exibe apenas o bag "identificacao".
        $dados = Validator::make(
            $request->all(),
            ['email' => ['required', 'max:150', new EmailValido]],
            ['required' => 'Informe o seu e-mail.'],
            ['email' => 'e-mail'],
        )->validateWithBag('identificacao');
        $email = mb_strtolower(trim($dados['email']));

        // Robo que caiu na isca, ou que respondeu rapido demais para ter lido a tela,
        // recebe a mesma resposta de sempre e nenhum disparo.
        if ($identificacao->pareceRobo($request) || $identificacao->pedidoApressado($request)) {
            return back()->with('codigo_enviado', $email);
        }

        // Selo ausente ou adulterado: o pedido nao veio de um formulario aberto aqui. O
        // aviso e explicito de proposito -- se um dia o campo sumir da tela, o erro
        // aparece na hora, em vez de todo mundo achar que recebeu um e-mail que nao saiu.
        if (! $identificacao->seloConfere($request)) {
            return back()->withInput()->withErrors(
                ['email' => 'Não foi possível confirmar o envio deste formulário. Recarregue a página e tente novamente.'],
                'identificacao',
            );
        }

        // Limites antes de qualquer resposta que dependa do e-mail digitado. A conferencia
        // de duplicidade logo abaixo diz se um endereco esta inscrito, e de graca ela viraria
        // um jeito de descobrir quem participa da atividade, um endereco por vez.
        $limites->conferir($request, $atividade, $email);

        // Duplicidade antes do envio: quem ja se inscreveu recebe o aviso, nao um codigo.
        if ($servico->jaInscritoPorEmail($atividade, $email)) {
            return back()->withInput()->with('ja_inscrito', $atividade->mensagemJaInscrito());
        }

        // conferir() ja rodou: a chamada equivalente dentro de solicitarCodigo() nao conta de novo.
        $identificacao->solicitarCodigo($request, $atividade, $email);

        return back()->with('codigo_enviado', $email);
    }

    private function validarCodigo(Request $request, Atividade $atividade, IdentificacaoParticipanteService $identificacao): RedirectResponse
    {
        $dados = Validator::make(
            $request->all(),
            ['email' => ['required', 'max:150', new EmailValido], 'codigo' => ['required', 'string', 'max:10']],
            ['required' => 'Informe o e-mail e o código recebido.'],
            ['email' => 'e-mail', 'codigo' => 'código'],
        )->validateWithBag('identificacao');
        $resultado = $identificacao->validarCodigo($request, $atividade, $dados['email'], $dados['codigo']);

        $participante = $identificacao->participanteDaSessao($request, $atividade);
        if ($participante && app(FormularioInscricaoService::class)->jaInscrito($atividade, $participante, $dados['email'])) {
            return back()->with('identificado', $atividade->mensagemJaInscrito());
        }

        $aviso = $resultado['unificados'] > 0
            ? 'Encontramos '.($resultado['unificados'] + 1).' cadastros com este e-mail e eles foram unificados. Confira os dados abaixo.'
            : ($resultado['criado']
                ? 'E-mail confirmado. Complete o seu cadastro abaixo.'
                : 'E-mail confirmado. Confira e complete os seus dados abaixo.');

        return back()->with('identificado', $aviso);
    }

    private function validarSenha(Request $request, Atividade $atividade, IdentificacaoParticipanteService $identificacao): RedirectResponse
    {
        $dados = $request->validateWithBag('identificacao', [
            'email' => ['required', 'email', 'max:150'],
            'senha' => ['required', 'string', 'max:200'],
        ], [
            'email.required' => 'Informe o e-mail.',
            'senha.required' => 'Informe a senha.',
        ]);
        $resultado = $identificacao->validarSenha($request, $atividade, $dados['email'], $dados['senha']);

        $aviso = $resultado['criado']
            ? 'E-mail confirmado. Complete seus dados para continuar.'
            : 'Olá, '.$resultado['nome'].'. Sua identificação foi confirmada pela senha.';

        return back()->with('identificado', $aviso);
    }

    private function registrarInscricao(Request $request, Atividade $atividade, FormularioInscricaoService $servico, IdentificacaoParticipanteService $identificacao): RedirectResponse
    {
        $sessao = $identificacao->daSessao($request, $atividade);
        $participante = $identificacao->participanteDaSessao($request, $atividade);

        if (! $sessao || ! $participante) {
            $identificacao->esquecer($request, $atividade);

            return back()->with('identificacao_expirada', 'Confirme o seu e-mail novamente para enviar a inscrição.');
        }

        $resultado = $servico->inscrever($request, $atividade, $participante, $sessao['email']);
        if ($resultado['sucesso']) {
            $identificacao->esquecer($request, $atividade);

            return back()->with('status', $resultado['mensagem']);
        }
        if ($resultado['motivo'] === 'esgotado') return back()->with('vagas_esgotadas', $resultado['mensagem']);
        abort(403, $resultado['mensagem']);
    }
    public function inscricoes(Request $request, Atividade $atividade, ArmazemService $armazem): View
    {
        $recurso = 'atividades.inscricoes.'.$atividade->id;
        $estado = $armazem->recuperar($recurso, $request);
        $pesquisar = $request->exists('pesquisar')
            ? trim((string) $request->query('pesquisar'))
            : $estado['pesquisar'];
        $pagina = $request->exists('page')
            ? max(1, $request->integer('page'))
            : ($request->exists('pesquisar') ? 1 : $estado['page']);
        $porPagina = 20;
        $query = InscricaoAtividade::query()->where('atividade_id', $atividade->id);

        if ($pesquisar !== '') {
            $participantes = Participante::query()->where('nome', 'like', "%{$pesquisar}%")
                ->limit(500)->pluck('id')->all();
            $query->where(function ($consulta) use ($pesquisar, $participantes): void {
                $consulta->where('participante_email', 'like', "%{$pesquisar}%")
                    ->orWhere('ip', 'like', "%{$pesquisar}%")
                    ->orWhere('resposta', 'like', "%{$pesquisar}%");
                if ($participantes !== []) $consulta->orWhereIn('participante_id', $participantes);
                if (ctype_digit($pesquisar)) $consulta->orWhere('id', (int) $pesquisar);
            });
        }

        $inscricoes = $query->latest()->paginate($porPagina, ['*'], 'page', $pagina)
            ->appends(['pesquisar' => $pesquisar]);
        $armazem->salvar($recurso, $request, $inscricoes->currentPage(), $pesquisar, $porPagina);

        return view('atividades.inscricoes', compact('atividade', 'inscricoes', 'pesquisar'));
    }

    /**
     * URL assinada e temporaria da planilha de respostas.
     *
     * O download acontece por navegacao para esta URL, e nao por fetch mais blob: dentro
     * do iframe do GI o blob nao chega ao visitante. A assinatura tambem dispensa o cookie
     * de sessao, que um navegador pode recusar num iframe de outro dominio.
     */
    public function exportarLink(Atividade $atividade, string $formato): JsonResponse
    {
        abort_unless(in_array($formato, ['csv', 'ods', 'xls', 'xlsx'], true), 404);

        return response()->json([
            'url' => URL::temporarySignedRoute('atividades.inscricoes.exportar', now()->addMinutes(10), [$atividade, $formato]),
        ]);
    }

    public function exportarInscricoes(Atividade $atividade, string $formato)
    {
        return app(\App\Services\InscricoesExportService::class)->download($atividade, $formato);
    }

    /**
     * Entrega um anexo enviado na inscricao.
     *
     * Os anexos ficam em disco privado; esta e a unica porta para eles, sempre por URL
     * assinada gerada na tela de inscricoes, que ja exige permissao.
     */
    public function arquivoInscricao(InscricaoAtividade $inscricao, string $campo, int $indice, string $modo): StreamedResponse
    {
        $valores = $inscricao->resposta[$campo] ?? null;
        $caminho = is_array($valores) ? ($valores[$indice] ?? null) : ($indice === 0 ? $valores : null);

        abort_unless(is_string($caminho) && $caminho !== '', 404);
        abort_if(str_contains($caminho, '..'), 404);
        abort_unless(str_starts_with($caminho, FormularioInscricaoService::PASTA_ANEXOS.'/'), 404);

        $disco = Storage::disk(FormularioInscricaoService::DISCO_ANEXOS);
        // Anexos anteriores a mudanca para disco privado podem ter ficado no disco publico.
        if (! $disco->exists($caminho)) $disco = Storage::disk('public');
        abort_unless($disco->exists($caminho), 404);

        // HTML e SVG enviados por terceiros virariam script rodando na origem da aplicacao,
        // entao so tipos inertes abrem embutidos; o resto e sempre baixado.
        $tipo = (string) $disco->mimeType($caminho);
        $podeExibir = str_starts_with($tipo, 'image/') && $tipo !== 'image/svg+xml'
            || $tipo === 'application/pdf' || $tipo === 'text/plain';

        return $disco->response($caminho, basename($caminho), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            // Conteudo de terceiros: nada de script, nada de requisicao para fora.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; media-src 'self'; object-src 'self'; style-src 'unsafe-inline'; sandbox",
        ], ($modo === 'baixar' || ! $podeExibir) ? 'attachment' : 'inline');
    }
    public function previewLink(Atividade $atividade): JsonResponse { return response()->json(['url' => URL::temporarySignedRoute('atividades.formulario.preview', now()->addMinutes(30), $atividade)]); }
    public function update(Request $request, Atividade $atividade, HistoricoService $historico): RedirectResponse
    {
        $campos = ['nome', 'ativo', 'evento_id', 'modalidade', 'data_inicio', 'data_fim', 'personalizacao'];
        $antes = $atividade->only($campos); $atividade->update($this->validar($request, $atividade));
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
    private function validar(Request $request, ?Atividade $atividade = null): array
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'ativo' => ['required', 'boolean'],
            'evento_id' => ['required', 'integer', 'exists:eventos,id'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'modalidade' => ['nullable', 'in:ead,presencial'],
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'personalizacao' => ['required', 'array:posicao,borda,cor_borda'],
            'personalizacao.posicao' => ['required', 'in:esquerda,direita'],
            'personalizacao.borda' => ['required', 'boolean'],
            'personalizacao.cor_borda' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'imagem_atividade' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=12000,max_height=12000'],
        ]);

        $dados['personalizacao']['imagem'] = $atividade?->estiloImagem()['imagem'];
        if ($arquivo = $request->file('imagem_atividade')) {
            $nome = \Illuminate\Support\Str::uuid().'.'.$arquivo->extension();
            \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('app/public/personalizacao'));
            $arquivo->move(storage_path('app/public/personalizacao'), $nome);
            $dados['personalizacao']['imagem'] = $nome;
        }
        unset($dados['imagem_atividade']);

        return $dados;
    }
}
