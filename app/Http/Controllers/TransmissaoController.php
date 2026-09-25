<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Transmissao;
use App\Models\YouTubeConexao;
use App\Models\LiveKitConfiguracao;
use App\Models\ArquivoBiblioteca;
use App\Services\YouTubeOAuthService;
use App\Services\LiveKitTokenService;
use App\Services\GiPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TransmissaoController
{
    public function index(Request $request, GiPermissionService $permissoes): View
    {
        $status = (string) $request->input('status', '');
        $eventoId = max(0, (int) $request->input('evento_id', 0));

        $transmissoes = Transmissao::query()
            ->with('evento')
            ->when(in_array($status, Transmissao::STATUS, true), fn ($query) => $query->where('status', $status))
            ->when($eventoId > 0, fn ($query) => $query->where('evento_id', $eventoId))
            ->orderByRaw("CASE status WHEN 'em_andamento' THEN 1 WHEN 'agendada' THEN 2 WHEN 'finalizada' THEN 3 ELSE 4 END")
            ->orderByDesc('agendada_para')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('transmissoes.index', [
            'transmissoes' => $transmissoes,
            'eventosFiltro' => Evento::query()->whereHas('transmissoes')->orderBy('nome')->get(['id', 'nome']),
            'statusSelecionado' => $status,
            'eventoSelecionado' => $eventoId,
            'youtubeConexao' => YouTubeConexao::atual(),
            'podeConfigurarLiveKit' => $permissoes->permite('transmissao.configuracao', $request),
            'podeExcluirTransmissao' => $permissoes->permite('transmissao.excluir', $request),
        ]);
    }

    public function conectarYouTube(Request $request, YouTubeOAuthService $youtube): RedirectResponse
    {
        $state = Str::random(64);
        $request->session()->put('youtube_oauth_state', $state);

        try {
            return redirect()->away($youtube->urlDeAutorizacao($state));
        } catch (\RuntimeException $excecao) {
            return redirect()->route('transmissoes.index')->with('erro', $excecao->getMessage());
        }
    }

    public function retornoYouTube(Request $request, YouTubeOAuthService $youtube): RedirectResponse
    {
        $state = (string) $request->session()->pull('youtube_oauth_state', '');
        if ($state === '' || ! hash_equals($state, (string) $request->input('state'))) {
            return redirect()->route('transmissoes.index')->with('erro', 'Não foi possível validar o retorno da conexão com o YouTube. Tente novamente.');
        }
        if ($request->filled('error')) {
            return redirect()->route('transmissoes.index')->with('erro', 'A conexão com o YouTube foi cancelada ou não foi autorizada.');
        }

        try {
            $conexao = $youtube->conectar((string) $request->input('code'), $request->session()->get('gi_context.usuario.id'));

            return redirect()->route('transmissoes.index')->with('status', "Canal \"{$conexao->canal_titulo}\" conectado com sucesso.");
        } catch (\RuntimeException $excecao) {
            return redirect()->route('transmissoes.index')->with('erro', $excecao->getMessage());
        }
    }

    public function desconectarYouTube(): RedirectResponse
    {
        YouTubeConexao::atual()?->delete();

        return redirect()->route('transmissoes.index')->with('status', 'A conta do YouTube foi desconectada deste sistema.');
    }

    public function create(): View
    {
        return view('transmissoes.create', [
            'eventos' => Evento::query()->where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function configuracao(): View
    {
        return view('transmissoes.configuracao', ['configuracao' => LiveKitConfiguracao::atual()]);
    }

    public function tutorialConfiguracao(): View
    {
        return view('transmissoes.tutorial-configuracao');
    }

    public function salvarConfiguracao(Request $request): RedirectResponse
    {
        $urlValida = function (string $atributo, mixed $valor, \Closure $falhar): void {
            if (! in_array(strtolower((string) parse_url((string) $valor, PHP_URL_SCHEME)), ['ws', 'wss'], true)) {
                $falhar('A :attribute deve começar com ws:// ou wss://.');
            }
        };
        $atual = LiveKitConfiguracao::atual();
        $dados = $request->validate([
            'url' => ['required', 'string', 'max:500', $urlValida],
            'api_key' => ['required', 'string', 'max:500'],
            'api_secret' => [$atual ? 'nullable' : 'required', 'string', 'max:500'],
        ], [], ['url' => 'URL do servidor', 'api_key' => 'API Key', 'api_secret' => 'API Secret']);
        if ($atual && blank($dados['api_secret'] ?? null)) unset($dados['api_secret']);
        ($atual ?? new LiveKitConfiguracao)->fill($dados)->save();

        return redirect()->route('transmissoes.configuracao')->with('status', 'Configuração do servidor de videoconferência salva.');
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'evento_id' => ['required', 'integer', 'exists:eventos,id'],
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'agendada_para' => ['required', 'date'],
            'miniatura_url' => ['nullable', 'url:http,https', 'max:2048'],
        ]);

        $dados['status'] = Transmissao::STATUS_AGENDADA;
        Transmissao::create($dados);

        return redirect()->route('transmissoes.index')->with('status', 'Transmissão agendada com sucesso.');
    }

    public function destroy(Transmissao $transmissao): RedirectResponse
    {
        $titulo = $transmissao->titulo;
        $transmissao->delete();

        return redirect()->route('transmissoes.index')->with('status', "Transmissão \"{$titulo}\" excluída.");
    }

    /** Página pública da sala. O hash funciona como convite secreto. */
    public function sala(Request $request, Transmissao $transmissao, GiPermissionService $permissoes): View
    {
        abort_if(in_array($transmissao->status, [Transmissao::STATUS_FINALIZADA, Transmissao::STATUS_CANCELADA], true), 410, 'Esta transmissão foi encerrada.');

        $usuario = (array) $request->session()->get('gi_context.usuario', []);
        $usuarioLogado = filled($usuario['id'] ?? null) && filled($usuario['nome'] ?? null)
            ? ['id' => (int) $usuario['id'], 'nome' => (string) $usuario['nome']]
            : null;
        $nomeSala = trim((string) $request->session()->get('transmissoes.nome.'.$transmissao->id, ''));

        return view('transmissoes.sala', [
            'transmissao' => $transmissao,
            'usuarioLogado' => $usuarioLogado,
            'nomeSala' => $nomeSala,
            'podeAdministrarPalco' => $permissoes->permite('transmissao.configuracao', $request),
            'midiasPalco' => $permissoes->permite('transmissao.configuracao', $request)
                ? ArquivoBiblioteca::query()->where('tipo', 'imagem')->latest('id')->get(['id', 'nome', 'arquivo'])
                : collect(),
        ]);
    }

    /** Entrega credenciais temporárias, vinculadas a uma única sala, para o navegador. */
    public function entrarSala(Request $request, Transmissao $transmissao, LiveKitTokenService $livekit, GiPermissionService $permissoes): JsonResponse
    {
        abort_if(in_array($transmissao->status, [Transmissao::STATUS_FINALIZADA, Transmissao::STATUS_CANCELADA], true), 410, 'Esta transmissão foi encerrada.');

        $dados = $request->validate(['nome' => ['required', 'string', 'min:2', 'max:80']]);
        $nome = trim($dados['nome']);
        $request->session()->put('transmissoes.nome.'.$transmissao->id, $nome);
        try {
            return response()->json($livekit->paraParticipante(
                $transmissao,
                $nome,
                $permissoes->permite('transmissao.configuracao', $request),
            ));
        } catch (\RuntimeException $excecao) {
            $podeConfigurar = $permissoes->permite('transmissao.configuracao', $request);
            return response()->json([
                'message' => $podeConfigurar ? $excecao->getMessage() : 'A sala de videoconferência ainda não está disponível.',
                'configuracao' => $podeConfigurar,
            ], 503);
        }
    }

    public function definirMudo(Request $request, Transmissao $transmissao, LiveKitTokenService $livekit, GiPermissionService $permissoes): JsonResponse
    {
        abort_if(in_array($transmissao->status, [Transmissao::STATUS_FINALIZADA, Transmissao::STATUS_CANCELADA], true), 410, 'Esta transmissão foi encerrada.');
        $permissoes->exigir('transmissao.configuracao', $request);
        $dados = $request->validate([
            'identity' => ['required', 'string', 'max:255'],
            'track_sid' => ['required', 'string', 'max:255'],
            'muted' => ['required', 'boolean'],
        ]);

        try {
            $livekit->definirMudo($transmissao, $dados['identity'], $dados['track_sid'], (bool) $dados['muted']);
            return response()->json(['muted' => (bool) $dados['muted']]);
        } catch (\RuntimeException $excecao) {
            return response()->json(['message' => $excecao->getMessage()], 503);
        }
    }
}
