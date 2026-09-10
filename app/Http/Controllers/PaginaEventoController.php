<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Atividade;
use App\Models\Convidado;
use App\Models\PaginaPadraoEvento;
use App\Models\TemplatePagina;
use App\Services\PaginaEventoRenderer;
use App\Services\TemplatePaginaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pagina publica de um evento: escolha do template e das variaveis, e a exibicao.
 *
 * A exibicao devolve o HTML do template puro, sem o layout do sistema, para acesso
 * público direto ou incorporação em sites externos.
 */
class PaginaEventoController
{
    public function __construct(
        private readonly TemplatePaginaService $templates,
        private readonly PaginaEventoRenderer $renderer,
    ) {}

    public function editar(Evento $evento): View
    {
        // Templates versionados no projeto podem ter recebido novas variáveis desde
        // que foram registrados no banco. Atualiza apenas os metadados do template;
        // os valores já escolhidos pelo evento permanecem em pagina_variaveis.
        $this->templates->sincronizar();
        $evento->load(['templatePagina', 'submissoes']);

        $paginaPadrao = $evento->paginaPadrao;

        return view('eventos.pagina', [
            'evento' => $evento,
            'templates' => TemplatePagina::query()->where('ativo', true)
                ->orWhereKey($evento->template_pagina_id)->orderBy('nome')->get(),
            'variaveis' => $evento->templatePagina?->variaveis ?? [],
            'valores' => (array) ($evento->pagina_variaveis ?? []),
            'arquivos' => $evento->templatePagina ? $this->templates->arquivos($evento->templatePagina) : [],
            'configuracaoPadrao' => $paginaPadrao?->configuracaoCompleta() ?? PaginaPadraoEvento::padrao(),
            'submissoes' => $evento->submissoes,
        ]);
    }

    public function salvar(Request $request, Evento $evento): RedirectResponse
    {
        $dados = $request->validate(
            ['template_pagina_id' => ['nullable', 'integer', 'exists:templates_pagina,id']],
            [],
            ['template_pagina_id' => 'template'],
        );

        $template = $dados['template_pagina_id'] ? TemplatePagina::find($dados['template_pagina_id']) : null;

        if (! $template) {
            $evento->update(['template_pagina_id' => null, 'pagina_variaveis' => null]);
            $configuracoes = $this->validarPaginaPadrao($request, $evento);
            PaginaPadraoEvento::updateOrCreate(['evento_id' => $evento->id], ['configuracoes' => $configuracoes]);

            return back()->with('status', 'Configuração da página padrão salva com sucesso.');
        }

        // So as variaveis que o template declara sao gravadas: o formulario nao pode
        // virar porta para guardar chave arbitraria no JSON do evento.
        $valores = [];
        foreach ($template?->variaveis ?? [] as $variavel) {
            $valores[$variavel['nome']] = mb_substr((string) $request->input('variaveis.'.$variavel['nome'], ''), 0, 2000);
        }

        $evento->update([
            'template_pagina_id' => $template?->id,
            'pagina_variaveis' => $valores === [] ? null : $valores,
        ]);

        return back()->with('status', 'Página do evento salva com sucesso.');
    }

    /**
     * HTML da pagina, sem o layout do sistema.
     *
     * Rota separada da edição porque é pública, enquanto a configuração continua
     * protegida pelas permissões do GI.
     */
    public function visualizar(Evento $evento): Response
    {
        $evento->load('templatePagina');

        abort_unless($evento->ativo, 404, 'Este evento não está ativo.');

        if ($evento->template_pagina_id === null) {
            return $this->respostaIncorporavel(
                view('eventos.pagina-sem-template', [
                    'evento' => $evento,
                    'configuracao' => $evento->paginaPadrao?->configuracaoCompleta() ?? PaginaPadraoEvento::padrao(),
                    'submissoes' => $evento->submissoes()->where('ativo', true)->orderBy('id')->get(),
                    'atividades' => Atividade::query()->with('categoria')->where('evento_id', $evento->id)->where('ativo', true)->orderByRaw('data_inicio is null, data_inicio')->orderBy('nome')->get(),
                    'convidados' => Convidado::query()->whereHas('eventos', fn ($query) => $query->where('eventos.id', $evento->id))->orderBy('nome')->get(),
                ])->render(),
            );
        }

        return $this->respostaIncorporavel($this->renderer->renderizar($evento));
    }

