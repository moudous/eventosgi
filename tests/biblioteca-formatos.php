<?php
// php tests/biblioteca-formatos.php — banco em memória e arquivos temporários.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ArquivoBiblioteca;
use App\Services\BibliotecaImagemService;
use App\Http\Controllers\BibliotecaController;
use Illuminate\Support\Facades\{Schema, File};
use Illuminate\Http\Request;
use Illuminate\Session\{Store, ArraySessionHandler};
use Illuminate\Validation\ValidationException;

function check(bool $ok, string $mensagem): void { if (!$ok) throw new RuntimeException($mensagem); }
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
Schema::create('arquivos_biblioteca', function ($t) {
    $t->id(); foreach (['nome','arquivo','mime','formato','tipo','categoria'] as $c) $t->string($c)->nullable();
    foreach (['tamanho','largura','altura','enviado_por'] as $c) $t->integer($c)->nullable();
    $t->text('tags')->nullable(); $t->timestamps();
});
$pasta = sys_get_temp_dir().'/biblioteca-teste-'.bin2hex(random_bytes(8));
$app->useStoragePath($pasta);
File::ensureDirectoryExists($pasta.'/app/public/biblioteca');
try {
    $servico = new BibliotecaImagemService;
    $registro = ArquivoBiblioteca::create(['nome' => 'Teste.png', 'arquivo' => '11111111-1111-1111-1111-111111111111.png', 'formato' => 'png', 'tipo' => 'imagem', 'categoria' => 'logo', 'tags' => ['teste']]);
    $original = $servico->caminho($registro);
    $img = imagecreatetruecolor(1200, 600);
    for ($y = 0; $y < 600; $y++) for ($x = 0; $x < 1200; $x++) imagesetpixel($img, $x, $y, mt_rand(0, 0xffffff));
    imagepng($img, $original); imagedestroy($img);
    $hash = hash_file('sha256', $original);
    foreach (BibliotecaImagemService::FORMATOS as $formato => $_) {
        $gerada = $servico->gerar($registro, $formato);
        check($gerada['largura'] <= 1200 && $gerada['altura'] <= 600, 'Não ampliar original');
        if ($formato === 'svg') {
            $svg = simplexml_load_string($gerada['conteudo']);
            check($svg !== false && isset($svg->path) && !str_contains($gerada['conteudo'], '<image'), 'SVG deve conter caminhos vetoriais');
        } else {
            $dim = getimagesizefromstring($gerada['conteudo']);
            check($dim[0] === $gerada['largura'] && $dim[1] === $gerada['altura'], 'Dimensões declaradas');
            if (str_starts_with($formato, 'compressao')) {
                $alvo = ['compressao_pequena' => 500, 'compressao_media' => 250, 'compressao_grande' => 100][$formato];
                check(strlen($gerada['conteudo']) <= $alvo * 1024, 'Limite de compressão');
            }
        }
        if ($formato === 'icone') check($gerada['largura'] === 64 && $gerada['altura'] === 32, 'Proporção do ícone');
    }
    check(hash_file('sha256', $original) === $hash, 'Original preservado');
    $request = Request::create('/', 'POST', ['formato' => 'webp']);
    $request->setLaravelSession(new Store('teste', new ArraySessionHandler(120)));
    $app->instance('request', $request); $app['url']->setRequest($request); $app['redirect']->setSession($request->session());
    $controller = new BibliotecaController;
    $controller->formatos($request, $registro, $servico);
    $copia = ArquivoBiblioteca::latest('id')->first();
    check($copia->id !== $registro->id && is_file($servico->caminho($copia)), 'Cópia gravada no disco e banco');
    check($copia->tags === ['teste'] && $copia->categoria === 'logo', 'Metadados mantidos');
    try {
        $controller->excluirPermanentemente($request, $copia, $servico);
        throw new RuntimeException('Exclusão sem confirmação aceita');
    } catch (ValidationException $e) { check(is_file($servico->caminho($copia)), 'Sem confirmação deve preservar arquivo'); }
    $request->merge(['confirmar_exclusao' => '1']);
    $controller->excluirPermanentemente($request, $copia, $servico);
    check(!is_file($servico->caminho($copia)) && !ArquivoBiblioteca::find($copia->id), 'Exclusão de disco e registro');
    check(is_file($original), 'Exclusão da cópia não afeta original');
    foreach (['biblioteca.formatos' => 'imagem.formatos', 'biblioteca.excluir-permanentemente' => 'biblioteca.excluir_permanentemente'] as $rota => $permissao) {
        check(in_array('gi.permission:'.$permissao, app('router')->getRoutes()->getByName($rota)->middleware()), 'Permissão da rota');
        $request->session()->put('gi_context.permissoes', []);
        try {
            (new App\Http\Middleware\RequireGiPermission)->handle($request, fn () => response('OK'), $permissao);
            throw new RuntimeException('Acesso sem permissão permitido');
        } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
            check($e->getStatusCode() === 403, 'Acesso deve ser negado');
        }
        $request->session()->put('gi_context.permissoes', [$permissao]);
        check((new App\Http\Middleware\RequireGiPermission)->handle($request, fn () => response('OK'), $permissao)->getStatusCode() === 200, 'Acesso autorizado');
    }
    echo "OK: 11 formatos, dimensões, metas de compressão, SVG vetorial, cópia, confirmação, exclusão e permissões.\n";
} finally {
    File::deleteDirectory($pasta);
}
