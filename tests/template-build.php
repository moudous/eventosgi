<?php
// php tests/template-build.php — arquivos temporários, sem alterar eventos ou templates reais.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\{TemplateBuildService, TemplatePaginaService, ZipEscritorService, ZipLeitorService, PaginaEventoRenderer};
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\{Request, UploadedFile};

function verificar(bool $condicao, string $mensagem): void { if (!$condicao) throw new RuntimeException($mensagem); }
function rejeitar(callable $acao): void { try { $acao(); } catch (ValidationException|Symfony\Component\HttpKernel\Exception\HttpException $e) { return; } throw new RuntimeException('Operação inválida foi permitida.'); }

$raiz = sys_get_temp_dir().'/template-build-test-'.bin2hex(random_bytes(6));
$service = new class(app(ZipEscritorService::class), app(TemplatePaginaService::class), $raiz) extends TemplateBuildService {
    public function __construct($zip, $templates, private string $diretorio) { parent::__construct($zip, $templates); }
    public function raiz(): string { return $this->diretorio; }
};
try {
    $estado = $service->criar('Modelo de teste'); $id = $estado['id'];
    verificar(count($estado['arquivos']) === 4, 'Devem existir quatro arquivos iniciais.');
    $acesso = $service->acessoPrevia($id);
    $service->validarAcessoPrevia($id, $acesso);
    rejeitar(fn () => $service->validarAcessoPrevia(str_repeat('a', 32), $acesso));
    rejeitar(fn () => $service->validarAcessoPrevia($id, '1.invalido'));
    rejeitar(fn () => $service->validarAcessoPrevia($id, $acesso.'alterado'));
    $html = $service->ler($id, 'index.html');
    verificar(str_contains($html, 'lang="pt-br"') && str_contains($html, "asset('style.css')") && str_contains($html, 'for atividade in atividades'), 'HTML inicial incompleto.');
    $service->alterarEstrutura($id, 'criar-pasta', 'assets');
    $service->alterarEstrutura($id, 'criar-pasta', 'assets/css');
    $service->alterarEstrutura($id, 'criar-arquivo', 'assets/css/teste.css');
    $service->salvar($id, ['assets/css/teste.css' => "  body { color: red; }\n\n", 'script.js' => '']);
    verificar($service->ler($id, 'assets/css/teste.css') === "  body { color: red; }\n\n", 'Espaços e quebras precisam ser preservados.');
    verificar($service->ler($id, 'script.js') === '', 'Arquivo vazio deve ser salvo.');
    $service->alterarEstrutura($id, 'mover', 'assets/css/teste.css', 'teste.css');
    verificar($service->ler($id, 'teste.css') !== '', 'Mover para a raiz falhou.');
    rejeitar(fn () => $service->alterarEstrutura($id, 'remover-pasta', 'assets'));
    $service->alterarEstrutura($id, 'remover-pasta', 'assets/css');
    $service->alterarEstrutura($id, 'remover-pasta', 'assets');
    $service->alterarEstrutura($id, 'remover-arquivo', 'teste.css');
    foreach (['../fora.css', '/fora.css', 'pasta/../../fora.css', 'nome com espaço.css', 'á.css', 'a.php', 'a.exe', 'a.sh', 'a.bat', '.env', 'a..css'] as $nome) rejeitar(fn () => $service->alterarEstrutura($id, 'criar-arquivo', $nome));
    foreach (['index.html', 'template.json'] as $nome) {
        rejeitar(fn () => $service->alterarEstrutura($id, 'remover-arquivo', $nome));
        rejeitar(fn () => $service->alterarEstrutura($id, 'mover', $nome, 'outro.html'));
    }
    rejeitar(fn () => $service->salvar($id, ['style.css' => 'NAO GRAVAR', 'template.json' => '{invalido}']));
    verificar($service->ler($id, 'style.css') !== 'NAO GRAVAR', 'Lote inválido não pode salvar parcialmente.');
    $manifesto = json_decode($service->ler($id, 'template.json'), true);
    $manifesto['variaveis'] = [['nome'=>'cor', 'padrao'=>'#ffffff', 'tipo'=>'color', 'rotulo'=>'Cor']];
    $service->salvar($id, ['template.json'=>json_encode($manifesto)]);
    rejeitar(fn () => $service->salvar($id, ['template.json'=>'{"nome":"Teste","versao":"1.0.0","variaveis":{}}']));
    $manifesto['variaveis'][0]['nome'] = 'evento';
    rejeitar(fn () => $service->salvar($id, ['template.json'=>json_encode($manifesto)]));
    $uploadPath = $raiz.'/upload.tmp'; File::put($uploadPath, '%PDF-1.4 teste');
    $service->upload($id, new UploadedFile($uploadPath, 'Documento original.pdf', 'application/pdf', null, true), 'documento-original.pdf');
    rejeitar(fn () => $service->upload($id, new UploadedFile($uploadPath, 'Documento.pdf', 'application/pdf', null, true), 'documento-original.pdf'));
    rejeitar(fn () => $service->upload($id, new UploadedFile($uploadPath, 'executavel.exe', 'application/octet-stream', null, true), 'arquivo.pdf'));
    File::put($uploadPath, 'MZexecutavel');
    rejeitar(fn () => $service->upload($id, new UploadedFile($uploadPath, 'foto.png', 'image/png', null, true), 'foto.png'));
    $novo = $service->novaVersao($id, ['index.html'=>'<h1>{{ evento.nome }}</h1>']);
    verificar($novo['id'] !== $id && $novo['manifesto']['versao'] === '1.0.1', 'Nova versão deve ser uma cópia incrementada.');
    verificar($service->ler($id, 'index.html') === $html, 'Nova versão alterou a origem.');
    verificar(count($service->listar('modelo')) === 2 && $service->listar('inexistente') === [], 'Consulta dos builds falhou.');
    $zip = $raiz.'/export.zip'; File::put($zip, $service->exportar($novo['id']));
    $conteudo = app(ZipLeitorService::class)->ler($zip);
    verificar(isset($conteudo['index.html'], $conteudo['template.json'], $conteudo['documento-original.pdf']), 'ZIP deve conter os arquivos na raiz.');
    verificar(json_decode($conteudo['template.json'], true)['versao'] === '1.0.1', 'ZIP está com versão incorreta.');
    symlink($uploadPath, $service->pasta($id).'/escape.css');
    rejeitar(fn () => $service->ler($id, 'escape.css'));
    rejeitar(fn () => $service->exportar($id));
    unlink($service->pasta($id).'/escape.css');

    $renderer = new class(app(TemplatePaginaService::class)) extends PaginaEventoRenderer {
        public function contexto(App\Models\Evento $evento): array { return ['evento'=>['nome'=>'Evento <teste>'], 'atividades'=>[['nome'=>'Atividade A', 'data_inicio'=>'17/09/2026', 'local'=>'Auditório', 'pode_inscrever'=>true, 'url_inscricao'=>'/inscricao']]]; }
    };
    $renderizado = $renderer->renderizarModelo($html, new App\Models\Evento, fn ($arquivo) => '/assets/'.$arquivo);
    verificar(str_contains($renderizado, '<h1>Evento &lt;teste&gt;</h1>') && str_contains($renderizado, '<h2>Atividade A</h2>') && str_contains($renderizado, '/assets/style.css'), 'Prévia deve renderizar evento, atividades e assets.');
    $request = Request::create('/eventos/1/pagina/criador');
    $request->setLaravelSession(new Illuminate\Session\Store('teste', new Illuminate\Session\ArraySessionHandler(120)));
    $middleware = new App\Http\Middleware\RequireGiPermission;
    rejeitar(fn () => $middleware->handle($request, fn () => response('OK'), 'eventos.pagina.editar', 'templates.codigo_fonte.editar'));
    $request->session()->put('gi_context.permissoes', ['templates.codigo_fonte.editar']);
    verificar($middleware->handle($request, fn () => response('OK'), 'eventos.pagina.editar', 'templates.codigo_fonte.editar')->getStatusCode() === 200, 'Editor autorizado deve poder acessar.');
    echo "OK: criação, leitura, lote, arquivos vazios, pastas, movimento, upload, validações, versões, ZIP, renderização e permissões.\n";
} finally { File::deleteDirectory($raiz); }
