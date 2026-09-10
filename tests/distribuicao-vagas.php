<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Atividade;
use App\Services\DistribuicaoVagasService;
use Illuminate\Validation\ValidationException;

function conferirVagas(bool $condicao, string $mensagem): void
{
    if (! $condicao) throw new RuntimeException($mensagem);
}

$config = [
    'limitar_inscricoes' => true,
    'limite_inscricoes' => 12,
    'criterios_vagas' => ['periodo', 'sexo'],
    'campos' => [
        ['nome' => 'periodo', 'label' => 'Período', 'tipo' => 'select', 'criterio_vagas' => true, 'opcoes' => [
            ['valor' => 'manha', 'texto' => 'Manhã', 'percentual_vagas' => 25],
            ['valor' => 'tarde', 'texto' => 'Tarde', 'percentual_vagas' => 25],
            ['valor' => 'noite', 'texto' => 'Noite', 'percentual_vagas' => 25],
        ]],
        ['nome' => 'sexo', 'label' => 'Sexo', 'tipo' => 'select', 'criterio_vagas' => true, 'opcoes' => [
            ['valor' => 'F', 'texto' => 'Feminino', 'percentual_vagas' => 66.6667],
            ['valor' => 'M', 'texto' => 'Masculino', 'percentual_vagas' => 33.3333],
        ]],
        ['nome' => 'oficinas', 'label' => 'Oficinas', 'tipo' => 'checkbox', 'criterio_vagas' => true, 'opcoes' => [
            ['valor' => 'arte', 'texto' => 'Arte', 'percentual_vagas' => 25],
            ['valor' => 'musica', 'texto' => 'Música', 'percentual_vagas' => 0],
        ]],
        ['nome' => 'extras', 'label' => 'Incluir almoço', 'tipo' => 'checkbox', 'criterio_vagas' => true, 'percentual_vagas' => 50, 'opcoes' => []],
    ],
];
$atividade = new Atividade(['nome' => 'Teste', 'formulario' => $config]);
$atividade->id = -1;
$servico = app(DistribuicaoVagasService::class);
$resultado = $servico->recalcular($atividade, $config, false)['distribuicao_vagas'];

conferirVagas($resultado['niveis'][0]['contextos']['[]']['opcoes']['manha']['disponiveis'] === 3, 'O primeiro nível deve aplicar a cota sobre o total.');
conferirVagas($resultado['niveis'][1]['contextos']['["manha"]']['opcoes']['F']['disponiveis'] === 2, 'O segundo nível deve aplicar a cota sobre o primeiro.');
conferirVagas($resultado['niveis'][1]['contextos']['["manha"]']['opcoes']['M']['disponiveis'] === 1, 'A conversão de uma vaga em percentual deve preservar a vaga inteira.');
conferirVagas($resultado['reservas_checkbox'][0]['opcoes']['arte']['disponiveis'] === 3, 'A reserva de checkbox deve usar diretamente o total de vagas.');
conferirVagas($resultado['reservas_checkbox'][1]['opcoes']['1']['disponiveis'] === 6, 'O checkbox simples deve possuir uma reserva independente.');
conferirVagas($servico->consumoResposta(['oficinas' => ['arte'], 'extras' => '1'], $config) === 2, 'Dois checkboxes reservados devem consumir duas vagas totais.');
conferirVagas($servico->consumoResposta([], $config) === 1, 'Uma inscrição sem reserva selecionada deve continuar consumindo uma vaga geral.');
$servico->conferirDisponibilidade($atividade, ['periodo' => 'manha', 'sexo' => 'F', 'extras' => '1']);
try {
    $servico->conferirDisponibilidade($atividade, ['periodo' => 'manha', 'sexo' => 'F', 'oficinas' => ['musica']]);
    throw new RuntimeException('Uma opção checkbox sem vagas deveria ser recusada.');
} catch (ValidationException) {
    // esperado
}

$invalida = $config;
$invalida['campos'][0]['opcoes'][0]['percentual_vagas'] = 80;
$invalida['campos'][0]['opcoes'][1]['percentual_vagas'] = 30;
try {
    $servico->validarConfiguracao($invalida);
    throw new RuntimeException('A soma acima de 100% deveria ser recusada.');
} catch (ValidationException) {
    // esperado
}

echo "OK: cotas hierárquicas, reservas independentes de checkbox e limite percentual validados.\n";
