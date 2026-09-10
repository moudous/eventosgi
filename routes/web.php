<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\AtividadeController;
use App\Http\Controllers\ParticipanteController;
use App\Http\Controllers\ConvidadoController;
use App\Http\Controllers\ConfiguracaoController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\PaginaEventoController;
use App\Http\Controllers\TemplatePaginaController;
use App\Http\Controllers\SubmissaoController;
use App\Http\Controllers\SubmissaoPublicaController;
use App\Http\Controllers\SenhaParticipanteController;
use App\Http\Controllers\BibliotecaController;
use App\Http\Controllers\CaptchaInscricaoController;
use App\Http\Controllers\CaptchaSubmissaoController;
use App\Http\Controllers\PresencaController;

Route::get('/auth/gi', function (Request $request) {
    abort_unless($request->filled('code'), 400, 'Código ausente.');

    $response = Http::asForm()->timeout(10)->post(
        rtrim(config('gi.gi_url'), '/').'/integracoes/gi/trocar-codigo',
        [
            'client_id' => config('gi.client_id'),
            'client_secret' => config('gi.client_secret'),
            'code' => $request->string('code')->toString(),
        ],
    );

    abort_unless(
        $response->successful(),
        401,
        (string) ($response->json('message') ?: 'Não foi possível autenticar pelo GI.'),
    );

    $context = (array) $response->json('data');
    abort_unless(
        isset(
            $context['usuario']['id'],
            $context['usuario']['nome'],
            $context['sistema']['id'],
            $context['perfil']['id'],
            $context['access_token'],
        ),
        502,
        'O GI retornou um contexto de autenticação incompleto.',
    );
    if (! empty($context['atualizar'])) {
        $directory = Http::withToken($context['access_token'])->acceptJson()->timeout(10)
            ->get(rtrim(config('gi.gi_url'), '/').'/api/integracoes/v1/usuarios');
        if ($directory->successful()) {
            $context['atualizacao_usuarios'] = ['realizada' => true, 'total' => (int) $directory->json('total', 0)];
        }
    }
    $request->session()->regenerate();
    $request->session()->put('gi_context', $context);

    $destination = (string) $response->json('data.caminho', '/');
    if (! str_starts_with($destination, '/')
        || str_starts_with($destination, '//')
        || str_contains($destination, '\\')
        || str_contains($destination, '..')) {
        $destination = '/';
    }

    return redirect($destination);
})->name('auth.gi');

Route::get('/', function (Request $request) {
    abort_unless($request->session()->has('gi_context'), 401, 'Abra esta aplicação pelo menu do GI.');

    $visibleContext = $request->session()->get('gi_context');
    unset($visibleContext['access_token']);

    return response()
        ->view('session', ['context' => $visibleContext])
        ->header('Cache-Control', 'no-store');
});

Route::prefix('usuarios')->name('usuarios.')->group(function (): void {
    Route::get('/', [UsuarioController::class, 'index'])
        ->middleware('gi.permission:usuarios.listar')->name('index');
    Route::post('/estado-tabela', [UsuarioController::class, 'salvarEstadoTabela'])
        ->middleware('gi.permission:usuarios.listar')->name('estado-tabela');
    Route::post('/importar', [UsuarioController::class, 'import'])
        ->middleware('gi.permission:usuarios.importar')->name('import');
    Route::get('/{usuario}', [UsuarioController::class, 'show'])
        ->middleware('gi.permission:usuarios.visualizar')->name('show');
});

Route::prefix('eventos')->name('eventos.')->group(function (): void {
    Route::get('/', [EventoController::class, 'index'])->middleware('gi.permission:eventos.listar')->name('index');
    Route::get('/dados', [EventoController::class, 'dados'])->middleware('gi.permission:eventos.listar')->name('dados');
    Route::get('/{evento}/historico', [EventoController::class, 'historico'])->middleware('gi.permission:eventos.visualizar')->name('historico');
    Route::get('/apagados', [EventoController::class, 'apagados'])->middleware('gi.permission:eventos.listar')->name('apagados');
    Route::get('/criar', [EventoController::class, 'create'])->middleware('gi.permission:eventos.criar')->name('create');
    Route::post('/', [EventoController::class, 'store'])->middleware('gi.permission:eventos.criar')->name('store');
    Route::patch('/{evento}/restaurar', [EventoController::class, 'restore'])->middleware('gi.permission:eventos.restaurar')->name('restore');
    Route::delete('/{evento}/definitivamente', [EventoController::class, 'forceDestroy'])->middleware('gi.permission:eventos.excluir_definitivamente')->name('force-destroy');
    Route::get('/{evento}', [EventoController::class, 'show'])->middleware('gi.permission:eventos.visualizar')->name('show');
    Route::get('/{evento}/editar', [EventoController::class, 'edit'])->middleware('gi.permission:eventos.editar')->name('edit');
    Route::put('/{evento}', [EventoController::class, 'update'])->middleware('gi.permission:eventos.editar')->name('update');
    Route::delete('/{evento}', [EventoController::class, 'destroy'])->middleware('gi.permission:eventos.excluir')->name('destroy');
});

