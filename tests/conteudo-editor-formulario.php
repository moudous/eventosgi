<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ConteudoEditorFormularioService;

function conferirEditor(bool $condicao, string $mensagem): void
{
    if (! $condicao) throw new RuntimeException($mensagem);
}

$editor = new ConteudoEditorFormularioService;
$html = $editor->sanitizar('<p class="ql-align-center" style="color: rgb(10, 20, 30)">Texto <strong>forte</strong></p>'
    .'<ol><li data-list="bullet"><span class="ql-ui"></span>Item</li></ol>'
    .'<script>alert(1)</script><img src="javascript:alert(1)" onerror="alert(2)">'
    .'<a href="https://example.com" target="_blank" onclick="alert(3)">Link</a>');

conferirEditor(str_contains($html, 'ql-align-center'), 'O alinhamento produzido pelo editor deve ser preservado.');
conferirEditor(str_contains($html, 'color: rgb(10, 20, 30)'), 'A cor do texto deve ser preservada.');
conferirEditor(str_contains($html, '<strong>forte</strong>'), 'A formatação do texto deve ser preservada.');
conferirEditor(str_contains($html, 'data-list="bullet"'), 'O estilo das listas do editor deve ser preservado.');
conferirEditor(! str_contains($html, '<script'), 'Scripts não podem chegar ao formulário público.');
conferirEditor(! str_contains($html, 'javascript:'), 'URLs executáveis não podem chegar ao formulário público.');
conferirEditor(! str_contains($html, 'onerror') && ! str_contains($html, 'onclick'), 'Eventos HTML devem ser removidos.');
conferirEditor(str_contains($html, 'rel="noopener noreferrer"'), 'Links em nova aba devem ser isolados.');

echo "OK: formatação preservada e HTML perigoso removido.\n";
