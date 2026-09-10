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

echo 'Dashboard renderizado: ' . strlen($html) . " bytes\n";
