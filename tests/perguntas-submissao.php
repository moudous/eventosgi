<?php
// php tests/perguntas-submissao.php — validação e renderização sem alterar o banco.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Submissao, InscricaoSubmissaoTrabalho};
use App\Http\Controllers\SubmissaoPublicaController;
use Illuminate\Http\Request;
use Illuminate\Session\{Store, ArraySessionHandler};
use Illuminate\Validation\ValidationException;

function check(bool $ok, string $mensagem): void {
    if (! $ok) throw new RuntimeException($mensagem);
}
$submissao = new Submissao(['id' => 1]);
$submissao->id = 1;
$campos = ['apresentacao' => 'mostrar_apresentacao', 'aprovacao_comite_etica' => 'mostrar_aprovacao_comite_etica', 'tem_apoio_financeiro' => 'mostrar_apoio_financeiro'];
$base = ['palavras_chave' => 'saúde pública, educação, qualidade de vida', 'titulo_trabalho' => 'Resumo de teste', 'email' => 'autor@example.com', 'primeiro_autor' => 'Autor Teste', 'primeiro_autor_afiliacao' => 'Universidade'];
$metodo = new ReflectionMethod(SubmissaoPublicaController::class, 'validarTrabalho');
$validar = function (array $dados, ?InscricaoSubmissaoTrabalho $trabalho = null) use ($app, $submissao, $metodo) {
    $request = Request::create('/', 'POST', $dados);
    $request->setLaravelSession(new Store('teste', new ArraySessionHandler(120)));
    $app->instance('request', $request);
    $app['url']->setRequest($request);
    $app['redirect']->setSession($request->session());
    return $metodo->invoke(new SubmissaoPublicaController, $request, $submissao, null, $trabalho)[0];
};
foreach ($campos as $opcao) check($submissao->{$opcao} === true, 'Pergunta deve vir habilitada');
try {
    $validar($base);
    throw new RuntimeException('Perguntas habilitadas devem ser obrigatórias');
} catch (ValidationException $e) {
    foreach ($campos as $campo => $opcao) check(isset($e->errors()[$campo]), 'Validação ausente: '.$campo);
}
for ($combinacao = 0; $combinacao < 8; $combinacao++) {
    $dados = $base;
    foreach (array_keys($campos) as $indice => $campo) {
        $habilitado = (bool) ($combinacao & (1 << $indice));
        $submissao->{$campos[$campo]} = $habilitado;
        if ($habilitado) $dados[$campo] = $campo === 'apresentacao' ? 'online' : '0';
    }
    $validar($dados);
    $html = view('submissoes.partials.formulario-trabalho', ['submissao' => $submissao, 'novoTrabalho' => true, 'acesso' => ['email' => $base['email']]])->render();
    foreach ($campos as $campo => $opcao) check(str_contains($html, 'name="'.$campo.'"') === $submissao->{$opcao}, 'Visibilidade incorreta: '.$campo);
    check(str_contains($html, 'name="apoiador"') === $submissao->mostrar_apoio_financeiro, 'Visibilidade do apoiador');
    check(str_contains($html, 'name="protocolo_comite_etica"') === $submissao->mostrar_aprovacao_comite_etica, 'Visibilidade do protocolo');
}
foreach ($campos as $opcao) $submissao->{$opcao} = false;
$existente = new InscricaoSubmissaoTrabalho(['apresentacao' => 'online', 'tem_apoio_financeiro' => true, 'apoiador' => 'Fundação', 'aprovacao_comite_etica' => true, 'protocolo_comite_etica' => 'ABC123']);
$dados = $validar($base + ['apresentacao' => 'invalida', 'tem_apoio_financeiro' => 'invalido', 'aprovacao_comite_etica' => 'invalido'], $existente);
foreach (['apresentacao', 'tem_apoio_financeiro', 'apoiador', 'aprovacao_comite_etica', 'protocolo_comite_etica'] as $campo) check($dados[$campo] === $existente->{$campo}, 'Resposta oculta deve ser preservada: '.$campo);
echo "OK: padrões, oito combinações de visibilidade, validação e preservação de respostas.\n";

$submissao->mostrar_palavras_chave = true;
foreach (['um, dois', 'um, dois, três, quatro, cinco, seis, sete', ', , '] as $invalido) {
    try {
        $validar(array_replace($base, ['palavras_chave' => $invalido]));
        throw new RuntimeException('Quantidade inválida aceita');
    } catch (ValidationException $e) {
        check(isset($e->errors()['palavras_chave']), 'Erro deve identificar palavras-chave');
    }
}
$dados = $validar(array_replace($base, ['palavras_chave' => ' saúde pública, , educação, qualidade de vida, ']));
check($dados['palavras_chave'] === 'saúde pública, educação, qualidade de vida', 'Normalização de expressões');
$submissao->min_palavras_chave = 1;
$submissao->max_palavras_chave = 2;
$validar(array_replace($base, ['palavras_chave' => 'uma expressão']));
$submissao->mostrar_palavras_chave = false;
$existente->palavras_chave = 'resposta anterior';
$dados = $validar(array_replace($base, ['palavras_chave' => ['inválido']]), $existente);
check($dados['palavras_chave'] === 'resposta anterior', 'Preservar palavras-chave quando desabilitadas');
$html = view('submissoes.partials.formulario-trabalho', ['submissao' => $submissao, 'novoTrabalho' => true, 'acesso' => ['email' => $base['email']]])->render();
check(!str_contains($html, 'name="palavras_chave"'), 'Ocultar palavras-chave quando desabilitadas');
echo "OK: limites de palavras-chave, expressões, normalização e campo desabilitado.\n";
