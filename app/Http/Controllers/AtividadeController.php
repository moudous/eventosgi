<?php

namespace App\Http\Controllers;

use App\Exceptions\PagamentoPixConfirmadoException;
use App\Models\Atividade;
use App\Models\Categoria;
use App\Models\Evento;
use App\Models\HistoricoAtividade;
use App\Models\InscricaoAtividade;
use App\Models\Participante;
use App\Models\PixCobranca;
use App\Rules\EmailValido;
use App\Services\ArmazemService;
use App\Services\CaptchaInscricaoService;
use App\Services\CancelamentoInscricaoService;
use App\Services\ComprovanteInscricaoService;
use App\Services\ConteudoEditorFormularioService;
use App\Services\DistribuicaoVagasService;
use App\Services\FormularioInscricaoService;
use App\Services\GiEmailService;
use App\Services\IdentificacaoParticipanteService;
use App\Services\GiPermissionService;
use App\Services\HistoricoService;
use App\Services\PresencaQrService;
use App\Services\InscricoesExportService;
use App\Services\SicoobPixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AtividadeController
{
    public function index(Request $request, ArmazemService $armazem): View { return view('atividades.index', ['apagados' => false, 'estadoTabela' => $armazem->recuperar('atividades', $request), 'eventosFiltro' => Evento::withTrashed()->orderByDesc('created_at')->orderByDesc('id')->get(['id', 'nome'])]); }
    public function apagados(Request $request, ArmazemService $armazem): View { return view('atividades.index', ['apagados' => true, 'estadoTabela' => $armazem->recuperar('atividades', $request), 'eventosFiltro' => Evento::withTrashed()->orderByDesc('created_at')->orderByDesc('id')->get(['id', 'nome'])]); }

    public function dados(Request $request, ArmazemService $armazem): JsonResponse
    {
        $apagados = $request->boolean('apagados');
        $query = ($apagados ? Atividade::onlyTrashed() : Atividade::query())->with('evento')->withCount('inscricoes');
        $total = (clone $query)->count();
        $filtroEvento = max(0, (int) $request->input('filtro_evento', 0));
        if ($filtroEvento > 0) $query->where('evento_id', $filtroEvento);
        $busca = trim((string) $request->input('search.value', ''));
        if ($busca !== '') $query->where(fn ($q) => $q->where('nome', 'like', "%{$busca}%")->orWhereHas('evento', fn ($e) => $e->where('nome', 'like', "%{$busca}%")));
        $filtrados = (clone $query)->count();
        $colunas = ['id', 'nome', 'evento_id', 'data_inicio', 'data_fim', 'inscricoes_count', 'ativo', 'updated_at', 'deleted_at'];
        $coluna = $colunas[(int) $request->input('order.0.column', 0)] ?? 'id';
        $direcao = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
        $inicio = max(0, (int) $request->input('start', 0));
        $tamanho = min(100, max(1, (int) $request->input('length', 10)));
        $armazem->salvar('atividades', $request, intdiv($inicio, $tamanho) + 1, $busca, $tamanho, ['filtro_evento' => $filtroEvento]);
        $permissoes = app(GiPermissionService::class);
        $dados = $query->orderBy($coluna, $direcao)->skip($inicio)->take($tamanho)->get()->map(fn (Atividade $atividade) => [
            'inscricoes_count' => $atividade->inscricoes_count, 'id' => $atividade->id, 'nome' => e($atividade->nome),
            'evento' => e($atividade->evento?->nome ?? '—'),
            'data_inicio' => $atividade->data_inicio?->format('d/m/Y H:i') ?? '—', 'data_fim' => $atividade->data_fim?->format('d/m/Y H:i') ?? '—',
            'ativo' => view('eventos.partials.status', ['evento' => $atividade])->render(),
            'updated_at' => $atividade->updated_at?->format('d/m/Y H:i') ?? '—',
            'deleted_at' => $atividade->deleted_at?->format('d/m/Y H:i') ?? '—',
            'acoes' => view('atividades.partials.acoes', ['atividade' => $atividade, 'apagados' => $apagados, 'permissoes' => $permissoes])->render(),
        ]);
        return response()->json(['draw' => (int) $request->input('draw'), 'recordsTotal' => $total, 'recordsFiltered' => $filtrados, 'data' => $dados]);
    }

    public function create(): View { return view('atividades.create', ['eventos' => Evento::query()->where('ativo', true)->orderBy('nome')->get(), 'categorias' => $this->categoriasDisponiveis()]); }
    public function store(Request $request, HistoricoService $historico): RedirectResponse
    {
        $dados = $this->validar($request); $sessoes = $dados['sessoes'] ?? []; unset($dados['sessoes']);
        $dados['criado_por'] = (int) $request->session()->get('gi_context.usuario.id');
        $atividade = DB::transaction(function () use ($dados, $sessoes): Atividade {
            $atividade = Atividade::create($dados);
            $this->sincronizarSessoes($atividade, $sessoes);
            return $atividade;
        });
        $historico->atividade($atividade, 'Atividade Inserida', $atividade->only(['id', 'tipo', 'formato', 'nome', 'ativo', 'criado_por', 'evento_id', 'modalidade', 'tipo_link_transmissao', 'link_transmissao', 'data_inicio', 'data_fim']), $request);
        return redirect()->route('atividades.index')->with('status', 'Atividade cadastrada com sucesso.');
    }
    public function show(Atividade $atividade): View { $atividade->load(['evento', 'categoria', 'sessoes' => fn ($q) => $q->withCount('inscricoes')]); return view('atividades.show', compact('atividade')); }
    public function transmissao(Atividade $atividade): Response
    {
        $atividade->loadMissing('evento');
        abort_unless($atividade->ativo && $atividade->evento?->ativo && $atividade->modalidade === 'ead', 404);
        abort_unless($iframe = $atividade->iframeTransmissaoSeguro(), 404);

        return response('<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>html,body,iframe{width:100%;height:100%;margin:0;border:0;background:#000}iframe{display:block}</style></head><body>'.$iframe.'</body></html>');
    }
    public function edit(Atividade $atividade): View { $atividade->load(['sessoes', 'evento']); return view('atividades.edit', ['atividade' => $atividade, 'eventos' => Evento::query()->where('ativo', true)->orWhereKey($atividade->evento_id)->orderBy('nome')->get(), 'categorias' => $this->categoriasDisponiveis($atividade)]); }

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
    public function formulario(Atividade $atividade, GiPermissionService $permissoes, DistribuicaoVagasService $distribuicao): View { $distribuicao->recalcular($atividade); return view('atividades.formulario', ['atividade' => $atividade->refresh(), 'permissoes' => $permissoes]); }
    public function salvarFormulario(Request $request, Atividade $atividade, ConteudoEditorFormularioService $editor, DistribuicaoVagasService $distribuicao, PresencaQrService $presencaQr): RedirectResponse
    {
        $request->merge([
            'url' => $request->boolean('usar_url') ? Str::slug((string) $request->input('url')) : null,
        ]);
        $dados = $request->validate([
            'formulario' => ['required', 'json'],
            'usar_url' => ['required', 'boolean'],
            'url' => [
                'nullable', 'required_if:usar_url,1', 'string', 'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                \Illuminate\Validation\Rule::unique('atividades', 'url')->ignore($atividade->id),
            ],
        ], [
            'url.required_if' => 'Informe a URL que será usada para esta atividade.',
            'url.regex' => 'A URL deve conter somente letras minúsculas, números e hífens.',
            'url.unique' => 'Esta URL já está sendo usada por outra atividade.',
        ]);
        $url = $request->boolean('usar_url') ? $dados['url'] : null;
        $config = $distribuicao->prepararConfiguracao(json_decode($dados['formulario'], true));
        $podeInserirCampoPix = app(GiPermissionService::class)->permite('atividades.pix', $request);
        $validator = validator(['config' => $config], [
            'config' => ['required', 'array'],
            'config.campos' => ['sometimes', 'array'],
            'config.campos.*.nome' => ['required', 'string', 'max:150', 'distinct'],
            'config.campos.*.label' => ['required', 'string', 'max:255'],
            'config.campos.*.texto_opcao' => ['nullable', 'string', 'max:255'],
            'config.campos.*.grid' => ['sometimes', 'integer', 'in:12,6,4'],
            'config.campos.*.campos_por_linha' => ['sometimes', 'integer', 'between:1,12'],
            'config.campos.*.opcoes' => ['sometimes', 'array'],
            'config.campos.*.opcoes.*.valor' => ['required_with:config.campos.*.opcoes.*.texto', 'string', 'max:255'],
            'config.campos.*.opcoes.*.texto' => ['required_with:config.campos.*.opcoes.*.valor', 'string', 'max:255'],
            'config.campos.*.opcoes.*.percentual_vagas' => ['nullable', 'numeric', 'between:0,100'],
            'config.campos.*.opcoes.*.valor_pix' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'config.campos.*.percentual_vagas' => ['nullable', 'numeric', 'between:0,100'],
            'config.campos.*.cobranca_pix' => ['sometimes', 'boolean'],
            'config.campos.*.cobranca_pix_modo' => ['nullable', 'in:campo,itens'],
            'config.campos.*.valor_pix' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'config.pagamento_inscricao.pix_sicoob' => ['sometimes', 'boolean'],
            'config.pagamento_inscricao.expiracao' => ['nullable', 'integer', 'between:300,86400'],
            'config.pagamento_inscricao.descricao' => ['nullable', 'string', 'max:140'],
            'config.criterios_vagas' => ['sometimes', 'array'],
            'config.criterios_vagas.*' => ['required', 'string', 'distinct'],
            'config.limitar_inscricoes' => ['sometimes', 'boolean'],
            'config.limite_inscricoes' => ['required_if:config.limitar_inscricoes,true', 'nullable', 'integer', 'min:1'],
            'config.apos_encerrar_vagas' => ['required_if:config.limitar_inscricoes,true', 'in:encerrar,lista_reserva'],
            'config.lista_reserva_sem_limite' => ['sometimes', 'boolean'],
            'config.limite_lista_reserva' => [
                \Illuminate\Validation\Rule::requiredIf(fn () => ! empty($config['limitar_inscricoes'])
                    && ($config['apos_encerrar_vagas'] ?? 'encerrar') === 'lista_reserva'
                    && empty($config['lista_reserva_sem_limite'])),
                'nullable', 'integer', 'min:1',
            ],
            'config.mostrar_vagas_restantes' => ['sometimes', 'boolean'],
            'config.registrar_presenca_qrcode' => ['sometimes', 'boolean'],
            'config.mensagem_vagas_esgotadas' => ['nullable', 'string', 'max:2000'],
            'config.mensagem_ja_inscrito' => ['nullable', 'string', 'max:2000'],
            'config.mensagem_identificacao' => ['nullable', 'string', 'max:2000'],
            'config.editor.exibir' => ['sometimes', 'boolean'],
            'config.editor.conteudo' => ['nullable', 'string', 'max:500000'],
            'config.rastreios' => ['sometimes', 'array', 'max:100'],
            'config.rastreios.*.titulo' => ['required', 'string', 'max:80'],
            'config.rastreios.*.codigo' => ['required', 'string', 'min:5', 'max:15', 'regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', 'distinct'],
            'config.rastreios.*.ativo' => ['required', 'boolean'],
            'config.rastreios.*.predefinido' => ['sometimes', 'boolean'],
        ], [
            'config.limite_inscricoes.required_if' => 'Informe a quantidade de inscrições disponíveis ao ativar o limite.',
            'config.limite_inscricoes.integer' => 'A quantidade de inscrições deve ser um número inteiro.',
            'config.limite_inscricoes.min' => 'A quantidade de inscrições deve ser pelo menos 1.',
            'config.limite_lista_reserva.required' => 'Informe a quantidade de inscrições além do limite ou marque “Sem limite”.',
            'config.limite_lista_reserva.integer' => 'A quantidade de inscrições além do limite deve ser um número inteiro.',
            'config.limite_lista_reserva.min' => 'A quantidade de inscrições além do limite deve ser pelo menos 1.',
        ]);
        $validator->after(function ($validator) use ($config): void {
            $pixAtivo = ! empty($config['pagamento_inscricao']['pix_sicoob']);
            $possuiCobranca = false;
            foreach (($config['campos'] ?? []) as $indice => $campo) {
                if (($campo['tipo'] ?? '') === 'pagamento_pix') {
                    $validator->errors()->add("config.campos.{$indice}.tipo", 'Pagamento PIX deve ser configurado no card Pagamento de Inscrição.');
                    continue;
                }
                if (! $pixAtivo || empty($campo['cobranca_pix'])) continue;
                $possuiCobranca = true;
                $porItem = ($campo['cobranca_pix_modo'] ?? 'campo') === 'itens';
                if ($porItem) {
                    if (empty($campo['opcoes'])) {
                        $validator->errors()->add("config.campos.{$indice}.cobranca_pix_modo", 'A cobrança por item exige um campo com opções.');
                    }
                    foreach (($campo['opcoes'] ?? []) as $opcaoIndice => $opcao) {
                        if ((float) ($opcao['valor_pix'] ?? 0) <= 0) {
                            $validator->errors()->add("config.campos.{$indice}.opcoes.{$opcaoIndice}.valor_pix", 'Informe um valor maior que zero para cada item cobrado.');
                        }
                    }
                } elseif ((float) ($campo['valor_pix'] ?? 0) <= 0) {
                    $validator->errors()->add("config.campos.{$indice}.valor_pix", 'Informe um valor maior que zero para o campo cobrado.');
                }
            }
            if ($pixAtivo && ! $possuiCobranca) {
                $validator->errors()->add('config.pagamento_inscricao.pix_sicoob', 'Marque Cobrança em pelo menos um campo da estrutura para usar o PIX Sicoob.');
            }
        });
        if ($validator->fails()) return back()->withErrors(['formulario' => $validator->errors()->first()])->withInput();
        $config['rastreios'] = collect($config['rastreios'] ?? [])->map(fn (array $rastreio) => [
            'titulo' => trim((string) $rastreio['titulo']),
            'codigo' => mb_strtolower(trim((string) $rastreio['codigo']), 'UTF-8'),
            'ativo' => ! empty($rastreio['ativo']),
            'predefinido' => ! empty($rastreio['predefinido']),
        ])->values()->all();
        $distribuicao->validarConfiguracao($config);

        // Sem atividades.formulario.estrutura o bloco Estrutura nem e exibido, entao o
        // JSON chega sem campo nenhum. Mantemos o que ja estava gravado: salvar a
        // configuracao nao pode apagar os campos que alguem sem essa permissao nao viu.
        if (! app(GiPermissionService::class)->permite('atividades.formulario.estrutura')) {
            foreach (['campos', 'rows', 'grupos', 'fieldsets'] as $chave) {
                $config[$chave] = $atividade->formulario[$chave] ?? [];
            }
        }
        if (! $podeInserirCampoPix) {
            $config['pagamento_inscricao'] = $atividade->formulario['pagamento_inscricao'] ?? ['pix_sicoob' => false];
            $cobrancasExistentes = collect($atividade->formulario['campos'] ?? [])->keyBy('nome');
            foreach ($config['campos'] as &$campo) {
                $existente = $cobrancasExistentes->get($campo['nome'] ?? '');
                foreach (['cobranca_pix', 'cobranca_pix_modo', 'valor_pix'] as $chave) {
                    $campo[$chave] = $existente[$chave] ?? ($chave === 'cobranca_pix' ? false : null);
                }
                if (isset($campo['opcoes']) && isset($existente['opcoes'])) {
                    $valores = collect($existente['opcoes'])->keyBy('valor');
                    foreach ($campo['opcoes'] as &$opcao) $opcao['valor_pix'] = $valores->get($opcao['valor'] ?? '')['valor_pix'] ?? null;
                    unset($opcao);
                }
            }
            unset($campo);
        }
        $config['mensagem_vagas_esgotadas'] = trim($config['mensagem_vagas_esgotadas'] ?? '') ?: Atividade::MENSAGEM_VAGAS_ESGOTADAS;
        $config['mensagem_ja_inscrito'] = trim($config['mensagem_ja_inscrito'] ?? '') ?: Atividade::MENSAGEM_JA_INSCRITO;
        $config['mensagem_identificacao'] = trim($config['mensagem_identificacao'] ?? '') ?: Atividade::MENSAGEM_IDENTIFICACAO;
        $config['mostrar_vagas_restantes'] = ! empty($config['limitar_inscricoes']) && ! empty($config['mostrar_vagas_restantes']);
        if (empty($config['limitar_inscricoes']) || ($config['apos_encerrar_vagas'] ?? 'encerrar') !== 'lista_reserva') {
            $config['apos_encerrar_vagas'] = 'encerrar';
            $config['lista_reserva_sem_limite'] = false;
            $config['limite_lista_reserva'] = null;
        }
        $config['registrar_presenca_qrcode'] = ! empty($config['registrar_presenca_qrcode']);
        $config['editor']['exibir'] = (bool) ($config['editor']['exibir'] ?? false);
        $config['editor']['conteudo'] = $editor->sanitizar($config['editor']['conteudo'] ?? '');
        DB::transaction(function () use ($atividade, $url, $distribuicao, $config): void {
            $atividade->update(['url' => $url]);
            $distribuicao->recalcular($atividade, $config);
        });
        $presencaQr->garantirCodigos($atividade->refresh());
        $editor->sincronizarImagens($atividade, $config['editor']['conteudo']);
        return redirect()->route('atividades.formulario', $atividade)->with('status', 'Formulário salvo com sucesso.');
    }

    public function enviarImagemEditor(Request $request, Atividade $atividade, ConteudoEditorFormularioService $editor): JsonResponse
    {
        $dados = $request->validate(['imagem' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120']], [
            'imagem.required' => 'Selecione uma imagem.',
            'imagem.image' => 'O arquivo selecionado não é uma imagem válida.',
            'imagem.mimes' => 'Use uma imagem JPG, PNG, GIF ou WebP.',
            'imagem.max' => 'A imagem deve ter no máximo 5 MB.',
        ]);
        $arquivo = $dados['imagem'];
        $nome = Str::uuid().'.'.mb_strtolower($arquivo->getClientOriginalExtension());
        File::ensureDirectoryExists($editor->pasta($atividade));
        $arquivo->move($editor->pasta($atividade), $nome);

        return response()->json(['url' => route('inscricoes.editor.imagem', [
            'atividade' => $atividade->hash_publica,
            'arquivo' => $nome,
        ])]);
    }

    public function imagemEditor(Atividade $atividade, string $arquivo, ConteudoEditorFormularioService $editor): BinaryFileResponse
    {
        $caminho = $editor->pasta($atividade).'/'.$arquivo;
        abort_unless(is_file($caminho), 404);

        return response()->file($caminho, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
    public function preview(Request $request, Atividade $atividade, IdentificacaoParticipanteService $identificacao, FormularioInscricaoService $servico, ComprovanteInscricaoService $comprovante, ConteudoEditorFormularioService $editor, DistribuicaoVagasService $distribuicao): View
    {
        abort_unless($atividade->formulario, 404);

        $sessao = $identificacao->daSessao($request, $atividade);
        $participante = $sessao ? $identificacao->participanteDaSessao($request, $atividade) : null;

        // O cadastro pode ter sido removido ou unificado depois da identificacao.
        if ($sessao && ! $participante) {
            $identificacao->esquecer($request, $atividade);
            $sessao = null;
        }

        $inscricao = $sessao ? $servico->inscricaoDoParticipante($atividade, $participante, $sessao['email']) : null;
        $inscricaoPendente = ! $inscricao && $sessao
            ? $servico->inscricaoPendenteDoParticipante($atividade, $participante, $sessao['email'])
            : null;
        $inscricao ??= $inscricaoPendente;
        $totalInscricoes = $atividade->inscricoes()->count();

        $config = $distribuicao->recalcular($atividade);
        $config['editor']['conteudo'] = $editor->sanitizar($config['editor']['conteudo'] ?? '');
        $cobrancasPix = $inscricao ? $inscricao->cobrancasPix : collect();
        if ($inscricaoPendente) {
            $estado = ['aberto' => false, 'motivo' => 'pagamento_pendente', 'mensagem' => 'Aguardando a confirmação do pagamento para realizar sua inscrição.', 'lista_reserva' => false];
        } else {
            $estado = $servico->estado($atividade, $participante, $sessao['email'] ?? null);
        }

        return view('atividades.formulario-publico', [
            'atividade' => $atividade,
            'config' => $config,
            'identificacao' => $sessao,
            'participante' => $participante,
            'instituicoesEnsino' => $participante ? $servico->instituicoesEnsino() : [],
            'estado' => $estado,
            'inscricao' => $inscricao,
            'totalInscricoes' => $totalInscricoes,
            'dadosComprovante' => $inscricao ? $comprovante->participante($inscricao) : [],
            'respostasComprovante' => $inscricao ? $comprovante->respostas($inscricao) : [],
            'qrPresenca' => $inscricao ? $comprovante->qrPresenca($inscricao) : null,
            'presencaInscricao' => $inscricao ? $comprovante->presenca($inscricao) : null,
            'cancelamentoBloqueadoPix' => $cobrancasPix->contains(
                fn (PixCobranca $cobranca): bool => $cobranca->pagamentoConfirmado(),
            ),
            'cobrancasPix' => $cobrancasPix->map(fn ($cobranca) => [
                'model' => $cobranca,
                'qr' => app(SicoobPixService::class)->qrCode($cobranca),
            ]),
            'pixAmbiente' => \App\Models\PixConfiguracao::atual()?->ambiente,
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
    public function inscricaoPublica(Request $request, Atividade $atividade, IdentificacaoParticipanteService $identificacao, FormularioInscricaoService $servico, ComprovanteInscricaoService $comprovante, ConteudoEditorFormularioService $editor, DistribuicaoVagasService $distribuicao): View
    {
        abort_unless($atividade->ativo, 404);

        return $this->preview($request, $atividade, $identificacao, $servico, $comprovante, $editor, $distribuicao);
    }

    public function previewRedirect(Atividade $atividade): RedirectResponse
    {
        return redirect()->to($atividade->urlPublica());
    }

    /**
     * As etapas de identificação e inscrição compartilham o endereço permanente por hash.
     */
    public function inscrever(Request $request, Atividade $atividade, FormularioInscricaoService $servico, IdentificacaoParticipanteService $identificacao, CaptchaInscricaoService $captcha, SicoobPixService $pix, CancelamentoInscricaoService $cancelamento): RedirectResponse
    {
        abort_unless($atividade->formulario, 404);
        if ($request->routeIs('inscricoes.publica.enviar', 'inscricoes.publica.amigavel.enviar')) abort_unless($atividade->ativo, 404);

        return match ((string) $request->input('acao')) {
            'solicitar_codigo' => $this->solicitarCodigo($request, $atividade, $identificacao, $captcha),
            'validar_codigo' => $this->validarCodigo($request, $atividade, $identificacao),
            'validar_senha' => $this->validarSenha($request, $atividade, $identificacao),
            'salvar_nova_senha' => $this->salvarNovaSenha($request, $atividade, $identificacao),
            'trocar_email' => $this->trocarEmail($request, $atividade, $identificacao),
            'gerar_pix' => $this->gerarPix($request, $atividade, $servico, $identificacao, $pix),
            'atualizar_pix' => $this->atualizarPix($request, $atividade, $servico, $identificacao, $pix),
            'cancelar_pix' => $this->cancelarPixPendente($request, $atividade, $servico, $identificacao, $cancelamento),
            default => $this->registrarInscricao($request, $atividade, $servico, $identificacao, $pix),
        };
    }

    private function trocarEmail(Request $request, Atividade $atividade, IdentificacaoParticipanteService $identificacao): RedirectResponse
    {
        $identificacao->esquecer($request, $atividade);

        return back();
    }

    private function solicitarCodigo(Request $request, Atividade $atividade, IdentificacaoParticipanteService $identificacao, CaptchaInscricaoService $captcha): RedirectResponse
    {
        $dados = Validator::make(
            $request->all(),
            ['email' => ['required', 'max:150', new EmailValido], 'captcha' => ['required', 'string', 'size:6']],
            ['email.required' => 'Informe o seu e-mail.', 'captcha.required' => 'Digite o texto exibido na imagem.', 'captcha.size' => 'Digite os 6 caracteres exibidos na imagem.'],
            ['email' => 'e-mail', 'captcha' => 'texto da imagem'],
        )->validateWithBag('identificacao');
        $email = mb_strtolower(trim($dados['email']));
        $captcha->validar($request, $atividade, $dados['captcha']);

        // A prova visual substitui o selo, a isca, o tempo mínimo, os limites de frequência
        // e a recusa por inscrição já existente neste fluxo público.
        $identificacao->solicitarCodigo($request, $atividade, $email, ignorarLimites: true);

        return back()->with('senha_temporaria_enviada', $email);
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
            return $this->voltarAoInicioFormulario($request, $atividade->mensagemJaInscrito());
        }

        $aviso = $resultado['unificados'] > 0
            ? 'Encontramos '.($resultado['unificados'] + 1).' cadastros com este e-mail e eles foram unificados. Confira os dados abaixo.'
            : ($resultado['criado']
                ? 'E-mail confirmado. Complete o seu cadastro abaixo.'
                : 'E-mail confirmado. Confira e complete os seus dados abaixo.');

        return $this->voltarAoInicioFormulario($request, $aviso);
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

        if ($resultado['temporaria']) {
            $request->session()->flash('oferecer_nova_senha', true);
        }

        $aviso = $resultado['criado']
            ? 'E-mail confirmado. Complete seus dados para continuar.'
            : 'Olá, '.$resultado['nome'].'. Sua identificação foi confirmada pela senha.';

        return $this->voltarAoInicioFormulario($request, $aviso);
    }

    private function salvarNovaSenha(Request $request, Atividade $atividade, IdentificacaoParticipanteService $identificacao): RedirectResponse
    {
        // Se a validação falhar, o modal volta aberto para o visitante corrigir os campos.
        $request->session()->flash('oferecer_nova_senha', true);
        $dados = $request->validateWithBag('identificacao', [
            'senha_nova' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'usar_na_submissao' => ['sometimes', 'boolean'],
        ], [
            'senha_nova.required' => 'Digite a nova senha.',
            'senha_nova.confirmed' => 'A confirmação da nova senha não confere.',
        ]);

        $identificacao->definirSenhaDaSessao($request, $atividade, $dados['senha_nova']);
        $request->session()->forget('oferecer_nova_senha');

        return $this->voltarAoInicioFormulario($request, 'Nova senha cadastrada. Você já está identificado.');
    }

    private function voltarAoInicioFormulario(Request $request, string $mensagem): RedirectResponse
    {
        return redirect()->to($request->fullUrl().'#inicio-formulario')->with('identificado', $mensagem);
    }

    private function registrarInscricao(Request $request, Atividade $atividade, FormularioInscricaoService $servico, IdentificacaoParticipanteService $identificacao, SicoobPixService $pix): RedirectResponse
    {
        $sessao = $identificacao->daSessao($request, $atividade);
        $participante = $identificacao->participanteDaSessao($request, $atividade);

        if (! $sessao || ! $participante) {
            $identificacao->esquecer($request, $atividade);

            return back()->with('identificacao_expirada', 'Confirme o seu e-mail novamente para enviar a inscrição.');
        }

        $resultado = $servico->inscrever($request, $atividade, $participante, $sessao['email']);
        if ($resultado['sucesso']) {
            try {
                $inscricao = InscricaoAtividade::query()->withoutGlobalScope('ativas')->with(['atividade', 'participante'])->findOrFail($resultado['inscricao_id']);
                $pix->gerarParaInscricao($inscricao);
            } catch (Throwable $erro) {
                report($erro);
                return back()->with('status', $resultado['mensagem'])->with('pix_erro', $erro->getMessage());
            }
            return back()->with('status', $resultado['mensagem']);
        }
        if ($resultado['motivo'] === 'esgotado') return back()->with('vagas_esgotadas', $resultado['mensagem']);
        abort(403, $resultado['mensagem']);
    }

    private function gerarPix(Request $request, Atividade $atividade, FormularioInscricaoService $servico, IdentificacaoParticipanteService $identificacao, SicoobPixService $pix): RedirectResponse
    {
        $inscricao = $this->inscricaoPixAutorizada($request, $atividade, $servico, $identificacao);
        try {
            $pix->gerarParaInscricao($inscricao->loadMissing(['atividade', 'participante']));
            return back()->with('status', 'Cobrança PIX gerada.');
        } catch (Throwable $erro) {
            report($erro);
            return back()->with('pix_erro', $erro->getMessage());
        }
    }

    private function atualizarPix(Request $request, Atividade $atividade, FormularioInscricaoService $servico, IdentificacaoParticipanteService $identificacao, SicoobPixService $pix): RedirectResponse
    {
        $inscricao = $this->inscricaoPixAutorizada($request, $atividade, $servico, $identificacao);
        $cobranca = $inscricao->cobrancasPix()->findOrFail((int) $request->input('cobranca_id'));
        try {
            $atualizada = $pix->consultar($cobranca);
            return back()->with('status', $atualizada->status === 'CONCLUIDA' ? 'Pagamento PIX confirmado.' : 'O pagamento ainda não foi confirmado pelo Sicoob.');
        } catch (Throwable $erro) {
            report($erro);
            return back()->with('pix_erro', $erro->getMessage());
        }
    }

    private function cancelarPixPendente(Request $request, Atividade $atividade, FormularioInscricaoService $servico, IdentificacaoParticipanteService $identificacao, CancelamentoInscricaoService $cancelamento): RedirectResponse
    {
        $inscricao = $this->inscricaoPixAutorizada($request, $atividade, $servico, $identificacao);
        abort_if($inscricao->ativa || $inscricao->cancelada_em, 409, 'Esta inscrição não está aguardando pagamento.');

        try {
            $cancelamento->cancelar($inscricao, 'Cobrança PIX cancelada pelo participante antes da confirmação do pagamento.');
        } catch (PagamentoPixConfirmadoException $erro) {
            return back()->with('pix_erro', 'O QR Code não foi cancelado porque o pagamento já foi confirmado. Sua inscrição foi realizada.');
        } catch (Throwable $erro) {
            report($erro);
            return back()->with('pix_erro', 'Não foi possível cancelar o QR Code no Sicoob. Tente novamente.');
        }

        return back()->with('status', 'QR Code PIX cancelado. Revise as opções e envie o formulário novamente.');
    }

    private function inscricaoPixAutorizada(Request $request, Atividade $atividade, FormularioInscricaoService $servico, IdentificacaoParticipanteService $identificacao): InscricaoAtividade
    {
        $sessao = $identificacao->daSessao($request, $atividade);
        $participante = $identificacao->participanteDaSessao($request, $atividade);
        abort_unless($sessao && $participante, 403);
        $inscricao = $servico->inscricaoDoParticipante($atividade, $participante, $sessao['email'])
            ?? $servico->inscricaoPendenteDoParticipante($atividade, $participante, $sessao['email']);
        abort_unless($inscricao, 404);
        return $inscricao;
    }

    public function comprovantePdf(InscricaoAtividade $inscricao, ComprovanteInscricaoService $comprovante): Response
    {
        $inscricao->load('atividade.evento');
        $opcoes = new Options;
        $opcoes->set('isRemoteEnabled', false);
        $pdf = new Dompdf($opcoes);
        $pdf->loadHtml(view('atividades.comprovante-pdf', [
            'inscricao' => $inscricao,
            'dadosParticipante' => $comprovante->participante($inscricao),
            'respostas' => $comprovante->respostas($inscricao),
            'qrPresenca' => $comprovante->qrPresenca($inscricao),
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$comprovante->nomeArquivo($inscricao).'"',
            'Cache-Control' => 'no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function enviarComprovante(Request $request, Atividade $atividade, FormularioInscricaoService $formularios, IdentificacaoParticipanteService $identificacao, ComprovanteInscricaoService $comprovante, GiEmailService $email): RedirectResponse
    {
        [$inscricao, $sessao] = $this->inscricaoAutorizada($request, $atividade, $formularios, $identificacao);
        $limite = 'email-comprovante:'.$inscricao->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($limite, 3)) {
            return back()->with('comprovante_erro', 'Aguarde um minuto antes de enviar o comprovante novamente.');
        }
        RateLimiter::hit($limite, 60);
        $inscricao->load('atividade.evento');
        try {
            $email->enviar(
                $sessao['email'],
                $sessao['nome'] ?? null,
                'Comprovante de inscrição — '.$atividade->nome,
                view('atividades.comprovante-email', [
                    'inscricao' => $inscricao,
                    'dadosParticipante' => $comprovante->participante($inscricao),
                    'respostas' => $comprovante->respostas($inscricao),
                    'qrPresenca' => $comprovante->qrPresenca($inscricao),
                    'urlPdf' => route('inscricoes.comprovante.pdf', $inscricao->comprovante_hash),
                ])->render(),
                'comprovante-inscricao-'.$inscricao->id.'-'.bin2hex(random_bytes(8)),
            );
        } catch (Throwable $excecao) {
            report($excecao);
            return back()->with('comprovante_erro', 'Não foi possível enviar as respostas agora. Tente novamente em alguns instantes.');
        }

        return back()->with('status', 'As respostas foram enviadas para '.$sessao['email'].'.');
    }

    public function comprovantePix(Request $request, Atividade $atividade, PixCobranca $cobranca, FormularioInscricaoService $formularios, IdentificacaoParticipanteService $identificacao): Response
    {
        [$inscricao] = $this->inscricaoAutorizada($request, $atividade, $formularios, $identificacao);
        abort_unless(
            (int) $cobranca->inscricao_atividade_id === (int) $inscricao->id
            && $cobranca->status === 'CONCLUIDA'
            && $cobranca->pago_em,
            404,
        );
        $cobranca->load(['inscricao.atividade.evento', 'inscricao.participante']);

        $opcoes = new Options;
        $opcoes->set('isRemoteEnabled', false);
        $pdf = new Dompdf($opcoes);
        $pdf->loadHtml(view('atividades.comprovante-pix-pdf', compact('cobranca'))->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="comprovante-pix-'.$cobranca->id.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function statusPix(Request $request, Atividade $atividade, PixCobranca $cobranca, FormularioInscricaoService $formularios, IdentificacaoParticipanteService $identificacao, SicoobPixService $sicoob): JsonResponse
    {
        $inscricao = $this->inscricaoPixAutorizada($request, $atividade, $formularios, $identificacao);
        abort_unless((int) $cobranca->inscricao_atividade_id === (int) $inscricao->id, 404);

        $cobranca->refresh();
        // O webhook é o caminho principal. Esta consulta espaçada recupera notificações
        // perdidas sem fazer uma chamada bancária a cada polling de 5 segundos da tela.
        if (! $cobranca->pagamentoConfirmado()
            && Cache::add('pix-conciliacao-tela:'.$cobranca->id, true, now()->addSeconds(30))) {
            try {
                $cobranca = $sicoob->consultar($cobranca);
            } catch (Throwable $erro) {
                report($erro);
            }
        }

        return response()->json([
            'pago' => $cobranca->pagamentoConfirmado(),
            'status' => trim((string) $cobranca->status),
            'pago_em' => $cobranca->pago_em?->toIso8601String(),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function apagarInscricao(Request $request, Atividade $atividade, FormularioInscricaoService $formularios, IdentificacaoParticipanteService $identificacao, GiEmailService $email, CancelamentoInscricaoService $cancelamento): RedirectResponse
    {
        [$inscricao, $sessao] = $this->inscricaoAutorizada($request, $atividade, $formularios, $identificacao);
        if ($request->input('confirmacao') !== 'CANCELAR') {
            return back()->with('comprovante_erro', 'Marque a confirmação antes de cancelar a inscrição.');
        }
        try {
            $cancelamento->cancelar($inscricao, 'Cancelada pelo participante no formulário público.');
        } catch (PagamentoPixConfirmadoException $erro) {
            return back()->with('comprovante_erro', $erro->getMessage());
        } catch (Throwable $erro) {
            report($erro);
            return back()->with('comprovante_erro', 'A inscrição não foi cancelada porque não foi possível confirmar o cancelamento da cobrança no Sicoob. Tente novamente.');
        }

        $atividade->load('evento');
        $canceladaEm = now();
        $avisoPix = $atividade->temPagamentoPix()
            ? '<p>Caso tenha ocorrido cobrança PIX, os registros e a cobrança serão preservados para segurança e auditoria.</p>'
            : '';
        $avisoEmail = '';
        try {
            $email->enviar(
                $sessao['email'],
                $sessao['nome'] ?? null,
                'Inscrição cancelada — '.$atividade->nome,
                '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#22303f">'
                    .'<p>Olá!</p><p>Sua inscrição foi cancelada.</p>'
                    .'<p><strong>Atividade:</strong> '.e($atividade->nome).'<br>'
                    .'<strong>Evento:</strong> '.e($atividade->evento?->nome ?? 'Não informado').'<br>'
                    .'<strong>Data e hora:</strong> '.$canceladaEm->format('d/m/Y \à\s H:i:s').'</p>'
                    .$avisoPix.'</div>',
                'inscricao-cancelada-'.$inscricao->id.'-'.bin2hex(random_bytes(8)),
            );
        } catch (Throwable $excecao) {
            report($excecao);
            $avisoEmail = ' Não foi possível enviar o aviso por e-mail.';
        }

        return back()->with('status', 'Sua inscrição foi cancelada com segurança e a vaga foi liberada.'.$avisoEmail);
    }

    /** @return array{InscricaoAtividade, array<string, mixed>} */
    private function inscricaoAutorizada(Request $request, Atividade $atividade, FormularioInscricaoService $formularios, IdentificacaoParticipanteService $identificacao): array
    {
        abort_unless($atividade->ativo && $atividade->formulario, 404);
        $sessao = $identificacao->daSessao($request, $atividade);
        $participante = $sessao ? $identificacao->participanteDaSessao($request, $atividade) : null;
        abort_unless($sessao && $participante, 403, 'Sua sessão expirou. Entre novamente para acessar a inscrição.');
        $inscricao = $formularios->inscricaoDoParticipante($atividade, $participante, $sessao['email']);
        abort_unless($inscricao, 404);

        return [$inscricao, $sessao];
    }
    public function inscricoes(Request $request, Atividade $atividade, ArmazemService $armazem, InscricoesExportService $exportacao): View
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
        $query = InscricaoAtividade::query()->with('sessao')->withCount([
            'cobrancasPix as cobrancas_pix_confirmadas_count' => fn ($cobrancas) => $cobrancas->confirmadas(),
        ])->where('atividade_id', $atividade->id);

        if ($pesquisar !== '') {
            $participantes = Participante::query()->where('nome', 'like', "%{$pesquisar}%")
                ->limit(500)->pluck('id')->all();
            $query->where(function ($consulta) use ($pesquisar, $participantes): void {
                $consulta->where('participante_email', 'like', "%{$pesquisar}%")
                    ->orWhere('ip', 'like', "%{$pesquisar}%")
                    ->orWhere('resposta', 'like', "%{$pesquisar}%")
                    ->orWhereHas('sessao', fn ($sessao) => $sessao->where('nome', 'like', "%{$pesquisar}%"));
                if ($participantes !== []) $consulta->orWhereIn('participante_id', $participantes);
                if (ctype_digit($pesquisar)) $consulta->orWhere('id', (int) $pesquisar);
            });
        }

        $inscricoes = $query->latest()->paginate($porPagina, ['*'], 'page', $pagina)
            ->appends(['pesquisar' => $pesquisar]);
        $armazem->salvar($recurso, $request, $inscricoes->currentPage(), $pesquisar, $porPagina);

        return view('atividades.inscricoes', compact('atividade', 'inscricoes', 'pesquisar') + [
            'camposExportacao' => $exportacao->camposDisponiveis($atividade),
            'linksAutoPresenca' => $atividade->linksAutoPresenca()->latest()->get()->map(fn ($link) => [
                'id' => $link->id,
                'url' => route('auto-presenca.abrir', ['link' => $link->hash]),
                'inicio' => $link->inicio->format('d/m/Y H:i'),
                'fim' => $link->fim->format('d/m/Y H:i'),
                'ajuste_minutos' => $link->ajuste_minutos,
                'cliques' => $link->cliques,
                'ajustar_url' => route('atividades.auto-presenca.ajustar', [$atividade, $link]),
                'excluir_url' => route('atividades.auto-presenca.excluir', [$atividade, $link]),
            ])->values(),
        ]);
    }

    public function excluirInscricao(
        Request $request,
        Atividade $atividade,
        InscricaoAtividade $inscricao,
        GiPermissionService $permissoes,
        HistoricoService $historico,
        CancelamentoInscricaoService $cancelamento,
    ): JsonResponse {
        $permissoes->exigir('atividades.inscricoes.excluir', $request);
        $request->validate(
            ['confirmacao' => ['required', 'accepted']],
            ['confirmacao.accepted' => 'Marque que tem certeza antes de cancelar a inscrição.'],
        );
        abort_unless((int) $inscricao->atividade_id === (int) $atividade->id, 404);

        try {
            $cancelamento->cancelar($inscricao, 'Cancelada administrativamente.');
        } catch (PagamentoPixConfirmadoException $erro) {
            abort(409, $erro->getMessage());
        } catch (Throwable $erro) {
            report($erro);
            abort(502, 'A inscrição não foi cancelada porque não foi possível confirmar o cancelamento da cobrança no Sicoob.');
        }

        $historico->atividade($atividade, 'Inscrição cancelada', [
            'inscricao_id' => $inscricao->id,
            'participante_id' => $inscricao->participante_id,
            'participante_email' => $inscricao->participante_email,
            'sessao_atividade_id' => $inscricao->sessao_atividade_id,
            'lista_reserva' => (bool) $inscricao->lista_reserva,
            'inscrita_em' => $inscricao->created_at?->format('d/m/Y H:i:s'),
        ], $request);

        return response()->json(['message' => 'Inscrição cancelada com segurança.']);
    }

    /**
     * URL assinada e temporaria da planilha de respostas.
     *
     * O download acontece por navegacao para esta URL, e nao por fetch mais blob: dentro
     * do iframe do GI o blob nao chega ao visitante. A assinatura tambem dispensa o cookie
     * de sessao, que um navegador pode recusar num iframe de outro dominio.
     */
    public function exportarLink(Request $request, Atividade $atividade, string $formato, InscricoesExportService $exportacao): JsonResponse
    {
        abort_unless(in_array($formato, ['csv', 'ods', 'xls', 'xlsx'], true), 404);
        $dados = $request->validate(['campos' => ['required', 'array', 'min:1'], 'campos.*' => ['required', 'string', 'max:180']]);
        $permitidos = collect($exportacao->camposDisponiveis($atividade))->pluck('chave')->all();
        $campos = array_values(array_unique(array_filter($dados['campos'], fn ($campo) => in_array($campo, $permitidos, true))));
        abort_if($campos === [], 422, 'Selecione pelo menos um campo para exportar.');

        return response()->json([
            'url' => URL::temporarySignedRoute('atividades.inscricoes.exportar', now()->addMinutes(10), [
                'atividade' => $atividade, 'formato' => $formato, 'campos' => $campos,
            ]),
        ]);
    }

    public function exportarInscricoes(Request $request, Atividade $atividade, string $formato, InscricoesExportService $exportacao)
    {
        return $exportacao->download($atividade, $formato, array_values((array) $request->query('campos', [])));
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
    public function previewLink(Atividade $atividade): JsonResponse { return response()->json(['url' => $atividade->urlPublica()]); }
    public function update(Request $request, Atividade $atividade, HistoricoService $historico): RedirectResponse
    {
        $campos = ['mostrar_link_evento', 'tipo', 'formato', 'categoria_id', 'nome', 'palestrante', 'ativo', 'evento_id', 'modalidade', 'tipo_link_transmissao', 'link_transmissao', 'data_inicio', 'data_fim', 'personalizacao'];
        $arquivosAnteriores = collect(['imagem', 'imagem_fundo_card', 'imagem_fundo_pagina'])
            ->map(fn ($chave) => $atividade->personalizacao[$chave] ?? null)->filter()->all();
        $antes = $atividade->only($campos); $dados = $this->validar($request, $atividade); $sessoes = $dados['sessoes'] ?? []; unset($dados['sessoes']);
        DB::transaction(function () use ($atividade, $dados, $sessoes): void {
            $atividade->update($dados);
            $this->sincronizarSessoes($atividade, $sessoes);
        });
        $personalizacaoAtual = $atividade->fresh()->personalizacao ?? [];
        $arquivosAtuais = array_filter([$personalizacaoAtual['imagem'] ?? null, $personalizacaoAtual['imagem_fundo_card'] ?? null, $personalizacaoAtual['imagem_fundo_pagina'] ?? null]);
        foreach (array_diff($arquivosAnteriores, $arquivosAtuais) as $arquivoAnterior) {
            if (preg_match('/^[a-f0-9-]{36}\.(?:jpe?g|png|webp)$/i', $arquivoAnterior)) {
                File::delete(storage_path('app/public/personalizacao/'.$arquivoAnterior));
            }
        }
        $mudancas = $historico->alteracoes($antes, $atividade->only($campos));
        if ($mudancas !== []) $historico->atividade($atividade, 'Atividade alterada', $mudancas, $request);
        return redirect()->route('atividades.index')->with('status', 'Atividade atualizada com sucesso.');
    }
    public function destroy(Request $request, Atividade $atividade, HistoricoService $historico): JsonResponse
    {
        if ($atividade->temInscricoes()) {
            return response()->json(['message' => $this->motivoExclusaoBloqueada($atividade)], 409);
        }

        $historico->atividade($atividade, 'Atividade excluída', $atividade->only(['id','nome','ativo','evento_id']), $request);
        $atividade->delete();

        return response()->json(['message' => 'Atividade excluída com sucesso.']);
    }
    public function restore(Request $request, int $atividade, HistoricoService $historico): JsonResponse { $item=Atividade::onlyTrashed()->findOrFail($atividade); $item->restore(); $historico->atividade($item, 'Atividade restaurada', $item->only(['id','nome','ativo','evento_id']), $request); return response()->json(['message'=>'Atividade restaurada com sucesso.']); }
    public function forceDestroy(Request $request, int $atividade, HistoricoService $historico): JsonResponse
    {
        $item = Atividade::onlyTrashed()->findOrFail($atividade);
        if ($item->temInscricoes()) {
            return response()->json(['message' => $this->motivoExclusaoBloqueada($item)], 409);
        }

        $historico->atividade($item, 'Atividade excluída definitivamente', $item->only(['id','nome','ativo','evento_id']), $request);
        $item->forceDelete();

        return response()->json(['message'=>'Atividade excluída definitivamente.']);
    }

    private function motivoExclusaoBloqueada(Atividade $atividade): string
    {
        $total = $atividade->inscricoes()->count();

        return "Esta atividade possui {$total} participante(s) inscrito(s) e não pode ser excluída.";
    }
    public function historico(Request $request, int $atividade): JsonResponse
    {
        $query=HistoricoAtividade::query()->where('atividade_id',$atividade); $total=$query->count(); $inicio=max(0,(int)$request->input('start')); $tamanho=min(100,max(1,(int)$request->input('length',10)));
        $dados=$query->latest('data_hora')->latest('id')->skip($inicio)->take($tamanho)->get()->values()->map(fn($item,$i)=>['numero'=>$total-$inicio-$i,'historico'=>e($item->historico),'usuario'=>$item->usuario??'—','dados'=>view('partials.historico-dados',['dados'=>$item->dados??[]])->render(),'data_hora'=>$item->data_hora?->format('d/m/Y H:i:s')??'—']);
        return response()->json(['draw'=>(int)$request->input('draw'),'recordsTotal'=>$total,'recordsFiltered'=>$total,'data'=>$dados]);
    }
    private function validar(Request $request, ?Atividade $atividade = null): array
    {
        $permissoes = app(GiPermissionService::class);
        $podePersonalizarLegado = $permissoes->permite('atividades.personalizar', $request);
        $podeEditarFundoPagina = $podePersonalizarLegado || $permissoes->permite('atividades.fundo_pagina.editar', $request);
        $podeEditarPersonalizacao = $podePersonalizarLegado || $permissoes->permite('atividades.personalizacao.editar', $request);
        $podePersonalizar = $podeEditarFundoPagina || $podeEditarPersonalizacao;
        $ignorarPersonalizacao = \Illuminate\Validation\Rule::excludeIf(!$podePersonalizar);
        $ignorarFundoPagina = \Illuminate\Validation\Rule::excludeIf(!$podeEditarFundoPagina);
        $ignorarEstiloAtividade = \Illuminate\Validation\Rule::excludeIf(!$podeEditarPersonalizacao);
        $request->mergeIfMissing(['tipo' => $atividade?->tipo ?? 'somente_inscricao']);
        $request->mergeIfMissing(['formato' => $atividade?->formato ?? 'simples']);

        if ($podePersonalizar) {
            // Campos que o perfil não pode editar sempre vêm do modelo, nunca do payload.
            // Isso também torna seguro um POST montado manualmente fora da interface.
            $personalizacao = (array) $request->input('personalizacao', []);
            $salva = ($atividade ?? new Atividade)->estiloImagem();
            $camposFundoPagina = ['alterar_cor_fundo_pagina', 'cor_fundo_pagina', 'fundo_pagina_tipo'];
            $camposEstiloAtividade = ['posicao', 'borda', 'cor_borda', 'usar_formatacao_evento', 'tipo', 'degrade_inicio', 'degrade_fim', 'cor_solida', 'cor_fonte', 'cor_borda_card'];
            if (! $podeEditarFundoPagina) {
                foreach ($camposFundoPagina as $campo) $personalizacao[$campo] = $salva[$campo];
            }
            if (! $podeEditarPersonalizacao) {
                foreach ($camposEstiloAtividade as $campo) $personalizacao[$campo] = $salva[$campo];
            }
            $request->merge(['personalizacao' => $personalizacao]);
        }

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'in:somente_inscricao,atividade_evento'],
            'formato' => ['required', 'in:simples,com_sessoes'],
            'ativo' => ['required', 'boolean'],
            'mostrar_link_evento' => ['sometimes', 'boolean'],
            'evento_id' => ['required', 'integer', 'exists:eventos,id'],
            'categoria_id' => ['exclude_if:tipo,somente_inscricao', 'nullable', 'integer', 'exists:categorias,id'],
            'modalidade' => ['exclude_if:tipo,somente_inscricao', 'nullable', 'in:ead,presencial'],
            'tipo_link_transmissao' => ['exclude_unless:modalidade,ead', 'nullable', 'in:nao_usar,link,iframe'],
            'link_transmissao' => ['exclude_unless:modalidade,ead', 'nullable', 'string', 'max:10000', function (string $atributo, mixed $valor, \Closure $falhar) use ($request): void {
                if (blank($valor)) return;
                if ($request->input('tipo_link_transmissao') === 'link'
                    && (! filter_var($valor, FILTER_VALIDATE_URL) || ! in_array(strtolower((string) parse_url($valor, PHP_URL_SCHEME)), ['http', 'https'], true))) {
                    $falhar('Informe um link HTTP ou HTTPS válido.');
                }
                if ($request->input('tipo_link_transmissao') === 'iframe') {
                    $teste = new Atividade(['tipo_link_transmissao' => 'iframe', 'link_transmissao' => $valor]);
                    if (! $teste->iframeTransmissaoSeguro()) $falhar('Informe somente um iframe com endereço HTTP ou HTTPS válido.');
                }
            }],
            'local' => ['exclude_if:tipo,somente_inscricao', 'nullable', 'string', 'max:255'],
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
            'sessoes' => ['exclude_unless:formato,com_sessoes', 'required_if:formato,com_sessoes', 'array', 'min:1', 'max:100'],
            'sessoes.*.id' => ['nullable', 'integer'],
            'sessoes.*.nome' => ['required', 'string', 'max:255'],
            'sessoes.*.data_inicio' => ['required', 'date'],
            'sessoes.*.data_fim' => ['required', 'date', 'after_or_equal:sessoes.*.data_inicio'],
            'sessoes.*.limite_vagas' => ['nullable', 'integer', 'min:1'],
            'personalizacao' => [$ignorarPersonalizacao, 'required', 'array:posicao,borda,cor_borda,usar_formatacao_evento,tipo,degrade_inicio,degrade_fim,cor_solida,cor_fonte,cor_borda_card,alterar_cor_fundo_pagina,cor_fundo_pagina,fundo_pagina_tipo'],
            'personalizacao.posicao' => [$ignorarPersonalizacao, 'required', 'in:esquerda,direita'],
            'personalizacao.borda' => [$ignorarPersonalizacao, 'required', 'boolean'],
            'personalizacao.cor_borda' => [$ignorarPersonalizacao, 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'personalizacao.usar_formatacao_evento' => [$ignorarPersonalizacao, 'required', 'boolean'],
            'personalizacao.tipo' => [$ignorarPersonalizacao, 'required', 'in:degrade,solida,imagem,transparente,transparente_borda'],
            'personalizacao.degrade_inicio' => [$ignorarPersonalizacao, 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'personalizacao.degrade_fim' => [$ignorarPersonalizacao, 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'personalizacao.cor_solida' => [$ignorarPersonalizacao, 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'personalizacao.cor_fonte' => [$ignorarPersonalizacao, 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'personalizacao.cor_borda_card' => [$ignorarPersonalizacao, 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'personalizacao.alterar_cor_fundo_pagina' => [$ignorarPersonalizacao, 'required', 'boolean'],
            'personalizacao.cor_fundo_pagina' => [$ignorarPersonalizacao, 'required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'personalizacao.fundo_pagina_tipo' => [$ignorarPersonalizacao, 'required', 'in:cor,imagem'],
            'imagem_atividade' => [$ignorarEstiloAtividade, 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=12000,max_height=12000'],
            'imagem_fundo_card_atividade' => [$ignorarEstiloAtividade, 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=12000,max_height=12000'],
            'imagem_fundo_pagina_atividade' => [$ignorarFundoPagina, 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=12000,max_height=12000'],
            'remover_imagem_atividade' => [$ignorarEstiloAtividade, 'nullable', 'boolean'],
            'remover_imagem_fundo_card_atividade' => [$ignorarEstiloAtividade, 'nullable', 'boolean'],
            'remover_imagem_fundo_pagina_atividade' => [$ignorarFundoPagina, 'nullable', 'boolean'],
        ]);

        if ($dados['tipo'] === 'somente_inscricao') {
            $dados['categoria_id'] = $dados['modalidade'] = $dados['tipo_link_transmissao'] = $dados['link_transmissao'] = $dados['local'] = null;
        } elseif (array_key_exists('local', $dados)) {
            $dados['local'] = trim((string) $dados['local']) ?: null;
        }

        if (($dados['modalidade'] ?? null) !== 'ead' || ($dados['tipo_link_transmissao'] ?? null) === 'nao_usar') {
            $dados['tipo_link_transmissao'] = $dados['link_transmissao'] = null;
        } elseif (isset($dados['link_transmissao'])) {
            $dados['link_transmissao'] = trim((string) $dados['link_transmissao']) ?: null;
        }

        if (($dados['formato'] ?? 'simples') === 'com_sessoes' && $atividade) {
            $ids = collect($dados['sessoes'] ?? [])->pluck('id')->filter()->map(fn ($id) => (int) $id);
            if ($ids->count() !== $ids->unique()->count()
                || $atividade->sessoes()->withTrashed()->whereIn('id', $ids)->count() !== $ids->count()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['sessoes' => 'Uma das sessões informadas não pertence a esta atividade.']);
            }
        }

        if (!$podePersonalizar) return $dados;

        foreach (['usar_formatacao_evento', 'alterar_cor_fundo_pagina', 'borda'] as $booleano) {
            $dados['personalizacao'][$booleano] = (bool) $dados['personalizacao'][$booleano];
        }
        foreach (['cor_borda', 'degrade_inicio', 'degrade_fim', 'cor_solida', 'cor_fonte', 'cor_borda_card', 'cor_fundo_pagina'] as $cor) {
            $dados['personalizacao'][$cor] = strtolower($dados['personalizacao'][$cor]);
        }
        $dados['personalizacao']['imagem'] = $podeEditarPersonalizacao && $request->boolean('remover_imagem_atividade')
            ? null
            : $atividade?->estiloImagem()['imagem'];
        $dados['personalizacao']['imagem_fundo_card'] = $podeEditarPersonalizacao && $request->boolean('remover_imagem_fundo_card_atividade')
            ? null
            : ($atividade?->personalizacao['imagem_fundo_card'] ?? null);
        $dados['personalizacao']['imagem_fundo_pagina'] = $podeEditarFundoPagina && $request->boolean('remover_imagem_fundo_pagina_atividade')
            ? null
            : ($atividade?->personalizacao['imagem_fundo_pagina'] ?? null);
        foreach (['imagem_atividade' => 'imagem', 'imagem_fundo_card_atividade' => 'imagem_fundo_card', 'imagem_fundo_pagina_atividade' => 'imagem_fundo_pagina'] as $campo => $chave) {
            if ($campo === 'imagem_fundo_pagina_atividade' ? ! $podeEditarFundoPagina : ! $podeEditarPersonalizacao) continue;
            $arquivo = $request->file($campo);
            if (! $arquivo) continue;
            $nome = \Illuminate\Support\Str::uuid().'.'.$arquivo->extension();
            \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('app/public/personalizacao'));
            $arquivo->move(storage_path('app/public/personalizacao'), $nome);
            $dados['personalizacao'][$chave] = $nome;
        }
        if ($podeEditarPersonalizacao
            && ! $dados['personalizacao']['usar_formatacao_evento']
            && $dados['personalizacao']['tipo'] === 'imagem'
            && ! $dados['personalizacao']['imagem_fundo_card']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['imagem_fundo_card_atividade' => 'Selecione uma imagem para o fundo do card de título.']);
        }
        if ($podeEditarFundoPagina
            && $dados['personalizacao']['alterar_cor_fundo_pagina']
            && $dados['personalizacao']['fundo_pagina_tipo'] === 'imagem'
            && ! $dados['personalizacao']['imagem_fundo_pagina']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['imagem_fundo_pagina_atividade' => 'Selecione uma imagem para o fundo da página.']);
        }
        unset($dados['imagem_atividade'], $dados['imagem_fundo_card_atividade'], $dados['imagem_fundo_pagina_atividade'],
            $dados['remover_imagem_atividade'], $dados['remover_imagem_fundo_card_atividade'], $dados['remover_imagem_fundo_pagina_atividade']);

        return $dados;
    }

    private function sincronizarSessoes(Atividade $atividade, array $sessoes): void
    {
        // No formato simples as sessoes antigas ficam guardadas, mas deixam de participar
        // das inscricoes. Assim uma troca acidental de formato nao apaga dados historicos.
        if (! $atividade->comSessoes()) return;

        $mantidas = [];
        foreach (array_values($sessoes) as $ordem => $dados) {
            $id = isset($dados['id']) ? (int) $dados['id'] : null;
            $sessao = $id ? $atividade->sessoes()->withTrashed()->findOrFail($id) : $atividade->sessoes()->make();
            if ($sessao->trashed()) $sessao->restore();
            $sessao->fill([
                'nome' => trim($dados['nome']),
                'data_inicio' => $dados['data_inicio'] ?? null,
                'data_fim' => $dados['data_fim'] ?? null,
                'limite_vagas' => $dados['limite_vagas'] ?? null,
                'ativo' => true,
                'ordem' => $ordem,
            ])->save();
            $mantidas[] = $sessao->id;
        }

        $atividade->sessoes()->whereNotIn('id', $mantidas)->each(fn ($sessao) => $sessao->delete());

        // Mantem as colunas historicas preenchidas com o intervalo completo. Listagens,
        // templates e integracoes antigas continuam entendendo a agenda da atividade.
        $atividade->update([
            'data_inicio' => $atividade->sessoes()->min('data_inicio'),
            'data_fim' => $atividade->sessoes()->max('data_fim'),
        ]);
    }
}
