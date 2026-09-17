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
    'instituicoesEnsino' => [App\Services\FormularioInscricaoService::INSTITUICAO_PADRAO, 'IF Goiano'],
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
if (! str_contains($html, 'id="participante_instituicao"')
    || ! preg_match('/<option value="FCO - Faculdade de Ciências Odontológicas"[^>]*selected/', $html)
    || ! str_contains($html, '<option value="__outra__"')
    || ! str_contains($html, 'name="participante[instituicao_ensino_outra_ativa]"')
    || ! str_contains($html, 'id="participante_instituicao_outra_bloco"')
    || ! str_contains($html, 'atualizarCampoOutraInstituicao')) {
    throw new RuntimeException('A instituição deve usar o combo cadastrado e revelar a opção Outra pelo controle lateral.');
}
if (! str_contains($html, "seletorInstituicao.value = '__outra__'")
    || ! str_contains($html, '? instituicaoSelecionadaAntesDeOutra')
    || ! str_contains($html, ": '';")) {
    throw new RuntimeException('Ao alternar Outra, o combo deve exibir um traço e depois restaurar a seleção anterior.');
}
if (! str_contains($html, 'id="formularioInscricaoAtividade"')
    || ! str_contains($html, "addEventListener('invalid'")
    || ! str_contains($html, "scrollIntoView({behavior: 'smooth', block: 'center'})")
    || ! str_contains($html, 'chavesDeErroDoServidor')) {
    throw new RuntimeException('O formulário deve destacar e navegar até o primeiro campo inválido.');
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
$estadoReserva = ['aberto' => true, 'motivo' => null, 'mensagem' => null, 'lista_reserva' => true];
foreach ([null, ['email' => 'pessoa@example.com']] as $identificacaoReserva) {
    $htmlReserva = view('atividades.formulario-publico', [
        'atividade' => $atividade, 'config' => $config,
        'identificacao' => $identificacaoReserva, 'participante' => $identificacaoReserva ? $participante : null,
        'estado' => $estadoReserva, 'inscricao' => null,
        'dadosComprovante' => [], 'respostasComprovante' => [], 'qrPresenca' => null,
    ])->render();
    if (! str_contains($htmlReserva, Atividade::MENSAGEM_LISTA_RESERVA)
        || str_contains($htmlReserva, 'Vagas restantes:')) {
        throw new RuntimeException('O aviso além do limite deve aparecer antes e depois da identificação, sem saldo de vagas.');
    }
    if (preg_match('/value="musica"[^>]*disabled/', $htmlReserva)
        || preg_match('/name="libras" value="1"[^>]*disabled/', $htmlReserva)) {
        throw new RuntimeException('Os critérios esgotados devem continuar disponíveis para a lista de reserva.');
    }
}
$inscricaoReserva = new App\Models\InscricaoAtividade(['lista_reserva' => true, 'comprovante_hash' => str_repeat('d', 64)]);
$inscricaoReserva->id = 99;
$htmlJaInscrito = view('atividades.formulario-publico', [
    'atividade' => $atividade, 'config' => $config,
    'identificacao' => ['email' => 'pessoa@example.com'], 'participante' => $participante,
    'estado' => ['aberto' => false, 'motivo' => 'duplicada', 'mensagem' => Atividade::MENSAGEM_JA_INSCRITO, 'lista_reserva' => false],
    'inscricao' => $inscricaoReserva, 'dadosComprovante' => [], 'respostasComprovante' => [], 'qrPresenca' => null,
])->render();
if (str_contains($htmlJaInscrito, 'Vagas restantes:')
    || str_contains($htmlJaInscrito, Atividade::MENSAGEM_LISTA_RESERVA)
    || ! str_contains($htmlJaInscrito, 'Respostas do formulário')
    || ! str_contains($htmlJaInscrito, 'Inscrição além do limite de vagas')) {
    throw new RuntimeException('Quem já se inscreveu deve ver a condição de reserva somente junto às respostas do comprovante.');
}
$htmlPix = view('atividades.formulario-publico', [
    'atividade' => $atividade, 'config' => $config,
    'identificacao' => ['email' => 'pessoa@example.com'], 'participante' => $participante,
    'estado' => ['aberto' => false, 'motivo' => 'duplicada', 'mensagem' => Atividade::MENSAGEM_JA_INSCRITO, 'lista_reserva' => false],
    'inscricao' => $inscricaoReserva, 'dadosComprovante' => [], 'respostasComprovante' => [], 'qrPresenca' => null,
    'cancelamentoBloqueadoPix' => true,
])->render();
if (! preg_match('/<button[^>]*disabled[^>]*>.*?Apagar inscrição/s', $htmlPix)
    || ! str_contains($htmlPix, 'Inscrições com pagamento PIX não podem ser apagadas.')
    || str_contains($htmlPix, 'id="apagarInscricaoModal"')) {
    throw new RuntimeException('A inscrição com PIX deve exibir o botão de exclusão desabilitado e não renderizar o modal.');
}
$configComPix = $config;
$configComPix['campos'][] = ['nome' => 'pagamento', 'label' => 'Pagamento', 'tipo' => 'pagamento_pix', 'valor_pix' => 50];
$cobrancaConfirmada = new App\Models\PixCobranca([
    'inscricao_atividade_id' => $inscricaoReserva->id,
    'txid' => 'TX-COMPROVANTE',
    'valor' => 50,
    'status' => 'CONCLUIDA',
    'pago_em' => now(),
]);
$cobrancaConfirmada->id = 321;
$htmlPagamentoConfirmado = view('atividades.formulario-publico', [
    'atividade' => $atividade, 'config' => $configComPix,
    'identificacao' => ['email' => 'pessoa@example.com'], 'participante' => $participante,
    'estado' => ['aberto' => false, 'motivo' => 'duplicada', 'mensagem' => Atividade::MENSAGEM_JA_INSCRITO, 'lista_reserva' => false],
    'inscricao' => $inscricaoReserva, 'dadosComprovante' => [], 'respostasComprovante' => [], 'qrPresenca' => null,
    'cancelamentoBloqueadoPix' => true,
    'cobrancasPix' => collect([['model' => $cobrancaConfirmada, 'qr' => ['imagem' => 'data:image/png;base64,AAAA', 'codigo' => 'PIX-CODIGO']]]),
])->render();
if (str_contains($htmlPagamentoConfirmado, 'alt="QR Code para pagamento PIX"')
    || ! str_contains($htmlPagamentoConfirmado, 'Baixar comprovante PIX')
    || ! str_contains($htmlPagamentoConfirmado, '/pix/321/comprovante.pdf')) {
    throw new RuntimeException('Após a confirmação, o QR Code deve ser substituído pelo link do comprovante PIX.');
}
$inscricaoReserva->participante_email = 'pessoa@example.com';
$inscricaoReserva->setRelation('atividade', $atividade);
$inscricaoReserva->setRelation('participante', $participante);
$cobrancaConfirmada->setRelation('inscricao', $inscricaoReserva);
$pdfPix = new Dompdf\Dompdf(new Dompdf\Options);
$pdfPix->loadHtml(view('atividades.comprovante-pix-pdf', ['cobranca' => $cobrancaConfirmada])->render(), 'UTF-8');
$pdfPix->render();
if (! str_starts_with($pdfPix->output(), '%PDF-')) {
    throw new RuntimeException('O comprovante PIX não gerou um PDF válido.');
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
$conteudoEmail = (new ReflectionMethod($identificacaoService, 'conteudo'))->invoke($identificacaoService, $atividade, 'AB12CD34', 'https://example.test/senha');
if (! str_contains($conteudoEmail, 'campo <strong>Senha</strong>')
    || ! str_contains($conteudoEmail, 'vale por 48 horas')
    || ! str_contains($conteudoEmail, 'link válido por 48 horas e para um único uso')
    || str_contains($conteudoEmail, 'Definir nova senha')) {
    throw new RuntimeException('O e-mail deve orientar o uso da senha temporária no novo fluxo.');
}
$atividade->formulario = $config;
$servicoFormulario = app(App\Services\FormularioInscricaoService::class);
$regras = $servicoFormulario->regras($atividade);
if (validator(['periodo' => 'manha', 'interesses' => ['arte'], 'declaracao' => '1'], $regras)->fails()
    || validator(['periodo' => 'manha', 'declaracao' => '1'], $regras)->fails()
    || validator(['periodo' => 'manha', 'interesses' => ['opcao-invalida'], 'declaracao' => '1'], $regras)->passes()
    || validator(['periodo' => 'manha', 'interesses' => ['arte']], $regras)->passes()
    || validator(['periodo' => 'manha', 'interesses' => ['arte'], 'declaracao' => '0'], $regras)->passes()) {
    throw new RuntimeException('A validação do campo checkbox não preservou obrigatoriedade e opções permitidas.');
}
$regrasParticipante = $servicoFormulario->regrasParticipante();
if (validator(['participante' => ['instituicao_ensino_outra_ativa' => '1', 'instituicao_ensino_outra' => '']], $regrasParticipante)->passes()) {
    throw new RuntimeException('Uma nova instituição deve ser obrigatória quando a opção Outra estiver ativa.');
}
$requisicaoInstituicao = Request::create('/', 'POST', ['participante' => [
    'instituicao_ensino' => 'FCO',
    'instituicao_ensino_outra_ativa' => '1',
    'instituicao_ensino_outra' => '  Nova Faculdade  ',
]]);
(new ReflectionMethod($servicoFormulario, 'normalizarInstituicaoEnsino'))->invoke($servicoFormulario, $requisicaoInstituicao, true);
if ($requisicaoInstituicao->input('participante.instituicao_ensino') !== 'Nova Faculdade') {
    throw new RuntimeException('A instituição digitada em Outra deve substituir a opção do combo antes de salvar.');
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