// A edição exige permissão; a visualização é pública para sites externos poderem
// incorporá-la diretamente em um iframe.
Route::prefix('eventos/{evento}/pagina')->name('eventos.pagina.')->group(function (): void {
    Route::get('/', [PaginaEventoController::class, 'editar'])->middleware('gi.permission:eventos.pagina.editar')->name('editar');
    Route::put('/', [PaginaEventoController::class, 'salvar'])->middleware('gi.permission:eventos.pagina.editar')->name('salvar');
    Route::get('/visualizar', [PaginaEventoController::class, 'visualizar'])->name('visualizar');
});

// Catalogo dos templates de pagina, importados por ZIP.
Route::prefix('templates')->name('templates.')->group(function (): void {
    Route::get('/', [TemplatePaginaController::class, 'index'])->middleware('gi.permission:templates.listar')->name('index');
    Route::post('/', [TemplatePaginaController::class, 'store'])->middleware('gi.permission:templates.importar')->name('store');
    Route::get('/{template}/exportar', [TemplatePaginaController::class, 'exportar'])->middleware('gi.permission:templates.exportar')->name('exportar');
    Route::delete('/{template}', [TemplatePaginaController::class, 'destroy'])->middleware('gi.permission:templates.excluir')->name('destroy');
    // Sem permissao: a pagina do evento e publica e o navegador de quem a abre precisa
    // dos arquivos. O servico so entrega extensoes de uma lista fechada.
    Route::get('/{template}/assets/{caminho}/visualizar', [TemplatePaginaController::class, 'asset'])
        ->where('caminho', '[^?]+')->name('asset');
});

// Categorias das atividades. A categoria e opcional na atividade, mas uma vez usada
// nao pode ser excluida nem desativada -- ver CategoriaController.
Route::prefix('categorias')->name('categorias.')->group(function (): void {
    Route::get('/', [CategoriaController::class, 'index'])->middleware('gi.permission:categorias.listar')->name('index');
    Route::get('/dados', [CategoriaController::class, 'dados'])->middleware('gi.permission:categorias.listar')->name('dados');
    Route::get('/criar', [CategoriaController::class, 'create'])->middleware('gi.permission:categorias.criar')->name('create');
    Route::post('/', [CategoriaController::class, 'store'])->middleware('gi.permission:categorias.criar')->name('store');
    Route::patch('/{categoria}/alternar', [CategoriaController::class, 'alternar'])->middleware('gi.permission:categorias.ativar_desativar')->name('alternar');
    Route::get('/{categoria}', [CategoriaController::class, 'show'])->middleware('gi.permission:categorias.visualizar')->name('show');
    Route::get('/{categoria}/editar', [CategoriaController::class, 'edit'])->middleware('gi.permission:categorias.editar')->name('edit');
    Route::put('/{categoria}', [CategoriaController::class, 'update'])->middleware('gi.permission:categorias.editar')->name('update');
    Route::delete('/{categoria}', [CategoriaController::class, 'destroy'])->middleware('gi.permission:categorias.excluir')->name('destroy');
});

// Configuracao da aplicacao. Permissoes proprias, cadastradas no perfil do GI: liberar
// uma rede dos limites de envio mexe em como o formulario publico se protege, e nao e a
// mesma capacidade de editar uma atividade.
//
// configuracao.visualizar abre a tela; as demais liberam cada acao dentro dela. Quem so
// tem a primeira ve a configuracao atual sem poder altera-la.
Route::prefix('configuracao')->name('configuracao.')->group(function (): void {
    Route::get('/', [ConfiguracaoController::class, 'index'])->middleware('gi.permission:configuracao.visualizar')->name('index');
    Route::post('/faixas-ip', [ConfiguracaoController::class, 'guardarFaixa'])->middleware('gi.permission:configuracao.faixa.criar')->name('faixas-ip.store');
    Route::patch('/faixas-ip/{faixa}', [ConfiguracaoController::class, 'alternarFaixa'])->middleware('gi.permission:configuracao.faixa.ativar_desativar')->name('faixas-ip.toggle');
    Route::delete('/faixas-ip/{faixa}', [ConfiguracaoController::class, 'removerFaixa'])->middleware('gi.permission:configuracao.faixa.excluir')->name('faixas-ip.destroy');
    Route::get('/plugin-wordpress', [ConfiguracaoController::class, 'baixarPlugin'])->middleware('gi.permission:configuracao.wordpress.visualizar')->name('plugin-wordpress');
});