    private function validarPaginaPadrao(Request $request, Evento $evento): array
    {
        $dados = $request->validate([
            'pagina_padrao' => ['required', 'array'],
            'pagina_padrao.layout' => ['required', 'in:fixo,fluido'],
            'pagina_padrao.card_tipo' => ['required', 'in:solida,degrade'],
            'pagina_padrao.card_solida' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'pagina_padrao.cabecalho_tipo' => ['required', 'in:degrade,solida,imagem'],
            'pagina_padrao.cabecalho_inicio' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'pagina_padrao.cabecalho_fim' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'pagina_padrao.cabecalho_solida' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'pagina_padrao.cabecalho_fonte' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'pagina_padrao.mostrar_imagem_evento' => ['nullable', 'boolean'],
            'pagina_padrao.altura_imagem_evento' => ['required', 'integer', 'min:100', 'max:700'],
            'pagina_padrao.conteudo_html' => ['nullable', 'string', 'max:500000'],
            'pagina_padrao.conteudo_altura' => ['required', 'in:auto,fixa'],
            'pagina_padrao.conteudo_altura_px' => ['required', 'integer', 'min:160', 'max:3000'],
            'pagina_padrao.cronograma_mostrar' => ['nullable', 'boolean'],
            'pagina_padrao.cronograma_json' => ['nullable', 'string', 'max:50000'],
            'pagina_padrao.participantes_mostrar' => ['nullable', 'boolean'],
            'pagina_padrao.participantes_foto' => ['nullable', 'boolean'],
            'pagina_padrao.participantes_redes' => ['nullable', 'boolean'],
            'pagina_padrao.participantes_curriculo' => ['nullable', 'boolean'],
            'pagina_padrao.participantes_colunas' => ['required', 'integer', 'in:2,3,4'],
            'pagina_padrao.atividades_mostrar' => ['nullable', 'boolean'],
            'pagina_padrao.atividades_agrupar' => ['nullable', 'boolean'],
            'pagina_padrao.atividades_inscricao' => ['nullable', 'boolean'],
            'pagina_padrao.cards_json' => ['nullable', 'string', 'max:200000'],
            'pagina_padrao.submissoes' => ['nullable', 'array'],
            'pagina_padrao.submissoes.*' => ['nullable', 'array'],
            'pagina_padrao.submissoes.*.titulo_html' => ['nullable', 'string', 'max:500000'],
            'pagina_padrao.submissoes.*.descricao_html' => ['nullable', 'string', 'max:500000'],
            'pagina_padrao.submissoes.*.fundo_tipo' => ['nullable', 'in:solida,degrade'],
            'pagina_padrao.submissoes.*.fundo_inicio' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'pagina_padrao.submissoes.*.fundo_fim' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'pagina_padrao.submissoes.*.fundo_solida' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'pagina_padrao.submissoes.*.fonte' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'pagina_padrao.submissoes.*.botao_texto' => ['nullable', 'string', 'max:100'],
            'pagina_padrao.submissoes.*.botao_fundo' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'pagina_padrao.submissoes.*.botao_fonte' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'pagina_padrao.faq_mostrar' => ['nullable', 'boolean'],
            'pagina_padrao.faq_json' => ['nullable', 'string', 'max:100000'],
            'imagem_pagina_cabecalho' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'imagem_pagina_evento' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ])['pagina_padrao'];

        $atual = $evento->paginaPadrao?->configuracaoCompleta() ?? PaginaPadraoEvento::padrao();
        foreach ([['imagem_pagina_cabecalho', 'cabecalho', 'imagem'], ['imagem_pagina_evento', null, 'imagem_evento']] as [$campo, $grupo, $chave]) {
            $nome = $grupo ? ($atual[$grupo][$chave] ?? null) : ($atual[$chave] ?? null);
            if ($arquivo = $request->file($campo)) {
                $nome = \Illuminate\Support\Str::uuid().'.'.$arquivo->extension();
                $arquivo->move(storage_path('app/public/personalizacao'), $nome);
            }
            if ($grupo) $atual[$grupo][$chave] = $nome;
            else $atual[$chave] = $nome;
        }

        $json = static function (?string $valor): array {
            if (! $valor) return [];
            $decodificado = json_decode($valor, true);
            return is_array($decodificado) ? array_values($decodificado) : [];
        };

        return [
            'layout' => $dados['layout'],
            'cabecalho' => ['tipo' => $dados['cabecalho_tipo'], 'inicio' => $dados['cabecalho_inicio'], 'fim' => $dados['cabecalho_fim'], 'solida' => $dados['cabecalho_solida'], 'fonte' => $dados['cabecalho_fonte'], 'imagem' => $atual['cabecalho']['imagem']],
            'card' => ['tipo' => $dados['card_tipo'], 'inicio' => $atual['card']['inicio'], 'fim' => $atual['card']['fim'], 'solida' => $dados['card_solida']],
            'mostrar_imagem_evento' => isset($dados['mostrar_imagem_evento']),
            'altura_imagem_evento' => (int) $dados['altura_imagem_evento'],
            'imagem_evento' => $atual['imagem_evento'],
            'conteudo' => ['html' => $dados['conteudo_html'] ?? '', 'altura' => $dados['conteudo_altura'], 'altura_px' => (int) $dados['conteudo_altura_px']],
            'cronograma' => ['mostrar' => isset($dados['cronograma_mostrar']), 'etapas' => $json($dados['cronograma_json'] ?? null)],
            'participantes' => ['mostrar' => isset($dados['participantes_mostrar']), 'foto' => isset($dados['participantes_foto']), 'redes' => isset($dados['participantes_redes']), 'curriculo' => isset($dados['participantes_curriculo']), 'colunas' => (int) $dados['participantes_colunas']],
            'atividades' => ['mostrar' => isset($dados['atividades_mostrar']), 'agrupar_categoria' => isset($dados['atividades_agrupar']), 'mostrar_inscricao' => isset($dados['atividades_inscricao'])],
            'cards' => $json($dados['cards_json'] ?? null),
            'submissoes' => $this->configurarSubmissoes($evento, $dados['submissoes'] ?? []),
            'faq' => ['mostrar' => isset($dados['faq_mostrar']), 'itens' => $json($dados['faq_json'] ?? null)],
        ];
    }

