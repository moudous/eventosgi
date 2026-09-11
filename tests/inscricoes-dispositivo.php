<?php
require __DIR__.'/../vendor/autoload.php';
$servico = new App\Services\InscricoesDispositivoService();
$registros = array_map(fn ($dados) => (object) ['dispositivo' => $dados], [null, [], ['navegador'=>'Chrome','navegador_versao'=>'130'], ['navegador'=>'Chrome'], ['navegador'=>['invalido']]]);
$r = $servico->agrupar($registros, 'navegador');
if ($r['total'] !== 5 || $r['itens'][0]['quantidade'] !== 3 || $r['itens'][1]['quantidade'] !== 2 || array_sum(array_column($r['itens'], 'percentual')) !== 100.0) throw new RuntimeException('Contagem inválida');
$r = $servico->agrupar($registros, 'navegador_versao');
if (! in_array('Chrome 130', array_column($r['itens'], 'rotulo')) || ! in_array('Chrome', array_column($r['itens'], 'rotulo'))) throw new RuntimeException('Versões inválidas');
if ($servico->agrupar([], 'plataforma') !== ['total'=>0,'itens'=>[]]) throw new RuntimeException('Vazio inválido');
echo "OK: agrupamento, percentuais, versões e dados ausentes.\n";
