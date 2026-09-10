<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array']);
$schema = Illuminate\Support\Facades\Schema::getFacadeRoot();
$schema->create('atividades', function ($table) { $table->id(); $table->softDeletes(); });
$schema->create('convidados', function ($table) { $table->id(); $table->string('nome'); $table->string('sobrenome')->nullable(); });
(require __DIR__.'/../database/migrations/2026_09_10_140000_create_atividade_convidado_table.php')->up();
Illuminate\Support\Facades\DB::table('atividades')->insert([['id' => 1], ['id' => 2]]);
Illuminate\Support\Facades\DB::table('convidados')->insert([['id' => 1, 'nome' => 'Ana'], ['id' => 2, 'nome' => 'Bia']]);
$history = new class extends App\Services\HistoricoService {
    public function atividade(App\Models\Atividade $atividade, string $texto, array $dados, Illuminate\Http\Request $request): void {}
};
$controller = new App\Http\Controllers\AtividadeConvidadoController;
$save = function ($ids, $id = 1) use ($controller, $history) {
    $request = Illuminate\Http\Request::create('/', 'PUT', ['convidados' => $ids]);
    $request->setLaravelSession(app('session.store'));
    $request->session()->put('gi_context.permissoes', ['atividades.convidados.editar']);
    $controller->update($request, App\Models\Atividade::findOrFail($id), $history);
};
$check = function ($expected, $actual) { if ($expected !== $actual) throw new RuntimeException('Resultado inesperado: '.json_encode($actual)); };
$ids = fn () => App\Models\Atividade::find(1)->convidados()->pluck('convidados.id')->all();
$save([2, 1]); $check([2, 1], $ids());
$save([1, 2]); $check([1, 2], $ids());
$save([1], 2);
foreach ([[1, 1], [999]] as $invalid) {
    try { $save($invalid); throw new RuntimeException('Vínculo inválido aceito.'); }
    catch (Illuminate\Validation\ValidationException $e) {}
}
$check([1, 2], $ids());
$save([2]); $check([2], $ids());
$save(''); $check([], $ids());
$check(2, App\Models\Convidado::count());
echo "OK: inclusão, ordenação, remoção, seleção vazia, duplicidade e convidado inexistente; cadastro original preservado.\n";
$request = Illuminate\Http\Request::create('/', 'PUT', ['convidados' => [1]]);
$request->setLaravelSession(app('session.store'));
$request->session()->put('gi_context.permissoes', ['atividades.convidados.visualizar']);
try {
    $controller->update($request, App\Models\Atividade::find(1), $history);
    throw new RuntimeException('Visualizador conseguiu editar.');
} catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
    $check(403, $exception->getStatusCode());
}
$check([], $ids());
echo "OK: permissão de visualização não permite salvar vínculos.\n";
$schema->create('eventos', function ($table) { $table->id(); });
Illuminate\Support\Facades\DB::table('eventos')->insert(['id' => 1]);
$validar = new ReflectionMethod(App\Http\Controllers\AtividadeController::class, 'validar');
$request = Illuminate\Http\Request::create('/', 'POST', [
    'nome' => 'Teste', 'ativo' => 1, 'evento_id' => 1,
    'personalizacao' => ['posicao' => 'direita', 'borda' => 1, 'cor_borda' => '#123456'],
]);
$request->setLaravelSession(app('session.store'));
$request->session()->put('gi_context.permissoes', []);
$dados = $validar->invoke(new App\Http\Controllers\AtividadeController, $request);
$check(false, array_key_exists('personalizacao', $dados));
$request->session()->put('gi_context.permissoes', ['atividade.personalizar']);
$dados = $validar->invoke(new App\Http\Controllers\AtividadeController, $request);
$check('direita', $dados['personalizacao']['posicao']);
echo "OK: personalização só é aceita com atividade.personalizar.\n";
