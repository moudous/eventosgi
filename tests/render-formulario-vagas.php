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
use Illuminate\Support\MessageBag;
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
$atividade->formulario = ['mensagem_identificacao' => 'Informe seu e-mail e sua senha para entrar. Caso ainda não possua uma senha, solicite um código temporário por e-mail.'];
if ($atividade->mensagemIdentificacao() !== Atividade::MENSAGEM_IDENTIFICACAO
    || str_contains($atividade->mensagemIdentificacao(), 'código temporário')) {
    throw new RuntimeException('A mensagem antiga deve ser convertida para a orientação de senha temporária.');
}
$participante = new Participante(['nome' => 'Pessoa Teste', 'cpf' => '12345678901']);
$config = [
    'titulo' => 'Formulário', 'limitar_inscricoes' => true, 'limite_inscricoes' => 12,
    'campos' => [
        ['nome' => 'periodo', 'label' => 'Período', 'tipo' => 'select', 'criterio_vagas' => true, 'opcoes' => [['valor' => 'manha', 'texto' => 'Manhã', 'percentual_vagas' => 25]]],
        ['nome' => 'interesses', 'label' => 'Interesses', 'tipo' => 'checkbox', 'obrigatorio' => false, 'criterio_vagas' => true, 'opcoes' => [['valor' => 'arte', 'texto' => 'Arte', 'percentual_vagas' => 50], ['valor' => 'musica', 'texto' => 'Música', 'percentual_vagas' => 50]]],
        ['nome' => 'libras', 'label' => 'Inscrever em Libras?', 'texto_opcao' => 'Quero cursar LIBRAS', 'tipo' => 'checkbox', 'obrigatorio' => false, 'criterio_vagas' => true, 'percentual_vagas' => 25, 'opcoes' => []],
        ['nome' => 'declaracao', 'label' => 'Declaro ter disponibilidade', 'tipo' => 'checkbox', 'obrigatorio' => true, 'opcoes' => []],
    ],
    'distribuicao_vagas' => ['total' => ['disponiveis' => 12, 'usadas' => 8, 'restantes' => 4], 'criterios' => ['periodo'], 'niveis' => [['campo' => 'periodo', 'contextos' => ['[]' => ['opcoes' => ['manha' => ['disponiveis' => 3, 'usadas' => 1, 'restantes' => 2]]]]]], 'reservas_checkbox' => [['campo' => 'interesses', 'opcoes' => ['arte' => ['disponiveis' => 6, 'usadas' => 4, 'restantes' => 2], 'musica' => ['disponiveis' => 6, 'usadas' => 6, 'restantes' => 0]]], ['campo' => 'libras', 'opcoes' => ['1' => ['disponiveis' => 3, 'usadas' => 3, 'restantes' => 0]]]]],
];
$html = view('atividades.formulario-publico', [
    'atividade' => $atividade, 'config' => $config,
    'identificacao' => ['email' => 'pessoa@example.com'], 'participante' => $participante,
    'estado' => ['aberto' => true, 'motivo' => null, 'mensagem' => null], 'inscricao' => null,
    'dadosComprovante' => [], 'respostasComprovante' => [],
    'qrPresenca' => null,
])->render();

