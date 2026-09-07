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
 * A exibicao devolve o HTML do template puro, sem o layout do sistema -- e assim que ela
 * podera ser embutida no WordPress por shortcode mais adiante.
 */
class PaginaEventoController
{
    public function __construct(
        private readonly TemplatePaginaService $templates,
        private readonly PaginaEventoRenderer $renderer,
    ) {}

    public function editar(Evento $evento): View
    {
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
     * Rota separada da edicao porque e ela que o botao "Visualizar página" abre em outra
     * janela, e sera a mesma que o shortcode do WordPress vai consumir.
     */
    public function visualizar(Evento $evento): Response
    {
        $evento->load('templatePagina');

        if ($evento->template_pagina_id === null) {
            return response(view('eventos.pagina-sem-template', ['evento' => $evento])->render(), 200)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        return response($this->renderer->renderizar($evento), 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