Route::prefix('atividades')->name('atividades.')->group(function (): void {
    Route::get('/', [AtividadeController::class, 'index'])->middleware('gi.permission:atividades.listar')->name('index');
    Route::get('/dados', [AtividadeController::class, 'dados'])->middleware('gi.permission:atividades.listar')->name('dados');
    Route::get('/apagados', [AtividadeController::class, 'apagados'])->middleware('gi.permission:atividades.listar')->name('apagados');
    Route::get('/criar', [AtividadeController::class, 'create'])->middleware('gi.permission:atividades.criar')->name('create');
    Route::post('/', [AtividadeController::class, 'store'])->middleware('gi.permission:atividades.criar')->name('store');
    Route::get('/validador-presenca', [PresencaController::class, 'index'])->middleware('gi.permission:atividades.validador_qr')->name('validador-presenca');
    Route::post('/validador-presenca', [PresencaController::class, 'validar'])->middleware('gi.permission:atividades.validador_qr')->name('validador-presenca.validar');
    Route::post('/validador-presenca/confirmar', [PresencaController::class, 'confirmar'])->middleware('gi.permission:atividades.validador_qr')->name('validador-presenca.confirmar');
    // O construtor de formulario abre com atividades.formulario. Dentro dele, o bloco
    // Estrutura -- linhas, colunas e campos -- exige atividades.formulario.estrutura:
    // quem so tem a primeira ajusta titulo, datas, limites e mensagens sem poder mexer
    // nos campos ja publicados. Ver salvarFormulario().
    Route::get('/{atividade}/formulario', [AtividadeController::class, 'formulario'])->middleware('gi.permission:atividades.formulario')->name('formulario');
    Route::post('/{atividade}/formulario', [AtividadeController::class, 'salvarFormulario'])->middleware('gi.permission:atividades.formulario')->name('formulario.salvar');
    Route::post('/{atividade}/formulario/editor-imagem', [AtividadeController::class, 'enviarImagemEditor'])->middleware('gi.permission:atividades.formulario')->name('formulario.editor-imagem');
    Route::get('/{atividade}/formulario/visualizar', [AtividadeController::class, 'previewRedirect'])->middleware('gi.permission:atividades.visualizar_formulario')->name('formulario.visualizar');
    Route::get('/{atividade}/formulario/preview-link', [AtividadeController::class, 'previewLink'])->middleware('gi.permission:atividades.visualizar_formulario')->name('formulario.preview-link');
    Route::get('/{atividade}/formulario/preview', [AtividadeController::class, 'previewRedirect'])->middleware('signed')->name('formulario.preview');
    Route::post('/{atividade}/formulario/preview', [AtividadeController::class, 'inscrever'])->middleware('signed')->name('formulario.inscrever');
    Route::get('/{atividade}/inscricoes/exportar-link/{formato}', [AtividadeController::class, 'exportarLink'])->middleware('gi.permission:atividades.inscritos')->whereIn('formato', ['ods', 'csv', 'xls', 'xlsx'])->name('inscricoes.exportar-link');
    // Assinada em vez de protegida por permissao: o download roda dentro do iframe do GI,
    // onde o cookie de sessao pode nao acompanhar a requisicao. Quem gera o link ja passou
    // pela permissao em inscricoes.exportar-link.
    Route::get('/{atividade}/inscricoes/exportar/{formato}', [AtividadeController::class, 'exportarInscricoes'])->middleware('signed')->whereIn('formato', ['ods', 'csv', 'xls', 'xlsx'])->name('inscricoes.exportar');
    Route::get('/{atividade}/inscricoes', [AtividadeController::class, 'inscricoes'])->middleware('gi.permission:atividades.inscritos')->name('inscricoes');
    Route::patch('/inscricoes/{inscricao}/presenca', [PresencaController::class, 'definir'])->middleware('gi.permission:atividades.validador_qr')->name('inscricoes.presenca');
    Route::get('/{atividade}/historico', [AtividadeController::class, 'historico'])->middleware('gi.permission:atividades.historico')->name('historico');
    Route::patch('/{atividade}/restaurar', [AtividadeController::class, 'restore'])->middleware('gi.permission:atividades.restaurar')->name('restore');
    Route::delete('/{atividade}/definitivamente', [AtividadeController::class, 'forceDestroy'])->middleware('gi.permission:atividades.excluir_definitivamente')->name('force-destroy');
    Route::get('/{atividade}', [AtividadeController::class, 'show'])->middleware('gi.permission:atividades.visualizar')->name('show');
    Route::get('/{atividade}/convidados', [\App\Http\Controllers\AtividadeConvidadoController::class, 'edit'])->name('convidados.edit');
    Route::put('/{atividade}/convidados', [\App\Http\Controllers\AtividadeConvidadoController::class, 'update'])->middleware('gi.permission:atividades.convidados.editar')->name('convidados.update');
    Route::get('/{atividade}/editar', [AtividadeController::class, 'edit'])->middleware('gi.permission:atividades.editar')->name('edit');
    Route::put('/{atividade}', [AtividadeController::class, 'update'])->middleware('gi.permission:atividades.editar')->name('update');
    Route::delete('/{atividade}', [AtividadeController::class, 'destroy'])->middleware('gi.permission:atividades.excluir')->name('destroy');
});

