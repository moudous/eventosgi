<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Atividade;
use App\Services\RecebimentosPixService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$banco = tempnam(sys_get_temp_dir(), 'eventosgi-recebimentos-test-');
config(['database.default' => 'recebimentos_test', 'database.connections.recebimentos_test' => [
    'driver' => 'sqlite', 'database' => $banco, 'prefix' => '', 'foreign_key_constraints' => false,
]]);
DB::purge('recebimentos_test');

try {
    Schema::create('eventos', function (Blueprint $t): void { $t->id(); $t->string('nome'); $t->timestamps(); $t->softDeletes(); });
    Schema::create('atividades', function (Blueprint $t): void { $t->id(); $t->string('nome'); $t->unsignedBigInteger('evento_id'); $t->longText('formulario')->nullable(); $t->timestamps(); $t->softDeletes(); });
    Schema::create('inscricoes_atividade', function (Blueprint $t): void { $t->id(); $t->unsignedBigInteger('atividade_id'); $t->unsignedBigInteger('participante_id')->nullable(); $t->string('participante_email')->nullable(); $t->json('resposta'); $t->timestamps(); });
    Schema::create('pix_cobrancas', function (Blueprint $t): void {
        $t->id(); $t->unsignedBigInteger('inscricao_atividade_id'); $t->string('campo'); $t->string('txid', 35)->unique(); $t->decimal('valor', 12, 2); $t->string('status');
        $t->text('pix_copia_cola')->nullable(); $t->string('location', 1000)->nullable(); $t->json('resposta_api')->nullable(); $t->timestamp('pago_em')->nullable(); $t->timestamps();
    });
    (require __DIR__.'/../database/migrations/2026_09_15_000000_add_pagador_fields_to_pix_cobrancas_table.php')->up();

    DB::table('eventos')->insert([['id' => 1, 'nome' => 'Evento A'], ['id' => 2, 'nome' => 'Evento B']]);
    $formulario = json_encode(['campos' => [['nome' => 'pix', 'tipo' => 'pagamento_pix', 'valor_pix' => 50]]]);
    DB::table('atividades')->insert([['id' => 10, 'evento_id' => 1, 'nome' => 'Curso A', 'formulario' => $formulario], ['id' => 20, 'evento_id' => 2, 'nome' => 'Curso B', 'formulario' => $formulario]]);
    DB::table('inscricoes_atividade')->insert([['id' => 100, 'atividade_id' => 10, 'participante_email' => 'joao@example.com', 'resposta' => '{}'], ['id' => 200, 'atividade_id' => 20, 'participante_email' => 'maria@example.com', 'resposta' => '{}']]);
    DB::table('pix_cobrancas')->insert([
        ['inscricao_atividade_id' => 100, 'campo' => 'pix', 'txid' => 'TXJOAO', 'valor' => 50, 'status' => 'CONCLUIDA', 'pagador_nome' => 'João Silva', 'pagador_documento' => '12345678901', 'pago_em' => '2026-09-10 10:00:00'],
        ['inscricao_atividade_id' => 200, 'campo' => 'pix', 'txid' => 'TXMARIA', 'valor' => 100, 'status' => 'CONCLUIDA', 'pagador_nome' => 'Maria Souza', 'pagador_documento' => '12345678000199', 'pago_em' => '2026-09-12 15:30:00'],
        ['inscricao_atividade_id' => 100, 'campo' => 'pix2', 'txid' => 'TXPENDENTE', 'valor' => 200, 'status' => 'ATIVA', 'pagador_nome' => 'Maria Souza', 'pagador_documento' => null, 'pago_em' => null],
    ]);

    $servico = app(RecebimentosPixService::class);
    $filtro = Request::create('/recebimentos/dados', 'GET', ['evento_id' => 2, 'valor_operador' => 'maior', 'valor' => '75,00', 'pagador' => '12.345.678/0001-99']);
    $resultado = $servico->consulta($filtro)->get();
    if ($resultado->count() !== 1 || $resultado->first()->txid !== 'TXMARIA') throw new RuntimeException('Os filtros combinados não selecionaram o recebimento correto.');
    if ($servico->consulta(Request::create('/', 'GET', ['valor_operador' => 'maior', 'valor' => '100']))->exists()) throw new RuntimeException('O filtro maior deve excluir valores iguais.');
    if ($servico->consulta(Request::create('/', 'GET', ['valor_operador' => 'menor', 'valor' => '50']))->exists()) throw new RuntimeException('O filtro menor deve excluir valores iguais.');
    if ($servico->consulta(Request::create('/', 'GET', ['valor_operador' => 'entre', 'valor' => '50', 'valor_ate' => '100']))->count() !== 2) throw new RuntimeException('O filtro entre valores não selecionou os limites informados.');
    $porData = $servico->consulta(Request::create('/', 'GET', ['data_inicial' => '2026-09-11', 'data_final' => '2026-09-13']))->get();
    if ($porData->count() !== 1 || $porData->first()->txid !== 'TXMARIA') throw new RuntimeException('O intervalo de datas não selecionou o recebimento correto.');
    if ($servico->consulta(Request::create('/', 'GET', ['data_inicial' => '2026-09-12', 'data_final' => '2026-09-12']))->count() !== 1) throw new RuntimeException('O filtro deve incluir todo o dia inicial e final.');

    $atividade = Atividade::findOrFail(10);
    if (! $atividade->temPagamentoPix() || $servico->consulta(new Request, $atividade)->count() !== 1) throw new RuntimeException('A listagem por atividade deve conter apenas seus pagamentos confirmados.');
    $excel = $servico->exportar($filtro)->getContent();
    if (! str_starts_with($excel, 'PK') || ! str_contains($excel, 'xl/')) throw new RuntimeException('A exportação não gerou um arquivo XLSX válido.');
    if (RecebimentosPixService::formatarDocumento('12345678000199') !== '12.345.678/0001-99') throw new RuntimeException('O CNPJ não foi formatado corretamente.');

    echo "OK: filtros, escopo por atividade, somente recebidos e exportação XLSX validados.\n";
} finally {
    DB::disconnect('recebimentos_test');
    @unlink($banco);
}
