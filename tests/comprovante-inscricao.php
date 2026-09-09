<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Atividade;
use App\Models\InscricaoAtividade;
use App\Services\ComprovanteInscricaoService;

function conferirComprovante(bool $condicao, string $mensagem): void
{
    if (! $condicao) throw new RuntimeException($mensagem);
}

$atividade = new Atividade([
    'nome' => 'Atividade de teste',
    'formulario' => [
        'campos' => [
            ['nome' => 'turno', 'label' => 'Turno', 'opcoes' => [['valor' => 'M', 'texto' => 'Manhã']]],
            ['nome' => 'observacao', 'label' => 'Observação'],
            ['nome' => 'arquivo', 'label' => 'Documento', 'tipo' => 'file'],
        ],
    ],
]);
$inscricao = new InscricaoAtividade([
    'participante_email' => 'pessoa@example.com',
    'resposta' => ['turno' => 'M', 'observacao' => '', 'arquivo' => ['inscricoes/documento.pdf']],
]);
$inscricao->setRelation('atividade', $atividade);
$servico = new ComprovanteInscricaoService;
$respostas = $servico->respostas($inscricao);

conferirComprovante($respostas[0]['valor'] === 'Manhã', 'O comprovante deve exibir o texto correspondente ao valor do combo.');
conferirComprovante($respostas[1]['valor'] === 'Não informado', 'Respostas vazias devem ser apresentadas claramente.');
conferirComprovante($respostas[2]['valor'] === 'Arquivo enviado: documento.pdf', 'Caminhos privados não podem aparecer no comprovante.');
conferirComprovante(strlen((new InscricaoAtividade)->forceFill([])->comprovante_hash ?? '') === 0, 'A hash deve ser criada apenas ao gravar a inscrição.');

echo "OK: texto de opções, vazios e anexos do comprovante.\n";