// Endereço permanente por hash. A disponibilidade segue as datas do formulário.
Route::get('/inscricoes/{atividade}', [AtividadeController::class, 'previewRedirect'])->whereNumber('atividade')->name('inscricoes.legado');
Route::get('/formularios/{atividade:hash_publica}', [AtividadeController::class, 'inscricaoPublica'])->name('inscricoes.publica');
Route::post('/formularios/{atividade:hash_publica}', [AtividadeController::class, 'inscrever'])->name('inscricoes.publica.enviar');
Route::get('/formularios/{atividade:hash_publica}/captcha', CaptchaInscricaoController::class)->name('inscricoes.captcha');
Route::get('/formularios/{atividade:hash_publica}/editor/imagens/{arquivo}/visualizar', [AtividadeController::class, 'imagemEditor'])
    ->where('arquivo', '[a-f0-9-]{36}\.(jpg|jpeg|png|gif|webp)')->name('inscricoes.editor.imagem');
Route::post('/formularios/{atividade:hash_publica}/comprovante/email', [AtividadeController::class, 'enviarComprovante'])->name('inscricoes.comprovante.email');
Route::delete('/formularios/{atividade:hash_publica}/inscricao', [AtividadeController::class, 'apagarInscricao'])->name('inscricoes.apagar');
Route::get('/comprovantes/{inscricao:comprovante_hash}.pdf/visualizar', [AtividadeController::class, 'comprovantePdf'])
    ->where('inscricao', '[a-f0-9]{64}')->name('inscricoes.comprovante.pdf');

// A posse do token recebido por e-mail autoriza a definição da senha. O servidor guarda
// somente o hash, limita o link a 15 minutos e o invalida depois do primeiro uso.
Route::get('/senha/definir/{token}', [SenhaParticipanteController::class, 'edit'])
    ->where('token', '[A-Za-z0-9]{64}')->name('senha-participante.editar');
Route::post('/senha/definir/{token}', [SenhaParticipanteController::class, 'update'])
    ->where('token', '[A-Za-z0-9]{64}')->name('senha-participante.atualizar');

Route::prefix('biblioteca')->name('biblioteca.')->group(function (): void {
    Route::get('/', [BibliotecaController::class, 'index'])->middleware('gi.permission:biblioteca.listar')->name('index');
    Route::post('/', [BibliotecaController::class, 'store'])->middleware('gi.permission:biblioteca.enviar')->name('store');
    Route::post('/recortar', [BibliotecaController::class, 'recortar'])->middleware('gi.permission:biblioteca.recortar')->name('recortar');
    // O endereço é público para que sites e formulários possam reutilizar estes arquivos.
    Route::get('/arquivos/{arquivo}/visualizar', [BibliotecaController::class, 'abrir'])
        ->where('arquivo', '[a-f0-9-]{36}\.[a-z0-9]{1,15}')->name('abrir');
});

