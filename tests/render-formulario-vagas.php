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
    'campos' => [['nome' => 'periodo', 'label' => 'Período', 'tipo' => 'select', 'criterio_vagas' => true, 'opcoes' => [['valor' => 'manha', 'texto' => 'Manhã', 'percentual_vagas' => 25]]]],
    'distribuicao_vagas' => ['total' => ['disponiveis' => 12, 'usadas' => 8, 'restantes' => 4], 'criterios' => ['periodo'], 'niveis' => [['campo' => 'periodo', 'contextos' => ['[]' => ['opcoes' => ['manha' => ['disponiveis' => 3, 'usadas' => 1, 'restantes' => 2]]]]]]],
];
$html = view('atividades.formulario-publico', [
    'atividade' => $atividade, 'config' => $config,
    'identificacao' => ['email' => 'pessoa@example.com'], 'participante' => $participante,
    'estado' => ['aberto' => true, 'motivo' => null, 'mensagem' => null], 'inscricao' => null,
    'dadosComprovante' => [], 'respostasComprovante' => [],
])->render();

if (! str_contains($html, 'Vagas: 8/12') || ! str_contains($html, 'Manhã — 1/2')) {
    throw new RuntimeException('Os contadores de vagas não foram renderizados corretamente.');
}
preg_match_all('#<script(?:\s[^>]*)?>(.*?)</script>#s', $html, $scripts);
file_put_contents('/tmp/formulario-vagas-renderizado.js', implode("\n", $scripts[1]));
echo "OK: total e disponibilidade por item renderizados.\n";
