<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Atividade;
use App\Models\Evento;
use App\Models\Participante;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\ViewErrorBag;

$request = Request::create('/formularios/'.str_repeat('a', 64));
$request->setLaravelSession(new Store('vagas', new ArraySessionHandler(120)));
$request->session()->flashInput(['interesses' => ['arte']]);
$app->instance('request', $request);
view()->share('errors', new ViewErrorBag);
$atividade = new Atividade(['nome' => 'Atividade', 'ativo' => true, 'formulario' => []]);
$atividade->id = 3;
$atividade->hash_publica = str_repeat('a', 64);
$atividade->exists = true;
$atividade->setRelation('evento', new Evento(['nome' => 'Evento']));
$participante = new Participante(['nome' => 'Pessoa Teste', 'cpf' => '12345678901']);
$config = [
    'titulo' => 'Formulário', 'limitar_inscricoes' => true, 'limite_inscricoes' => 12,
    'campos' => [
        ['nome' => 'periodo', 'label' => 'Período', 'tipo' => 'select', 'criterio_vagas' => true, 'opcoes' => [['valor' => 'manha', 'texto' => 'Manhã', 'percentual_vagas' => 25]]],
        ['nome' => 'interesses', 'label' => 'Interesses', 'tipo' => 'checkbox', 'obrigatorio' => true, 'opcoes' => [['valor' => 'arte', 'texto' => 'Arte'], ['valor' => 'musica', 'texto' => 'Música']]],
        ['nome' => 'declaracao', 'label' => 'Declaro ter disponibilidade', 'tipo' => 'checkbox', 'obrigatorio' => true, 'opcoes' => []],
    ],
    'distribuicao_vagas' => ['total' => ['disponiveis' => 12, 'usadas' => 8, 'restantes' => 4], 'criterios' => ['periodo'], 'niveis' => [['campo' => 'periodo', 'contextos' => ['[]' => ['opcoes' => ['manha' => ['disponiveis' => 3, 'usadas' => 1, 'restantes' => 2]]]]]]],
];
$html = view('atividades.formulario-publico', [
    'atividade' => $atividade, 'config' => $config,
    'identificacao' => ['email' => 'pessoa@example.com'], 'participante' => $participante,
    'estado' => ['aberto' => true, 'motivo' => null, 'mensagem' => null], 'inscricao' => null,
    'dadosComprovante' => [], 'respostasComprovante' => [],
    'qrPresenca' => null,
])->render();

if (str_contains($html, 'Vagas: 8/12') || str_contains($html, 'Manhã — 1/2')) {
    throw new RuntimeException('Os contadores de vagas devem ficar ocultos por padrão.');
}
if (substr_count($html, 'type="checkbox"') < 2 || ! str_contains($html, 'name="interesses[]"') || ! str_contains($html, 'value="arte" checked') || ! str_contains($html, '>Arte</label>')) {
    throw new RuntimeException('O campo checkbox não foi renderizado como caixas de seleção.');
}
if (! str_contains($html, 'name="declaracao" value="1"') || ! str_contains($html, '>Declaro ter disponibilidade *')) {
    throw new RuntimeException('O checkbox de declaração única não foi renderizado.');
}
if (! str_contains($html, 'id="inicio-formulario"') || ! str_contains($html, 'Você já entrou com')) {
    throw new RuntimeException('A âncora do início do formulário não foi renderizada no aviso de identificação.');
}
$atividade->formulario = $config;
$regras = app(App\Services\FormularioInscricaoService::class)->regras($atividade);
if (validator(['periodo' => 'manha', 'interesses' => ['arte'], 'declaracao' => '1'], $regras)->fails()
    || validator(['periodo' => 'manha', 'interesses' => [], 'declaracao' => '1'], $regras)->passes()
    || validator(['periodo' => 'manha', 'interesses' => ['opcao-invalida'], 'declaracao' => '1'], $regras)->passes()
    || validator(['periodo' => 'manha', 'interesses' => ['arte']], $regras)->passes()
    || validator(['periodo' => 'manha', 'interesses' => ['arte'], 'declaracao' => '0'], $regras)->passes()) {
    throw new RuntimeException('A validação do campo checkbox não preservou obrigatoriedade e opções permitidas.');
}
$config['mostrar_vagas_restantes'] = true;
$html = view('atividades.formulario-publico', [
    'atividade' => $atividade, 'config' => $config,
    'identificacao' => ['email' => 'pessoa@example.com'], 'participante' => $participante,
    'estado' => ['aberto' => true, 'motivo' => null, 'mensagem' => null], 'inscricao' => null,
    'dadosComprovante' => [], 'respostasComprovante' => [],
    'qrPresenca' => null,
])->render();
if (! str_contains($html, 'Vagas: 8/12') || ! str_contains($html, 'Manhã — 1/2')) {
    throw new RuntimeException('Os contadores de vagas não foram renderizados quando habilitados.');
}
preg_match_all('#<script(?:\s[^>]*)?>(.*?)</script>#s', $html, $scripts);
file_put_contents('/tmp/formulario-vagas-renderizado.js', implode("\n", $scripts[1]));
echo "OK: total e disponibilidade por item renderizados.\n";
