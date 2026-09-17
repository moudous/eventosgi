<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Atividade;
use App\Services\FormularioInscricaoService;
use Illuminate\Http\Request;

$atividade = new Atividade;
$atividade->formulario = ['rastreios' => [
    ['titulo' => 'Instagram', 'codigo' => 'instagram', 'ativo' => true, 'predefinido' => true],
    ['titulo' => 'Facebook', 'codigo' => 'facebook', 'ativo' => false, 'predefinido' => true],
]];
$metodo = new ReflectionMethod(FormularioInscricaoService::class, 'rastreioAtivo');
$servico = app(FormularioInscricaoService::class);

$ativo = $metodo->invoke($servico, Request::create('/?utm=instagram'), $atividade);
$inativo = $metodo->invoke($servico, Request::create('/?utm=facebook'), $atividade);
$inventado = $metodo->invoke($servico, Request::create('/?utm=origem-inventada'), $atividade);

if ($ativo !== 'instagram' || $inativo !== null || $inventado !== null) {
    throw new RuntimeException('Somente códigos UTM ativos da atividade devem ser registrados na inscrição.');
}

echo "OK: rastreio UTM ativo validado.\n";
