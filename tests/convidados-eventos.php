<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
Schema::create('eventos', function ($table) { $table->id(); $table->string('nome'); $table->boolean('ativo')->default(true); $table->softDeletes(); });
Schema::create('convidados', function ($table) {
    $table->id(); $table->unsignedBigInteger('evento_id')->nullable(); $table->string('nome');
    $table->string('sobrenome')->nullable(); $table->string('titulacao')->nullable();
    $table->text('curriculo')->nullable(); $table->text('redes_sociais')->nullable(); $table->timestamps();
});
DB::table('eventos')->insert([['id'=>1,'nome'=>'A'],['id'=>2,'nome'=>'B']]);
DB::table('convidados')->insert(['id'=>1,'nome'=>'Ana','evento_id'=>1]);
(require __DIR__.'/../database/migrations/2026_09_10_150000_create_convidados_eventos_table.php')->up();
$check = function ($expected, $actual) { if ($expected !== $actual) throw new RuntimeException('Resultado inesperado: '.json_encode($actual)); };
$ids = fn () => App\Models\Convidado::find(1)->eventos()->pluck('eventos.id')->all();
$check([1], $ids());
$controller = new App\Http\Controllers\ConvidadoController;
$method = new ReflectionMethod($controller, 'salvar');
$save = function ($eventos) use ($controller, $method) {
    $request = Illuminate\Http\Request::create('/', 'POST', ['nome'=>'Ana', 'titulacao'=>'Prof. Dra.', 'eventos'=>$eventos]);
    $method->invoke($controller, $request, App\Models\Convidado::find(1));
};
$save([1,2]); $check([1,2], $ids());
$check('Prof. Dra.', App\Models\Convidado::find(1)->titulacao);
$check(1, App\Models\Convidado::whereHas('eventos', fn ($q) => $q->where('eventos.id', 2))->count());
foreach ([[1,1],[999],[]] as $invalid) {
    try { $save($invalid); throw new RuntimeException('Seleção inválida aceita.'); }
    catch (Illuminate\Validation\ValidationException $e) {}
}
$save([2]); $check([2], $ids());
$check(2, App\Models\Evento::count());
echo "OK: migração preserva vínculo, múltiplos eventos, remoção, filtro, validação e titulação livre.\n";