if (! str_contains($html, 'Vagas restantes: 4') || str_contains($html, 'Manhã — 1/2')) {
    throw new RuntimeException('O total deve aparecer e os contadores hierárquicos devem respeitar sua configuração.');
}
if (substr_count($html, 'type="checkbox"') < 2 || ! str_contains($html, 'name="interesses[]"') || ! str_contains($html, 'value="arte" checked') || ! str_contains($html, 'Arte <span class="text-muted">— 2 vaga(s) restante(s)</span>')) {
    throw new RuntimeException('O campo checkbox não foi renderizado como caixas de seleção.');
}
if (! preg_match('/value="musica"[^>]*disabled/', $html) || preg_match('/value="musica"[^>]*checked/', $html)) {
    throw new RuntimeException('A opção checkbox esgotada deve ficar desmarcada e desabilitada.');
}
if (! preg_match('/name="libras" value="1"[^>]*disabled/', $html)
    || ! str_contains($html, '>Inscrever em Libras? ')
    || ! str_contains($html, '>Quero cursar LIBRAS <span class="text-muted">— 0 vaga(s) restante(s)</span>')) {
    throw new RuntimeException('O checkbox simples esgotado deve exibir o saldo e ficar desabilitado.');
}
if (! str_contains($html, 'name="declaracao" value="1"') || ! str_contains($html, '>Declaro ter disponibilidade *')) {
    throw new RuntimeException('O checkbox de declaração única não foi renderizado.');
}
if (! str_contains($html, 'id="inicio-formulario"') || ! str_contains($html, 'Você já entrou com')) {
    throw new RuntimeException('A âncora do início do formulário não foi renderizada no aviso de identificação.');
}
$htmlIdentificacao = view('atividades.formulario-publico', [
    'atividade' => $atividade, 'config' => $config,
    'identificacao' => null, 'participante' => null,
    'estado' => ['aberto' => true, 'motivo' => null, 'mensagem' => null], 'inscricao' => null,
    'dadosComprovante' => [], 'respostasComprovante' => [],
    'qrPresenca' => null,
])->render();
if (! str_contains($htmlIdentificacao, 'input-group senha-inscricao') || ! str_contains($htmlIdentificacao, '@media (max-width: 575.98px)')) {
    throw new RuntimeException('O campo e o botão da senha devem ser empilhados em telas de celular.');
}
if (str_contains($htmlIdentificacao, 'Código recebido')
    || ! str_contains($htmlIdentificacao, 'Esqueceu a senha ou precisa de uma nova?')
    || ! str_contains($htmlIdentificacao, 'id="recuperacaoSenha" class="d-none"')
    || ! str_contains($htmlIdentificacao, 'Enviar senha para o e-mail')) {
    throw new RuntimeException('A recuperação deve ficar oculta e usar a senha temporária no campo Senha.');
}
$gerenciadorSessao = app('session');
$request->session()->put('senha_temporaria_enviada', 'marcelo33@nossafco.com.br');
$app->instance('session', $request->session());
$htmlSenhaEnviada = view('atividades.formulario-publico', [
    'atividade' => $atividade, 'config' => $config,
    'identificacao' => null, 'participante' => null,
    'estado' => ['aberto' => true, 'motivo' => null, 'mensagem' => null], 'inscricao' => null,
    'dadosComprovante' => [], 'respostasComprovante' => [],
    'qrPresenca' => null,
])->render();
if (substr_count($htmlSenhaEnviada, 'A senha temporária foi enviada para') !== 2
    || ! str_contains($htmlSenhaEnviada, '<strong>marcelo33@nossafco.com.br</strong>')) {
    throw new RuntimeException('A confirmação da senha temporária deve aparecer no topo e junto ao CAPTCHA.');
}
$request->session()->forget('senha_temporaria_enviada');
$app->instance('session', $gerenciadorSessao);
$errosModal = new ViewErrorBag;
$errosModal->put('identificacao', new MessageBag(['senha_nova' => ['A confirmação da nova senha não confere.']]));
view()->share('errors', $errosModal);
$htmlModalSenha = view('atividades.formulario-publico', [
    'atividade' => $atividade, 'config' => $config,
    'identificacao' => ['email' => 'pessoa@example.com'], 'participante' => $participante,
    'estado' => ['aberto' => true, 'motivo' => null, 'mensagem' => null], 'inscricao' => null,
    'dadosComprovante' => [], 'respostasComprovante' => [],
    'qrPresenca' => null,
])->render();
if (! str_contains($htmlModalSenha, 'Deseja cadastrar uma nova senha?')
    || ! str_contains($htmlModalSenha, 'Não, apenas entrar')
    || ! str_contains($htmlModalSenha, 'Salvar a senha e entrar')) {
    throw new RuntimeException('O modal de nova senha deve oferecer os fluxos Sim e Não após a senha temporária.');
}
view()->share('errors', new ViewErrorBag);
$identificacaoService = (new ReflectionClass(App\Services\IdentificacaoParticipanteService::class))->newInstanceWithoutConstructor();
$gerarSenha = new ReflectionMethod($identificacaoService, 'gerarSenhaTemporaria');
for ($i = 0; $i < 100; $i++) {
    $senhaTemporaria = $gerarSenha->invoke($identificacaoService);
    if (! preg_match('/^(?=.*[A-Z])(?=.*[0-9])[A-Z0-9]{8}$/', $senhaTemporaria)) {
        throw new RuntimeException('A senha temporária deve ter oito caracteres, incluindo letras e números.');
    }
}
$conteudoEmail = (new ReflectionMethod($identificacaoService, 'conteudo'))->invoke($identificacaoService, $atividade, 'AB12CD34');
if (! str_contains($conteudoEmail, 'campo <strong>Senha</strong>')
    || ! str_contains($conteudoEmail, 'vale por 15 minutos')
    || str_contains($conteudoEmail, 'Definir nova senha')) {
    throw new RuntimeException('O e-mail deve orientar o uso da senha temporária no novo fluxo.');
}
$atividade->formulario = $config;
$regras = app(App\Services\FormularioInscricaoService::class)->regras($atividade);
if (validator(['periodo' => 'manha', 'interesses' => ['arte'], 'declaracao' => '1'], $regras)->fails()
    || validator(['periodo' => 'manha', 'declaracao' => '1'], $regras)->fails()
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
if (! str_contains($html, 'Vagas restantes: 4') || ! str_contains($html, 'Manhã — 1/2')) {
    throw new RuntimeException('Os contadores de vagas não foram renderizados quando habilitados.');
}
preg_match_all('#<script(?:\s[^>]*)?>(.*?)</script>#s', $html, $scripts);
file_put_contents('/tmp/formulario-vagas-renderizado.js', implode("\n", $scripts[1]));
echo "OK: total e disponibilidade por item renderizados.\n";
