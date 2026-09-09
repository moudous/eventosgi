<?php
// Execute com: php tests/formulario-sessao.php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Atividade;
use App\Services\IdentificacaoParticipanteService;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;

function conferir(bool $condicao, string $mensagem): void {
    if (! $condicao) throw new RuntimeException($mensagem);
}
$servico = (new ReflectionClass(IdentificacaoParticipanteService::class))->newInstanceWithoutConstructor();
$request = Request::create('/formularios/teste');
$request->setLaravelSession(new Store('teste', new ArraySessionHandler(120)));
$a = new Atividade;
$a->id = 1;
$b = new Atividade;
$b->id = 2;
try {
    Carbon::setTestNow(Carbon::parse('2026-09-09 12:00:00'));
    $request->session()->put('identificacao_formularios', [
        'participante_id' => 123, 'email' => 'teste@example.com', 'ultimo_acesso' => now()->timestamp,
    ]);
    Carbon::setTestNow(now()->addMinutes(29));
    conferir($servico->daSessao($request, $b)['email'] === 'teste@example.com', 'Identificação deve funcionar em outra atividade.');
    Carbon::setTestNow(now()->addMinutes(29));
    conferir($servico->daSessao($request, $a) !== null, 'Acesso deve renovar a validade.');
    Carbon::setTestNow(now()->addMinutes(30));
    conferir($servico->daSessao($request, $b) === null, 'Sessão deve expirar aos 30 minutos sem acesso.');
    conferir(! $request->session()->has('identificacao_formularios'), 'Identificação expirada deve ser removida.');
    $request->session()->put('identificacao_formularios', ['participante_id' => 123, 'email' => 'teste@example.com', 'ultimo_acesso' => now()->timestamp]);
    $servico->esquecer($request, $b);
    conferir($servico->daSessao($request, $a) === null, 'Trocar e-mail deve sair de todas as atividades.');
    $a->hash_publica = str_repeat('a', 64);
    $url = route('inscricoes.publica', ['atividade' => $a->hash_publica]);
    conferir(str_ends_with($url, '/formularios/'.str_repeat('a', 64)), 'URL deve conter somente a hash.');
    $formulario = app(App\Services\FormularioInscricaoService::class);
    $a->formulario = ['abertura' => now()->addMinute()->toDateTimeString()];
    conferir($formulario->estado($a)['motivo'] === 'antes', 'Abertura deve restringir acesso.');
    $a->formulario = ['fechamento' => now()->subMinute()->toDateTimeString()];
    conferir($formulario->estado($a)['motivo'] === 'fechado', 'Fechamento deve restringir acesso.');
    echo "OK: sessão compartilhada, renovação, expiração, troca de e-mail, URL e datas.\n";
} finally {
    Carbon::setTestNow();
}
