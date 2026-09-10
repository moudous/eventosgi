<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$check = function (bool $condicao, string $mensagem): void {
    if (! $condicao) throw new RuntimeException($mensagem);
};
$check(app()->getLocale() === 'pt_BR', 'A aplicação deve usar português do Brasil.');
$mensagemTraduzida = Illuminate\Support\Facades\Validator::make(
    ['anexo' => [1, 2, 3]],
    ['anexo' => ['array', 'max:2']],
    [],
    ['anexo' => 'Anexo de Comprovante'],
)->errors()->first('anexo');
$check(
    $mensagemTraduzida === 'O campo Anexo de Comprovante não deve conter mais de 2 elementos.',
    'A mensagem de limite deve estar em português: '.$mensagemTraduzida,
);

$atividade = new App\Models\Atividade;
$atividade->formulario = ['campos' => [[
    'nome' => 'anexos', 'label' => 'Anexos', 'tipo' => 'file',
    'obrigatorio' => true, 'max_arquivos' => 2,
]]];
$formularios = app(App\Services\FormularioInscricaoService::class);
$regras = $formularios->regras($atividade);
$check(in_array('size:2', $regras['anexos'], true), 'Campo obrigatório deve exigir todos os arquivos configurados.');

$arquivo = fn (string $nome) => Illuminate\Http\UploadedFile::fake()->create($nome, 10);
$umArquivo = Illuminate\Support\Facades\Validator::make(['anexos' => [$arquivo('um.pdf')]], $regras);
$check($umArquivo->fails(), 'Um arquivo não pode satisfazer um campo obrigatório configurado para dois.');
$doisArquivos = Illuminate\Support\Facades\Validator::make(['anexos' => [$arquivo('um.pdf'), $arquivo('dois.pdf')]], $regras);
$check($doisArquivos->passes(), 'A quantidade completa de arquivos deve ser aceita.');

$inscricao = new App\Models\InscricaoAtividade([
    'resposta' => ['anexos' => [
        'inscricoes/123e4567-e89b-12d3-a456-426614174000-minha-foto.jpg',
        'inscricoes/123e4567-e89b-12d3-a456-426614174001-meu-documento.pdf',
    ]],
]);
$inscricao->id = 25;
$inscricao->setRelation('atividade', $atividade);
$resposta = app(App\Services\ComprovanteInscricaoService::class)->respostas($inscricao)[0];
$check($resposta['arquivos'][0]['imagem'] === true, 'Imagem deve ser identificada para gerar miniatura.');
$check($resposta['arquivos'][1]['imagem'] === false, 'Documento não deve ser tratado como imagem.');
$check(str_contains($resposta['arquivos'][1]['icone'], 'pdf'), 'PDF deve receber o ícone correspondente.');
$check($resposta['arquivos'][0]['nome'] === 'minha-foto.jpg', 'O identificador interno não deve aparecer no nome do arquivo.');
$check($resposta['valor'] === 'minha-foto.jpg, meu-documento.pdf', 'Impressão e e-mail devem mostrar somente os nomes.');
$html = view('atividades.partials.comprovante-respostas', [
    'dadosParticipante' => [], 'respostas' => [$resposta],
])->render();
$check(str_contains($html, 'comprovante-anexo-miniatura'), 'Comprovante visual deve renderizar miniatura.');
$check(str_contains($html, 'bi-file-earmark-pdf-fill'), 'Comprovante visual deve renderizar ícone de PDF.');
$check(str_contains($html, 'comprovante-anexos-nomes'), 'Comprovante deve conter a versão de nomes para impressão.');

echo "OK: quantidade obrigatória, miniatura, ícone e nomes para impressão/e-mail.\n";
