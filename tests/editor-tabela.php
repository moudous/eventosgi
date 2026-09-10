<?php

require __DIR__.'/../vendor/autoload.php';

use App\Services\ConteudoEditorFormularioService;

$servico = new ConteudoEditorFormularioService;
$html = '<table onclick="alert(1)"><tbody><tr><td data-row="row-a1b2" style="color:red">Célula 1</td><td data-row="inválido"><script>alert(1)</script>Célula 2</td></tr></tbody></table>';
$limpo = $servico->sanitizar($html);

if (! str_contains($limpo, '<table>') || ! str_contains($limpo, '<tbody>') || ! str_contains($limpo, '<tr>') || ! str_contains($limpo, '<td data-row="row-a1b2" style="color:red">')) {
    throw new RuntimeException('A estrutura segura da tabela não foi preservada.');
}
if (str_contains($limpo, 'onclick') || str_contains($limpo, '<script') || str_contains($limpo, 'data-row="inválido"')) {
    throw new RuntimeException('A sanitização da tabela permitiu conteúdo inseguro.');
}
$editor = file_get_contents(__DIR__.'/../resources/views/atividades/formulario.blade.php');
foreach (['insertTable', 'insertRowBelow', 'deleteRow', 'insertColumnRight', 'deleteColumn', 'deleteTable'] as $acao) {
    if (! str_contains($editor, $acao)) throw new RuntimeException("O editor não oferece a ação de tabela {$acao}.");
}

echo "OK: tabelas podem ser inseridas, alteradas, excluídas e são sanitizadas.\n";
