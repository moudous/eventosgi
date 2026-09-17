<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Área de trabalho de templates, sem publicação no catálogo ou no evento. */
class TemplateBuildService
{
    public const EDITAVEIS = ['html', 'css', 'js', 'json', 'svg', 'txt', 'md'];
    public const EXTENSOES = ['html', 'css', 'js', 'json', 'svg', 'txt', 'md', 'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'woff', 'woff2', 'ttf', 'otf'];
    private const OBRIGATORIOS = ['index.html', 'template.json'];

    public function __construct(private readonly ZipEscritorService $zip, private readonly TemplatePaginaService $templates) {}

    public function raiz(): string
    {
        return base_path('templates-build');
    }

    public function temTemplates(): bool
    {
        return is_dir($this->raiz()) && File::directories($this->raiz()) !== [];
    }

    private function falhar(string $mensagem): never
    {
        throw ValidationException::withMessages(['template' => $mensagem]);
    }

    public function pasta(string $id): string
    {
        abort_unless(preg_match('/^[a-f0-9]{32}$/D', $id), 404);
        $pasta = $this->raiz().'/'.$id;
        abort_unless(is_dir($pasta) && !is_link($pasta), 404);
        return $pasta;
    }

    private function caminho(string $id, string $relativo, bool $arquivo = true): string
    {
        if (strlen($relativo) > 240 || !preg_match('/^[a-z0-9][a-z0-9_.\/-]*$/D', $relativo) || str_contains($relativo, '..')) {
            $this->falhar('Use nomes sem espaços ou acentos: letras minúsculas, números, hífen e sublinhado.');
        }
        $partes = explode('/', $relativo);
        foreach ($partes as $indice => $parte) {
            $padrao = $arquivo && $indice === count($partes) - 1
                ? '/^[a-z0-9][a-z0-9_-]*\.[a-z0-9]+$/D' : '/^[a-z0-9][a-z0-9_-]*$/D';
            if (!preg_match($padrao, $parte)) $this->falhar('Nome de arquivo ou pasta inválido.');
        }
        if ($arquivo && !in_array(pathinfo($relativo, PATHINFO_EXTENSION), self::EXTENSOES, true)) {
            $this->falhar('Tipo de arquivo não permitido. Executáveis e scripts de sistema não são aceitos.');
        }
        $caminho = $this->pasta($id);
        foreach ($partes as $parte) {
            $caminho .= '/'.$parte;
            if (is_link($caminho)) $this->falhar('Links simbólicos não são permitidos.');
        }
        return $caminho;
    }

    public function listar(string $busca = ''): array
    {
        if (!is_dir($this->raiz())) return [];
        $itens = [];
        foreach (File::directories($this->raiz()) as $pasta) {
            if (!preg_match('/^[a-f0-9]{32}$/D', basename($pasta)) || is_link($pasta)) continue;
            $manifesto = json_decode(File::get($pasta.'/template.json'), true) ?: [];
            $texto = ($manifesto['nome'] ?? 'Template').' — v'.($manifesto['versao'] ?? '1.0.0');
            if ($busca !== '' && !Str::contains(mb_strtolower($texto), mb_strtolower($busca))) continue;
            $itens[] = ['id' => basename($pasta), 'text' => $texto];
        }
        usort($itens, fn ($a, $b) => strnatcasecmp($a['text'], $b['text']));
        return $itens;
    }

    public function criar(string $nome): array
    {
        $id = bin2hex(random_bytes(16));
        File::ensureDirectoryExists($this->raiz().'/'.$id);
        $manifesto = ['nome' => $nome, 'versao' => '1.0.0', 'descricao' => '', 'variaveis' => []];
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ evento.nome }}</title>
    <link rel="stylesheet" href="{{ asset('style.css') }}">
    <script src="{{ asset('script.js') }}" defer></script>
</head>
<body>
    <main>
        <h1>{{ evento.nome }}</h1>
        <section aria-label="Atividades do evento">
            {% for atividade in atividades %}
            <article>
                <h2>{{ atividade.nome }}</h2>
                <p>{{ atividade.data_inicio }} · {{ atividade.local }}</p>
                {% if atividade.pode_inscrever %}
                <a href="{{ atividade.url_inscricao }}">Inscrever-se</a>
                {% endif %}
            </article>
            {% endfor %}
        </section>
    </main>
</body>
</html>
HTML;
        foreach (['template.json' => $this->json($manifesto), 'index.html' => $html."\n", 'style.css' => "body { font-family: system-ui, sans-serif; margin: 0; color: #243447; background: #f4f7fb; }\nmain { max-width: 1100px; margin: auto; padding: 32px 20px; }\narticle { background: white; border-radius: 12px; padding: 24px; margin: 16px 0; }\na { color: #0d6efd; }\n", 'script.js' => "'use strict';\n\n// JavaScript do template.\n"] as $arquivo => $conteudo) {
            File::put($this->pasta($id).'/'.$arquivo, $conteudo);
        }
        return $this->estado($id);
    }

    public function estado(string $id): array
    {
        $arquivos = []; $pastas = [];
        $raiz = $this->pasta($id);
        $iterador = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterador as $item) {
            if ($item->isLink()) $this->falhar('Links simbólicos não são permitidos.');
            $nome = substr($item->getPathname(), strlen($raiz) + 1);
            if ($item->isDir()) $pastas[] = $nome;
            else $arquivos[] = ['nome' => $nome, 'editavel' => in_array($item->getExtension(), self::EDITAVEIS, true), 'tamanho' => $item->getSize()];
        }
        sort($pastas);
        usort($arquivos, fn ($a, $b) => strcmp($a['nome'], $b['nome']));
        return ['id' => $id, 'manifesto' => json_decode(File::get($raiz.'/template.json'), true), 'arquivos' => $arquivos, 'pastas' => $pastas];
    }

    public function ler(string $id, string $arquivo): string
    {
        $caminho = $this->caminho($id, $arquivo);
        if (!in_array(pathinfo($arquivo, PATHINFO_EXTENSION), self::EDITAVEIS, true)) $this->falhar('Este arquivo não é editável.');
        abort_unless(is_file($caminho), 404);
        if (filesize($caminho) > TemplatePaginaService::TAMANHO_MAXIMO_EDITOR) $this->falhar('O editor aceita arquivos de até 2 MB.');
        return File::get($caminho);
    }

    private function validarConteudo(string $arquivo, string $conteudo): void
    {
        if (strlen($conteudo) > TemplatePaginaService::TAMANHO_MAXIMO_EDITOR) $this->falhar('O editor aceita arquivos de até 2 MB.');
        if ($arquivo !== 'template.json') return;
        $manifesto = json_decode($conteudo, true);
        if (!is_array($manifesto) || !is_string($manifesto['nome'] ?? null) || trim($manifesto['nome']) === '' || mb_strlen($manifesto['nome']) > 150
            || !is_string($manifesto['versao'] ?? null) || !preg_match('/^\d{1,5}\.\d{1,5}\.\d{1,5}$/D', $manifesto['versao'])) {
            $this->falhar('template.json deve conter nome e versão no formato 1.0.0.');
        }
        if (isset($manifesto['descricao']) && !is_string($manifesto['descricao'])) $this->falhar('A descrição deve ser um texto.');
        $objeto = json_decode($conteudo);
        if (isset($objeto->variaveis) && !is_array($objeto->variaveis)) $this->falhar('variaveis deve ser uma lista.');
        $nomes = [];
        foreach ($manifesto['variaveis'] ?? [] as $variavel) {
            $nome = is_array($variavel) ? ($variavel['nome'] ?? '') : '';
            if (!is_string($nome) || !preg_match('/^[a-z_][a-z0-9_]*$/D', $nome) || in_array($nome, PaginaEventoRenderer::RESERVADOS, true) || in_array($nome, $nomes, true)) $this->falhar('Variável inválida, reservada ou repetida no manifesto.');
            foreach (['rotulo', 'padrao', 'tipo'] as $campo) if (isset($variavel[$campo]) && !is_string($variavel[$campo])) $this->falhar('Os campos das variáveis devem ser textos.');
            $nomes[] = $nome;
        }
    }

    /** Valida o lote inteiro antes de gravar, preservando os originais em caso de falha. */
    public function salvar(string $id, array $arquivos): array
    {
        $originais = []; $destinos = [];
        foreach ($arquivos as $arquivo => $conteudo) {
            $originais[$arquivo] = $this->ler($id, $arquivo);
            $this->validarConteudo($arquivo, $conteudo);
            $destinos[$arquivo] = $this->caminho($id, $arquivo);
        }
        try {
            foreach ($arquivos as $arquivo => $conteudo) File::replace($destinos[$arquivo], $conteudo);
        } catch (\Throwable $erro) {
            foreach ($originais as $arquivo => $conteudo) File::replace($destinos[$arquivo], $conteudo);
            throw $erro;
        }
        return $this->estado($id);
    }

    private function destinoLivre(string $id, string $nome, bool $arquivo): string
    {
        $destino = $this->caminho($id, $nome, $arquivo);
        if (file_exists($destino)) $this->falhar('Já existe um arquivo ou pasta com esse nome.');
        if (!is_dir(dirname($destino))) $this->falhar('A pasta de destino não existe.');
        return $destino;
    }

    public function alterarEstrutura(string $id, string $acao, string $nome, ?string $destino = null): array
    {
        if ($acao === 'criar-pasta') {
            File::makeDirectory($this->destinoLivre($id, $nome, false));
        } elseif ($acao === 'criar-arquivo') {
            if (!in_array(pathinfo($nome, PATHINFO_EXTENSION), self::EDITAVEIS, true)) $this->falhar('Use o upload para enviar arquivos binários.');
            File::put($this->destinoLivre($id, $nome, true), '');
        } elseif ($acao === 'remover-pasta') {
            $pasta = $this->caminho($id, $nome, false);
            if (!is_dir($pasta) || count(scandir($pasta)) !== 2) $this->falhar('Somente pastas vazias podem ser removidas.');
            rmdir($pasta);
        } else {
            if (in_array($nome, self::OBRIGATORIOS, true)) $this->falhar('index.html e template.json devem permanecer na raiz.');
            $origem = $this->caminho($id, $nome);
            abort_unless(is_file($origem), 404);
            if ($acao === 'remover-arquivo') File::delete($origem);
            elseif ($acao === 'mover') {
                if (pathinfo($nome, PATHINFO_EXTENSION) !== pathinfo($destino ?? '', PATHINFO_EXTENSION)) $this->falhar('A extensão deve ser mantida.');
                File::move($origem, $this->destinoLivre($id, $destino ?? '', true));
            } else $this->falhar('Operação inválida.');
        }
        return $this->estado($id);
    }

    public function upload(string $id, UploadedFile $arquivo, string $nome): array
    {
        $destino = $this->destinoLivre($id, $nome, true);
        $extensao = pathinfo($nome, PATHINFO_EXTENSION);
        if (strtolower($arquivo->getClientOriginalExtension()) !== $extensao) $this->falhar('Mantenha a extensão original do arquivo.');
        $conteudo = File::get($arquivo->getRealPath());
        if (strlen($conteudo) > 20 * 1024 * 1024) $this->falhar('Cada upload pode ter até 20 MB.');
        if (preg_match('/^(MZ|\x7fELF|#!)/', $conteudo) || preg_match('/<\?(?:php|=)/i', $conteudo)) $this->falhar('Conteúdo executável não permitido.');
        if (in_array($extensao, ['png','jpg','jpeg','gif','webp','avif','ico'], true) && !str_starts_with((string) $arquivo->getMimeType(), 'image/')) $this->falhar('O conteúdo não corresponde a uma imagem.');
        if ($extensao === 'pdf' && !str_starts_with($conteudo, '%PDF-')) $this->falhar('Arquivo PDF inválido.');
        if (in_array($extensao, self::EDITAVEIS, true)) $this->validarConteudo($nome, $conteudo);
        File::put($destino, $conteudo);
        return $this->estado($id);
    }

    public function novaVersao(string $id, array $arquivos): array
    {
        // A cópia recebe as edições pendentes; a versão anterior permanece intacta.
        foreach ($arquivos as $nome => $conteudo) {
            $this->ler($id, $nome);
            $this->validarConteudo($nome, $conteudo);
        }
        $this->estado($id); // rejeita links antes da cópia
        $novo = bin2hex(random_bytes(16));
        $destino = $this->raiz().'/'.$novo;
        try {
            if (!File::copyDirectory($this->pasta($id), $destino)) throw new \RuntimeException('Não foi possível copiar o template.');
            foreach ($arquivos as $nome => $conteudo) File::replace($this->caminho($novo, $nome), $conteudo);
            $manifesto = json_decode(File::get($destino.'/template.json'), true);
            $manifesto['versao'] = $this->templates->proximaVersao($manifesto['versao']);
            File::replace($destino.'/template.json', $this->json($manifesto));
            return $this->estado($novo);
        } catch (\Throwable $erro) {
            File::deleteDirectory($destino);
            throw $erro;
        }
    }

    public function exportar(string $id): string
    {
        $this->estado($id);
        return $this->zip->deDiretorio($this->pasta($id));
    }

    public function asset(string $id, string $arquivo): array
    {
        $caminho = $this->caminho($id, $arquivo);
        abort_unless(is_file($caminho), 404);
        $mime = ['css'=>'text/css', 'js'=>'text/javascript', 'html'=>'text/html', 'svg'=>'image/svg+xml', 'json'=>'application/json', 'pdf'=>'application/pdf', 'woff'=>'font/woff', 'woff2'=>'font/woff2', 'ttf'=>'font/ttf', 'otf'=>'font/otf'][pathinfo($arquivo, PATHINFO_EXTENSION)] ?? File::mimeType($caminho);
        return ['conteudo' => File::get($caminho), 'mime' => $mime ?: 'application/octet-stream'];
    }

    /** Permite assets no iframe isolado, sem depender dos cookies de sessão. */
    public function acessoPrevia(string $id): string
    {
        $expira = (string) now()->addHour()->timestamp;
        return $expira.'.'.hash_hmac('sha256', $id.'|'.$expira, (string) config('app.key'));
    }

    public function validarAcessoPrevia(string $id, string $acesso): void
    {
        $partes = explode('.', $acesso, 2);
        abort_unless(count($partes) === 2 && ctype_digit($partes[0]) && (int) $partes[0] >= now()->timestamp
            && hash_equals(hash_hmac('sha256', $id.'|'.$partes[0], (string) config('app.key')), $partes[1]), 403);
    }

    private function json(array $dados): string
    {
        return json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
    }
}
