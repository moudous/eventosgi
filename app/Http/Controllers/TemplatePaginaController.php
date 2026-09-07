<?php

namespace App\Http\Controllers;

use App\Models\TemplatePagina;
use App\Services\GiPermissionService;
use App\Services\TemplatePaginaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/** Catálogo dos templates de página de evento, importados por ZIP. */
class TemplatePaginaController
{
    public function __construct(private readonly TemplatePaginaService $servico) {}

    public function index(GiPermissionService $permissoes): View
    {
        // Templates versionados junto do codigo entram sozinhos no catalogo.
        $this->servico->sincronizar();

        return view('templates.index', [
            'permissoes' => $permissoes,
            'templates' => TemplatePagina::query()
                ->withCount(['eventos' => fn ($consulta) => $consulta->withTrashed()])
                ->orderBy('nome')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(
            ['pacote' => ['required', 'file', 'max:20480']],
            ['pacote.required' => 'Escolha o arquivo ZIP do template.', 'pacote.max' => 'O pacote não pode passar de 20 MB.'],
            ['pacote' => 'pacote'],
        );

        try {
            $template = $this->servico->importar($request->file('pacote'), $request->session()->get('gi_context.usuario.id'));
        } catch (RuntimeException $excecao) {
            return back()->withErrors(['pacote' => $excecao->getMessage()]);
        }

        return back()->with('status', "Template \"{$template->nome}\" importado com sucesso.");
    }

    public function destroy(TemplatePagina $template): RedirectResponse
    {
        // Regra do pedido: template usado por algum evento nao sai.
        if ($template->temEventos()) {
            $total = $template->eventos()->withTrashed()->count();

            return back()->withErrors(['pacote' => "Este template está em uso por {$total} evento".($total === 1 ? '' : 's').' e não pode ser removido.']);
        }

        $nome = $template->nome;
        $this->servico->remover($template);
        $template->delete();

        return back()->with('status', "Template \"{$nome}\" removido.");
    }

    /** Baixa o template empacotado, para levar a outra instalação ou guardar. */
    public function exportar(TemplatePagina $template): Response
    {
        $arquivo = $template->pasta.($template->versao ? '.'.$template->versao : '').'.zip';

        return response($this->servico->zip($template), 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $arquivo, Str::ascii($arquivo)),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Entrega css, js, imagens e fontes do template.
     *
     * Rota publica de proposito: a pagina do evento e publica, e o navegador de quem a
     * abre precisa dos arquivos. O servico so devolve extensoes de uma lista fechada, e
     * nunca o index.html, que so sai renderizado.
     */
    public function asset(TemplatePagina $template, string $caminho): Response
    {
        $asset = $this->servico->asset($template, $caminho);

        abort_unless($asset, 404);

        return response($asset['conteudo'], 200, [
            'Content-Type' => $asset['mime'],
            'Cache-Control' => 'public, max-age=3600',
            // O conteudo veio de um pacote enviado por alguem: sem o nosniff, um arquivo
            // com extensao inocente poderia ser interpretado como outra coisa.
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