// Anexos das inscricoes: ficam em disco privado e so saem por aqui, com URL assinada
// gerada na tela de inscricoes (que exige atividades.visualizar).
Route::get('/inscricoes/{inscricao}/arquivos/{campo}/{indice}/{modo}', [AtividadeController::class, 'arquivoInscricao'])
    ->middleware('signed')
    ->whereNumber('indice')
    // O nome do campo vem do construtor de formularios e pode ter acento; so a barra e
    // barrada, para nao confundir o roteador. Quem valida o caminho do arquivo e o controller.
    ->where('campo', '[^/]+')
    ->whereIn('modo', ['visualizar', 'baixar'])
    ->name('inscricoes.arquivo');

Route::prefix('participantes')->name('participantes.')->group(function (): void {
    Route::get('/', [ParticipanteController::class, 'index'])->middleware('gi.permission:participantes.listar')->name('index');
    Route::get('/dados', [ParticipanteController::class, 'dados'])->middleware('gi.permission:participantes.listar')->name('dados');
    Route::get('/criar', [ParticipanteController::class, 'create'])->middleware('gi.permission:participantes.criar')->name('create');
    Route::post('/', [ParticipanteController::class, 'store'])->middleware('gi.permission:participantes.criar')->name('store');
    Route::get('/{id}/{nome}/editar', [ParticipanteController::class, 'edit'])->middleware('gi.permission:participantes.editar')->name('edit');
    Route::get('/{id}/{nome}', [ParticipanteController::class, 'show'])->middleware('gi.permission:participantes.visualizar')->name('show');
    Route::put('/{id}/{nome}', [ParticipanteController::class, 'update'])->middleware('gi.permission:participantes.editar')->name('update');
    Route::delete('/{id}/{nome}', [ParticipanteController::class, 'destroy'])->middleware('gi.permission:participantes.excluir')->name('destroy');
});

Route::prefix('convidados')->name('convidados.')->group(function (): void {
    Route::get('/foto/{codigo}/visualizar', [ConvidadoController::class, 'foto'])->where('codigo', '[a-f0-9]{40}\.jpg')->name('foto');
    Route::get('/', [ConvidadoController::class, 'index'])->middleware('gi.permission:convidados.listar')->name('index');
    Route::get('/dados', [ConvidadoController::class, 'dados'])->middleware('gi.permission:convidados.listar')->name('dados');
    Route::get('/criar', [ConvidadoController::class, 'create'])->middleware('gi.permission:convidados.criar')->name('create');
    Route::post('/', [ConvidadoController::class, 'store'])->middleware('gi.permission:convidados.criar')->name('store');
    Route::get('/{convidado}', [ConvidadoController::class, 'show'])->middleware('gi.permission:convidados.visualizar')->name('show');
    Route::get('/{convidado}/editar', [ConvidadoController::class, 'edit'])->middleware('gi.permission:convidados.editar')->name('edit');
    Route::put('/{convidado}', [ConvidadoController::class, 'update'])->middleware('gi.permission:convidados.editar')->name('update');
    Route::delete('/{convidado}', [ConvidadoController::class, 'destroy'])->middleware('gi.permission:convidados.excluir')->name('destroy');
});