    private function configurarSubmissoes(Evento $evento, array $salvas): array
    {
        return $evento->submissoes()->orderBy('id')->get()->map(function ($submissao) use ($salvas): array {
            $salva = (array) ($salvas[(string) $submissao->id] ?? $salvas[$submissao->id] ?? []);
            $padrao = PaginaPadraoEvento::submissaoPadrao($submissao->id, $submissao->titulo);
            $cor = fn (string $chave): string => is_string($salva[$chave] ?? null) && preg_match('/^#[0-9a-fA-F]{6}$/', $salva[$chave])
                ? $salva[$chave] : $padrao[$chave];

            return [
                'submissao_id' => $submissao->id,
                'titulo_html' => (string) ($salva['titulo_html'] ?? $padrao['titulo_html']),
                'descricao_html' => (string) ($salva['descricao_html'] ?? $padrao['descricao_html']),
                'fundo_tipo' => in_array($salva['fundo_tipo'] ?? null, ['solida', 'degrade'], true) ? $salva['fundo_tipo'] : 'solida',
                'fundo_inicio' => $cor('fundo_inicio'), 'fundo_fim' => $cor('fundo_fim'),
                'fundo_solida' => $cor('fundo_solida'), 'fonte' => $cor('fonte'),
                'botao_texto' => mb_substr((string) ($salva['botao_texto'] ?? $padrao['botao_texto']), 0, 100),
                'botao_fundo' => $cor('botao_fundo'), 'botao_fonte' => $cor('botao_fonte'),
            ];
        })->values()->all();
    }

    /**
     * A página publicada é feita para incorporação por qualquer site, inclusive
     * WordPress em outro domínio. Estes cabeçalhos ficam também no controller para
     * que a rota continue incorporável mesmo se o middleware geral mudar no futuro.
     */
    private function respostaIncorporavel(string $html): Response
    {
        $resposta = response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "frame-ancestors *; object-src 'none'; base-uri 'self'",
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        // SAMEORIGIN e DENY impediriam o carregamento em WordPress de outro domínio.
        $resposta->headers->remove('X-Frame-Options');

        return $resposta;
    }
}
