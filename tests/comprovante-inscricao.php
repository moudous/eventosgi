<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Atividade;
use App\Models\InscricaoAtividade;
use App\Models\Usuario;
use App\Services\ComprovanteInscricaoService;
use Dompdf\Dompdf;
use Dompdf\Options;

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
            ['nome' => 'declaracao', 'label' => 'Declaração', 'texto_opcao' => 'Aceito a declaração', 'tipo' => 'checkbox', 'opcoes' => []],
        ],
    ],
]);
$inscricao = new InscricaoAtividade([
    'participante_email' => 'pessoa@example.com',
    'resposta' => ['turno' => 'M', 'observacao' => '', 'arquivo' => ['inscricoes/documento.pdf'], 'declaracao' => '1'],
]);
$inscricao->id = 123;
$inscricao->exists = true;
$inscricao->setRelation('atividade', $atividade);
$servico = new ComprovanteInscricaoService;
$respostas = $servico->respostas($inscricao);

conferirComprovante($respostas[0]['valor'] === 'Manhã', 'O comprovante deve exibir o texto correspondente ao valor do combo.');
conferirComprovante($respostas[1]['valor'] === 'Não informado', 'Respostas vazias devem ser apresentadas claramente.');
conferirComprovante($respostas[2]['valor'] === 'documento.pdf', 'Caminhos privados não podem aparecer no comprovante.');
conferirComprovante($respostas[3]['valor'] === 'Aceito a declaração', 'Checkbox de opção única deve usar seu texto no comprovante.');
conferirComprovante(strlen((new InscricaoAtividade)->forceFill([])->comprovante_hash ?? '') === 0, 'A hash deve ser criada apenas ao gravar a inscrição.');

// Um comprovante com bastante conteúdo deve continuar cabendo em uma página A4,
// preservando um QR Code grande o suficiente para leitura.
$configuracao = $atividade->formulario;
$configuracao['registrar_presenca_qrcode'] = true;
$atividade->formulario = $configuracao;
$inscricao->forceFill(['codigo_qr' => 'EVGI-1234567890ABCDEF1234567890ABCDEF', 'created_at' => now()]);
$dadosPdf = collect(range(1, 6))->map(fn ($numero) => [
    'label' => 'Dado do participante '.$numero,
    'valor' => 'Informação cadastral '.$numero,
])->all();
$respostasPdf = collect(range(1, 18))->map(fn ($numero) => [
    'label' => 'Pergunta do formulário '.$numero,
    'valor' => 'Resposta informada pelo participante número '.$numero.'.',
])->all();
$qrPdf = $servico->qrPresenca($inscricao);
$inscricao->forceFill([
    'presente' => true,
    'data_presenca' => '2026-09-09 14:35:20',
    'presenca_validada_por' => 37,
]);
$inscricao->setRelation('validadorPresenca', new Usuario(['nome' => 'Usuário Validador']));
$dadosPresenca = $servico->presenca($inscricao);
conferirComprovante($dadosPresenca === ['data' => '09/09/2026 14:35:20', 'usuario' => 'Usuário Validador'], 'A presença deve informar data, hora e nome do usuário validador.');
$htmlPresencaComQr = view('atividades.partials.qrcode-presenca', ['qrPresenca' => $qrPdf, 'presenca' => $dadosPresenca])->render();
$htmlPresencaSemQr = view('atividades.partials.qrcode-presenca', ['qrPresenca' => null, 'presenca' => $dadosPresenca])->render();
conferirComprovante(str_contains($htmlPresencaComQr, 'col-md-6') && str_contains($htmlPresencaComQr, 'Usuário Validador'), 'A presença validada deve aparecer ao lado do QR Code.');
conferirComprovante(str_contains($htmlPresencaSemQr, 'Presença validada') && str_contains($htmlPresencaSemQr, '09/09/2026 14:35:20'), 'A presença validada deve aparecer mesmo sem QR Code.');
$htmlPdf = view('atividades.comprovante-pdf', [
    'inscricao' => $inscricao,
    'dadosParticipante' => $dadosPdf,
    'respostas' => $respostasPdf,
    'qrPresenca' => $qrPdf,
])->render();
conferirComprovante(str_contains($htmlPdf, 'width:158px') && str_contains($htmlPdf, 'class="ultracompacto"'), 'O PDF extenso deve usar o layout compacto sem reduzir excessivamente o QR Code.');
$pdf = new Dompdf(new Options(['isRemoteEnabled' => false]));
$pdf->loadHtml($htmlPdf, 'UTF-8');
$pdf->setPaper('A4');
$pdf->render();
preg_match_all('/\/Type\s*\/Page\b/', $pdf->output(), $paginas);
conferirComprovante(count($paginas[0]) === 1, 'O comprovante extenso de teste deve ser gerado em uma única página.');

echo "OK: conteúdo do comprovante e PDF compacto de uma página.\n";
