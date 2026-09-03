<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\AtividadeController;

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

Route::prefix('atividades')->name('atividades.')->group(function (): void {
    Route::get('/', [AtividadeController::class, 'index'])->middleware('gi.permission:atividades.listar')->name('index');
    Route::get('/dados', [AtividadeController::class, 'dados'])->middleware('gi.permission:atividades.listar')->name('dados');
    Route::get('/apagados', [AtividadeController::class, 'apagados'])->middleware('gi.permission:atividades.listar')->name('apagados');
    Route::get('/criar', [AtividadeController::class, 'create'])->middleware('gi.permission:atividades.criar')->name('create');
    Route::post('/', [AtividadeController::class, 'store'])->middleware('gi.permission:atividades.criar')->name('store');
    Route::get('/{atividade}/historico', [AtividadeController::class, 'historico'])->middleware('gi.permission:atividades.visualizar')->name('historico');
    Route::patch('/{atividade}/restaurar', [AtividadeController::class, 'restore'])->middleware('gi.permission:atividades.restaurar')->name('restore');
    Route::delete('/{atividade}/definitivamente', [AtividadeController::class, 'forceDestroy'])->middleware('gi.permission:atividades.excluir_definitivamente')->name('force-destroy');
    Route::get('/{atividade}', [AtividadeController::class, 'show'])->middleware('gi.permission:atividades.visualizar')->name('show');
    Route::get('/{atividade}/editar', [AtividadeController::class, 'edit'])->middleware('gi.permission:atividades.editar')->name('edit');
    Route::put('/{atividade}', [AtividadeController::class, 'update'])->middleware('gi.permission:atividades.editar')->name('update');
    Route::delete('/{atividade}', [AtividadeController::class, 'destroy'])->middleware('gi.permission:atividades.excluir')->name('destroy');
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
