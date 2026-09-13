<?php
// Execute com: php tests/url-atividade.php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Atividade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
Schema::create('atividades', function ($table): void {
    $table->id();
    $table->string('hash_publica', 64)->unique();
    $table->string('url', 180)->nullable()->unique();
    $table->softDeletes();
});
DB::table('atividades')->insert([
    ['id' => 1, 'hash_publica' => str_repeat('a', 64), 'url' => 'novo-nome-baseado-no-titulo'],
    ['id' => 2, 'hash_publica' => str_repeat('b', 64), 'url' => null],
    ['id' => 3, 'hash_publica' => str_repeat('c', 64), 'url' => null],
]);

$atividade = Atividade::findOrFail(1);
if (parse_url($atividade->urlPublica(), PHP_URL_PATH) !== '/a/novo-nome-baseado-no-titulo') {
    throw new RuntimeException('A URL amigável não foi gerada corretamente.');
}
$semUrl = Atividade::findOrFail(2);
if (parse_url($semUrl->urlPublica(), PHP_URL_PATH) !== '/formularios/'.str_repeat('b', 64)) {
    throw new RuntimeException('A URL permanente por hash deixou de funcionar como alternativa.');
}

$duplicada = validator(
    ['url' => 'novo-nome-baseado-no-titulo'],
    ['url' => [Rule::unique('atividades', 'url')->ignore(2)]],
);
if (! $duplicada->fails()) throw new RuntimeException('Uma URL duplicada foi aceita.');

$propria = validator(
    ['url' => 'novo-nome-baseado-no-titulo'],
    ['url' => [Rule::unique('atividades', 'url')->ignore(1)]],
);
if ($propria->fails()) throw new RuntimeException('A atividade não pôde manter a própria URL.');

echo "OK: URL amigável, unicidade, valor nulo e alternativa por hash validados.\n";
