<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controller = new App\Http\Controllers\EventoController;
$validar = new ReflectionMethod($controller, 'validar');
$check = function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
$request = fn (array $extra) => Illuminate\Http\Request::create('/eventos', 'POST', array_merge(['nome' => 'Teste', 'ativo' => '1'], $extra));
$dados = $validar->invoke($controller, $request(['cores' => ['#abcdef', '#123456']]));
$check($dados['cores'] === ['#ABCDEF', '#123456'], 'Deve preservar ordem e normalizar hexadecimal.');
$check($validar->invoke($controller, $request(['cores' => '']))['cores'] === [], 'Deve permitir paleta vazia.');
$check(count($validar->invoke($controller, $request(['cores' => array_fill(0, 32, '#112233')]))['cores']) === 32, 'Deve aceitar 32 cores.');
foreach ([['#invalid'], array_fill(0, 33, '#112233')] as $cores) {
    try {
        $validar->invoke($controller, $request(['cores' => $cores]));
        throw new RuntimeException('Paleta inválida aceita.');
    } catch (Illuminate\Validation\ValidationException $exception) {
        $check(count($exception->errors()) > 0, 'Deve reportar erro de validação.');
    }
}
$evento = new App\Models\Evento(['cores' => ['#ABCDEF', '#123456'], 'imagem' => 'teste.png']);
$check(json_decode($evento->getAttributes()['cores'], true) === ['#ABCDEF', '#123456'], 'Cores devem ser armazenadas como JSON.');
$check($evento->cores === ['#ABCDEF', '#123456'], 'Cores devem retornar na ordem salva.');
view()->share('errors', new Illuminate\Support\ViewErrorBag);
$html = view('eventos.partials.imagem-cores', ['evento' => null])->render();
$check(str_contains($html, 'Imagem e cores do evento') && str_contains($html, 'Extrair cores'), 'Card deve renderizar.');
echo "OK: validação, limite de 32 cores, ordem, JSON e renderização do card.\n";
