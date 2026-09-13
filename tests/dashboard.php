<?php

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Illuminate\Http\Request::create('/dashboard', 'GET');
$view = (new App\Http\Controllers\DashboardController())($request);
$html = $view->render();

foreach (['Dashboard', 'Eventos', 'Atividades', 'Convidados', 'Trabalhos submetidos', 'Últimas 10 inscrições', 'Campo combo'] as $texto) {
    if (! str_contains($html, $texto)) {
        throw new RuntimeException('Texto ausente no dashboard: ' . $texto);
    }
}

foreach (['inscricoes-categoria', 'inscritos-opcao', 'evolucao-inscricoes', 'inscricoes-dispositivo'] as $card) {
    foreach (['html', 'pdf', 'imagem'] as $formato) {
        $caminho = "/dashboard/exportar/{$card}/{$formato}";
        if (! str_contains($html, $caminho)) {
            throw new RuntimeException("Link de exportação ausente: {$caminho}");
        }
    }
}

$atividadeCombo = App\Models\Atividade::query()->get(['id', 'evento_id', 'formulario'])->first(
    fn (App\Models\Atividade $atividade) => collect($atividade->formulario['campos'] ?? [])->contains(
        fn (array $campo) => ($campo['tipo'] ?? '') === 'select' && ! empty($campo['nome']) && ! empty($campo['opcoes']),
    ),
);

if ($atividadeCombo) {
    $campo = collect($atividadeCombo->formulario['campos'])->first(
        fn (array $item) => ($item['tipo'] ?? '') === 'select' && ! empty($item['nome']) && ! empty($item['opcoes']),
    );
    $request = Illuminate\Http\Request::create('/dashboard', 'GET', [
        'evento' => $atividadeCombo->evento_id,
        'atividade' => $atividadeCombo->id,
        'campo' => $campo['nome'],
    ]);
    $view = (new App\Http\Controllers\DashboardController())($request);
    $dados = $view->getData();
    $html = $view->render();

    if (! str_contains($html, e($campo['label'] ?? $campo['nome']))) {
        throw new RuntimeException('O filtro não selecionou o campo combo solicitado.');
    }

    if ($dados['grafico']['total'] > 0) {
        $soma = array_sum(array_column($dados['grafico']['itens'], 'quantidade'));
        if ($soma !== $dados['grafico']['total'] || ! str_contains($html, 'graficoInscritos')) {
            throw new RuntimeException('Os dados do gráfico não representam todas as inscrições.');
        }
    }
}


$dados = $view->getData();
$exportacao = app(App\Services\DashboardExportService::class);
foreach (['inscricoes-categoria', 'inscritos-opcao', 'evolucao-inscricoes', 'inscricoes-dispositivo'] as $card) {
    $relatorio = $exportacao->relatorio($card, $dados);
    $htmlExportado = view('dashboard-exportacao', ['relatorio' => $relatorio, 'pdf' => false])->render();
    if (! str_contains($htmlExportado, $relatorio['titulo'])) {
        throw new RuntimeException("HTML da exportação não renderizado: {$card}");
    }
}

$imagem = $exportacao->png($exportacao->relatorio('inscricoes-categoria', $dados));
if (! str_starts_with($imagem, "\x89PNG\r\n\x1a\n")) {
    throw new RuntimeException('A exportação de imagem não gerou um PNG válido.');
}

$urlAssinada = Illuminate\Support\Facades\URL::temporarySignedRoute('dashboard.exportar', now()->addMinutes(5), [
    'card' => 'inscricoes-categoria',
    'formato' => 'pdf',
]);
if (! Illuminate\Support\Facades\URL::hasValidSignature(Illuminate\Http\Request::create($urlAssinada))) {
    throw new RuntimeException('A URL temporária da exportação não possui assinatura válida.');
}

$controladorExportacao = new App\Http\Controllers\DashboardExportController;
foreach ([
    'html' => ['text/html', '<!doctype html>'],
    'pdf' => ['application/pdf', '%PDF-'],
    'imagem' => ['image/png', "\x89PNG\r\n\x1a\n"],
] as $formato => [$tipo, $assinatura]) {
    $resposta = $controladorExportacao->show(
        Illuminate\Http\Request::create('/dashboard/exportacoes/inscricoes-categoria/'.$formato, 'GET'),
        'inscricoes-categoria',
        $formato,
        $exportacao,
    );
    if (! str_starts_with((string) $resposta->headers->get('Content-Type'), $tipo)
        || ! str_starts_with($resposta->getContent(), $assinatura)
        || ! str_starts_with((string) $resposta->headers->get('Content-Disposition'), 'attachment;')) {
        throw new RuntimeException("Resposta inválida na exportação {$formato}.");
    }
}

echo 'Dashboard renderizado: ' . strlen($html) . " bytes\n";
