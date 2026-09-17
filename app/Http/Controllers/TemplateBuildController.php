<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\TemplatePagina;
use App\Services\PaginaEventoRenderer;
use App\Services\TemplateBuildService;
use Illuminate\Http\Request;

class TemplateBuildController
{
    public function __construct(private readonly TemplateBuildService $builds) {}

    public function index(Evento $evento)
    {
        return view('templates.criador', ['evento' => $evento, 'extensoes' => TemplateBuildService::EXTENSOES, 'temTemplates' => $this->builds->temTemplates()]);
    }

    public function listar(Request $request, Evento $evento)
    {
        $dados = $request->validate(['q' => ['nullable', 'string', 'max:150'], 'page' => ['nullable', 'integer', 'min:1']]);
        $itens = $this->builds->listar($dados['q'] ?? '');
        $inicio = (max(1, (int) ($dados['page'] ?? 1)) - 1) * 20;
        return response()->json(['results' => array_slice($itens, $inicio, 20), 'pagination' => ['more' => count($itens) > $inicio + 20]]);
    }

    public function criar(Request $request, Evento $evento)
    {
        $dados = $request->validate(['nome' => ['required', 'string', 'max:150']]);
        return response()->json($this->builds->criar($dados['nome']), 201);
    }

    public function abrir(Evento $evento, string $build)
    {
        return response()->json($this->builds->estado($build));
    }

    public function arquivo(Request $request, Evento $evento, string $build)
    {
        $dados = $request->validate(['arquivo' => ['required', 'string', 'max:240']]);
        return response()->json(['conteudo' => $this->builds->ler($build, $dados['arquivo'])]);
    }

    private function arquivos(Request $request): array
    {
        $dados = $request->validate(['arquivos' => ['present', 'array', 'max:100'], 'arquivos.*' => ['present', 'nullable', 'string', 'max:2000000']]);
        // O middleware converte strings vazias em null: um arquivo vazio é válido.
        return array_map(fn ($conteudo) => $conteudo ?? '', $dados['arquivos']);
    }

    public function salvar(Request $request, Evento $evento, string $build)
    {
        return response()->json($this->builds->salvar($build, $this->arquivos($request)));
    }

    public function estrutura(Request $request, Evento $evento, string $build)
    {
        $dados = $request->validate(['acao' => ['required', 'in:criar-arquivo,criar-pasta,remover-arquivo,remover-pasta,mover'], 'nome' => ['required', 'string', 'max:240'], 'destino' => ['nullable', 'string', 'max:240']]);
        return response()->json($this->builds->alterarEstrutura($build, $dados['acao'], $dados['nome'], $dados['destino'] ?? null));
    }

    public function upload(Request $request, Evento $evento, string $build)
    {
        $dados = $request->validate(['arquivo' => ['required', 'file', 'max:20480'], 'nome' => ['required', 'string', 'max:240']]);
        return response()->json($this->builds->upload($build, $request->file('arquivo'), $dados['nome']));
    }

    public function versao(Request $request, Evento $evento, string $build)
    {
        return response()->json($this->builds->novaVersao($build, $this->arquivos($request)), 201);
    }

    public function exportar(Evento $evento, string $build)
    {
        $manifesto = $this->builds->estado($build)['manifesto'];
        $nome = \Illuminate\Support\Str::slug($manifesto['nome']).'-'.$manifesto['versao'].'.zip';
        return response($this->builds->exportar($build), 200, ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="'.$nome.'"', 'Cache-Control' => 'private, no-store']);
    }

    public function visualizar(Evento $evento, string $build, PaginaEventoRenderer $renderer)
    {
        $manifesto = $this->builds->estado($build)['manifesto'];
        $evento = clone $evento;
        $evento->setRelation('templatePagina', new TemplatePagina(['variaveis' => $manifesto['variaveis'] ?? []]));
        $evento->pagina_variaveis = [];
        $acesso = $this->builds->acessoPrevia($build);
        $html = $renderer->renderizarModelo($this->builds->ler($build, 'index.html'), $evento, fn ($arquivo) => route('templates-build.previa-asset', [$build, $acesso, $arquivo]));
        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "sandbox allow-scripts; base-uri 'none'"]);
    }

    public function asset(Evento $evento, string $build, string $caminho)
    {
        $asset = $this->builds->asset($build, $caminho);
        return response($asset['conteudo'], 200, ['Content-Type' => $asset['mime'], 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "sandbox allow-scripts; base-uri 'none'", 'Access-Control-Allow-Origin' => '*']);
    }

    public function previaAsset(string $build, string $acesso, string $caminho)
    {
        $this->builds->validarAcessoPrevia($build, $acesso);
        return $this->asset(new Evento, $build, $caminho);
    }
}