Route::prefix('submissao')->name('submissoes.')->group(function (): void {
    // Administração das chamadas de submissão.
    Route::get('/', [SubmissaoController::class, 'index'])->middleware('gi.permission:submissoes.listar')->name('index');
    Route::get('/dados', [SubmissaoController::class, 'dados'])->middleware('gi.permission:submissoes.listar')->name('dados');
    Route::get('/criar', [SubmissaoController::class, 'create'])->middleware('gi.permission:submissoes.criar')->name('create');
    Route::post('/', [SubmissaoController::class, 'store'])->middleware('gi.permission:submissoes.criar')->name('store');
    Route::get('/{submissao}/editar', [SubmissaoController::class, 'edit'])->middleware('gi.permission:submissoes.editar')->name('edit');
    Route::put('/{submissao}', [SubmissaoController::class, 'update'])->middleware('gi.permission:submissoes.editar')->name('update');
    Route::patch('/{submissao}/alternar', [SubmissaoController::class, 'alternar'])->middleware('gi.permission:submissoes.ativar_desativar')->name('alternar');
    Route::delete('/{submissao}', [SubmissaoController::class, 'destroy'])->middleware('gi.permission:submissoes.excluir')->name('destroy');
    Route::get('/{submissao}/inscritos', [SubmissaoController::class, 'inscritos'])->middleware('gi.permission:submissoes.inscritos')->name('inscritos');
    Route::get('/{submissao}/inscritos/dados', [SubmissaoController::class, 'inscritosDados'])->middleware('gi.permission:submissoes.inscritos')->name('inscritos.dados');
    Route::patch('/{submissao}/inscritos/{trabalho}/status', [SubmissaoController::class, 'alterarStatus'])->middleware('gi.permission:submissoes.trabalhos.alterar_status')->name('inscritos.alterar-status');
    Route::patch('/{submissao}/inscritos/{trabalho}/restaurar', [SubmissaoController::class, 'restaurar'])->middleware('gi.permission:submissoes.trabalhos.restaurar')->name('inscritos.restaurar');
    Route::delete('/{submissao}/inscritos/{trabalho}/definitivo', [SubmissaoController::class, 'excluirDefinitivamente'])->middleware('gi.permission:submissoes.trabalhos.excluir_definitivamente')->name('inscritos.excluir-definitivamente');

    // Portal público: criação, autenticação e edição dos trabalhos. Nenhuma permissão GI.
    Route::get('/{submissao}/formulario', [SubmissaoPublicaController::class, 'formulario'])->name('publicas.formulario');
    Route::get('/{submissao}/captcha', CaptchaSubmissaoController::class)->name('publicas.captcha');
    Route::post('/{submissao}/formulario/trabalhos', [SubmissaoPublicaController::class, 'criar'])->name('publicas.criar');
    Route::post('/{submissao}/formulario/entrar', [SubmissaoPublicaController::class, 'entrar'])->name('publicas.entrar');
    Route::post('/{submissao}/formulario/selecionar', [SubmissaoPublicaController::class, 'selecionar'])->name('publicas.selecionar');
    Route::put('/{submissao}/formulario/trabalhos/{trabalho}', [SubmissaoPublicaController::class, 'atualizar'])->name('publicas.atualizar');
    Route::get('/{submissao}/formulario/trabalhos/{trabalho}/exportar-doc', [SubmissaoPublicaController::class, 'exportarDocumento'])->name('publicas.exportar-documento');
    Route::delete('/{submissao}/formulario/trabalhos/{trabalho}', [SubmissaoPublicaController::class, 'excluir'])->name('publicas.excluir');
    Route::patch('/{submissao}/formulario/trabalhos/{trabalho}/senha', [SubmissaoPublicaController::class, 'alterarSenha'])->name('publicas.senha');
    Route::post('/{submissao}/formulario/esqueci-senha', [SubmissaoPublicaController::class, 'esqueciSenha'])->name('publicas.esqueci-senha');
    Route::post('/{submissao}/formulario/sair', [SubmissaoPublicaController::class, 'sair'])->name('publicas.sair');
});

Route::post('/manutencao/{acao}', function (Request $request, string $acao) {
    abort_unless($request->session()->has('gi_context'), 401);
    $comandos = ['optimize-clear' => 'optimize:clear', 'config-cache' => 'config:cache'];
    abort_unless(isset($comandos[$acao]), 404);

    $codigo = Artisan::call($comandos[$acao]);
    $mensagem = $codigo === 0
        ? "Comando php artisan {$comandos[$acao]} executado com sucesso."
        : "O comando php artisan {$comandos[$acao]} terminou com código {$codigo}.";

    return redirect('/')->with('manutencao', $mensagem);
})->name('manutencao.executar');

Route::get('/gi/{resource}', function (Request $request, string $resource) {
    abort_unless($request->session()->has('gi_context'), 401);
    abort_unless(in_array($resource, ['perfis', 'usuarios', 'grupos'], true), 404);

    $upstreamResponse = Http::withToken($request->session()->get('gi_context.access_token'))
        ->acceptJson()->timeout(10)
        ->get(rtrim(config('gi.gi_url'), '/').'/api/integracoes/v1/'.$resource);

    return response($upstreamResponse->body(), $upstreamResponse->status())
        ->header(
            'Content-Type',
            $upstreamResponse->header('Content-Type') ?? 'application/json',
        );
});

// Fundos dos formulários públicos: somente imagens com nomes gerados pelo servidor.
Route::get('/personalizacao/imagens/{arquivo}/visualizar', function (string $arquivo) {
    $caminho = storage_path('app/public/personalizacao/'.$arquivo);
    abort_unless(is_file($caminho), 404);
    return response()->file($caminho, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=31536000, immutable']);
})->where('arquivo', '[a-f0-9-]{36}\.(jpg|jpeg|png|webp)')->name('eventos.personalizacao.imagem');
