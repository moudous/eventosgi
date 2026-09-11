<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Carbon\CarbonImmutable as Data;
$servico = new App\Services\EvolucaoInscricoesService();
function verificar($ok, $mensagem) { if (! $ok) throw new RuntimeException($mensagem); }
$config = ['abertura' => '2026-09-01 10:00', 'fechamento' => '2026-09-01 10:10'];
$r = $servico->agrupar($config, ['2026-09-01 10:10', '2026-09-01 10:01', '2026-09-01 09:59', '2026-09-01 10:00'], Data::parse('2026-09-02'));
verificar($r['total'] === 3 && $r['fora'] === 1, 'Limites inclusivos e exclusão externa');
verificar(array_sum(array_column($r['itens'], 'quantidade')) === 3, 'Preservar contagem');
verificar($r['itens'][1]['quantidade'] === 1 && $r['itens'][9]['quantidade'] === 1, 'Fronteiras sem duplicação');
$r = $servico->agrupar($config, [], Data::parse('2026-09-01 10:02:30'));
verificar($r['itens'][2]['parcial'] && $r['itens'][3]['quantidade'] === null, 'Parcial e futuro');
verificar($r['itens'][0]['quantidade'] === 0, 'Zero observado');
$r = $servico->agrupar(['abertura'=>'2025-01-31','fechamento'=>'2026-01-31'], ['2025-02-28'], Data::parse('2026-02-01'));
verificar(count($r['itens']) <= 60 && array_sum(array_column($r['itens'], 'quantidade')) === 1, 'Escala longa');
$r = $servico->agrupar([], [], Data::parse('2026-01-01'));
verificar(count($r['itens']) === 1 && $r['total'] === 0, 'Sem datas e sem inscrições');
echo "OK: limites, contagens, zeros, futuro, intervalos parciais e escala longa.\n";
