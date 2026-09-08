<?php

namespace App\Http\Controllers;

use App\Models\Evento;
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
        $evento->load('templatePagina');

        return view('eventos.pagina', [
            'evento' => $evento,
            'templates' => TemplatePagina::query()->where('ativo', true)
                ->orWhereKey($evento->template_pagina_id)->orderBy('nome')->get(),
            'variaveis' => $evento->templatePagina?->variaveis ?? [],
            'valores' => (array) ($evento->pagina_variaveis ?? []),
            'arquivos' => $evento->templatePagina ? $this->templates->arquivos($evento->templatePagina) : [],
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
                view('eventos.pagina-sem-template', ['evento' => $evento])->render(),
            );
        }

        return $this->respostaIncorporavel($this->renderer->renderizar($evento));
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
