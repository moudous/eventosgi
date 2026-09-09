<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\AtividadeController;
use App\Models\Atividade;
use App\Services\ConteudoEditorFormularioService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

$temporario = tempnam(sys_get_temp_dir(), 'editor-imagem-');
file_put_contents($temporario, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
$request = Request::create('/upload', 'POST', [], [], [
    'imagem' => new UploadedFile($temporario, 'pixel.png', 'image/png', null, true),
]);
$atividade = new Atividade(['nome' => 'Teste']);
$atividade->id = 999999;
$atividade->hash_publica = str_repeat('b', 64);
$atividade->exists = true;
$editor = new ConteudoEditorFormularioService;

try {
    $resposta = (new AtividadeController)->enviarImagemEditor($request, $atividade, $editor);
    $url = $resposta->getData(true)['url'] ?? '';
    if (! preg_match('#/formularios/b{64}/editor/imagens/[a-f0-9-]{36}\.png$#', $url)) {
        throw new RuntimeException('A URL pública da imagem não foi gerada corretamente.');
    }
    $nome = basename(parse_url($url, PHP_URL_PATH));
    if (! is_file($editor->pasta($atividade).'/'.$nome)) throw new RuntimeException('A imagem não foi salva na pasta do editor.');
    $arquivo = (new AtividadeController)->imagemEditor($atividade, $nome, $editor);
    $cache = $arquivo->headers->get('Cache-Control');
    if (! str_contains($cache, 'public') || ! str_contains($cache, 'immutable') || ! str_contains($cache, 'max-age=31536000')) {
        throw new RuntimeException('A imagem não foi marcada para visualização pública.');
    }
    echo "OK: imagem salva e disponibilizada por URL pública.\n";
} finally {
    File::deleteDirectory($editor->pasta($atividade));
    @unlink($temporario);
}
