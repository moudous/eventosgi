<?php

namespace App\Services;

use App\Models\TemplatePagina;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use RuntimeException;

/**
 * Importa, guarda e remove os templates das paginas de evento.
 *
 * Um template e um ZIP com esta forma:
 *
 *   index.html          obrigatorio -- o HTML na linguagem de PaginaEventoRenderer
 *   template.json       opcional -- nome, descricao, versao e variaveis declaradas
 *   assets/…            opcional -- css, js, imagens e fontes
 *
 * Cada template ocupa uma pasta em templates/, na raiz do projeto -- do mesmo jeito que
 * wordpress/ guarda o plugin. Ficam fora de public/, entao nada e servido direto pelo
 * servidor web: o HTML passa pelo renderizador e os assets saem por rota propria, que so
 * entrega extensoes conhecidas.
 *
 * Ficar no projeto, e nao em storage/, tem uma consequencia util: um template versionado
 * junto do codigo -- como o institucional-1 -- ja chega instalado, e a sincronizacao
 * abaixo o registra sozinha na primeira vez que a tela e aberta.
 */
class TemplatePaginaService
{
    /** Pasta dos templates, na raiz do projeto. */
    public const PASTA = 'templates';

    /** Arquivo que o pacote precisa ter. */
    public const ENTRADA = 'index.html';

    /** Manifesto opcional. */
    public const MANIFESTO = 'template.json';

    /**
     * Extensoes aceitas dentro do pacote.
     *
     * Lista fechada de proposito: um .php ou .phtml gravado junto dos assets viraria
     * codigo executavel se um dia a pasta fosse exposta por engano.
     */
    private const EXTENSOES = ['html', 'css', 'js', 'json', 'map', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'txt', 'md'];

    public function __construct(
        private readonly ZipLeitorService $zip,
        private readonly ZipEscritorService $escritor,
    ) {}

    /** Caminho absoluto da pasta de um template. */
    public function caminho(TemplatePagina|string $template): string
    {
        return base_path(self::PASTA.'/'.(is_string($template) ? $template : $template->pasta));
    }

    /**
     * Grava um template novo a partir do ZIP enviado.
     */
    public function importar(UploadedFile $arquivo, ?int $usuarioId = null): TemplatePagina
    {
        $conteudo = $this->zip->ler($arquivo->getRealPath());
        $conteudo = $this->semPastaRaiz($conteudo);

        if (! isset($conteudo[self::ENTRADA])) {
            throw new RuntimeException('O pacote precisa conter um arquivo '.self::ENTRADA.' na raiz.');
        }

        foreach (array_keys($conteudo) as $caminho) {
            $extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
            if (! in_array($extensao, self::EXTENSOES, true)) {
                throw new RuntimeException("O arquivo {$caminho} tem uma extensão não aceita em templates.");
            }
        }

        $manifesto = $this->manifesto($conteudo);
        $nome = $manifesto['nome'] ?? pathinfo($arquivo->getClientOriginalName(), PATHINFO_FILENAME);
        $pasta = $this->pastaDisponivel($nome);

        // Descompacta criando a pasta do template dentro de templates/.
        foreach ($conteudo as $caminho => $bytes) {
            $destino = $this->caminho($pasta).'/'.$caminho;
            File::ensureDirectoryExists(dirname($destino));
            File::put($destino, $bytes);
        }

        return TemplatePagina::create([
            'nome' => mb_substr((string) $nome, 0, 150),
            'pasta' => $pasta,
            'descricao' => isset($manifesto['descricao']) ? mb_substr((string) $manifesto['descricao'], 0, 500) : null,
            'versao' => isset($manifesto['versao']) ? mb_substr((string) $manifesto['versao'], 0, 20) : null,
            'variaveis' => $this->variaveis($manifesto),
            'ativo' => true,
            'importado_por' => $usuarioId,
        ]);
    }

    /** Apaga a pasta do template. Quem chama decide se ele pode sair. */
    public function remover(TemplatePagina $template): void
    {
        File::deleteDirectory($this->caminho($template));
    }

    /** Devolve o template empacotado, para baixar e reaproveitar em outra instalação. */
    public function zip(TemplatePagina $template): string
    {
        return $this->escritor->deDiretorio($this->caminho($template), $template->pasta);
    }

    public function html(TemplatePagina $template): string
    {
        $caminho = $this->caminho($template).'/'.self::ENTRADA;

        return is_readable($caminho) ? (string) File::get($caminho) : '';
    }

    /**
     * Conteudo de um asset, ou null quando o caminho nao existe ou nao e permitido.
     *
     * @return array{conteudo: string, mime: string}|null
     */
    public function asset(TemplatePagina $template, string $caminho): ?array
    {
        $caminho = ltrim(str_replace('\\', '/', $caminho), '/');
        $extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));

        // O index.html so sai renderizado; servi-lo cru entregaria o modelo sem os dados.
        if ($caminho === '' || $caminho === self::ENTRADA || str_contains($caminho, '..')
            || ! in_array($extensao, self::EXTENSOES, true)) {
            return null;
        }

        $completo = $this->caminho($template).'/'.$caminho;

        // realpath fecha a porta a link simbolico apontando para fora da pasta.
        $real = realpath($completo);
        $raiz = realpath($this->caminho($template));

        if ($real === false || $raiz === false || ! str_starts_with($real, $raiz.DIRECTORY_SEPARATOR)) return null;

        return ['conteudo' => (string) File::get($real), 'mime' => $this->mime($extensao)];
    }

    public function urlDoAsset(TemplatePagina $template, string $arquivo): string
    {
        return route('templates.asset', ['template' => $template->id, 'caminho' => ltrim($arquivo, '/')]);
    }

    /**
     * Arquivos do template, para a tela mostrar o que veio no pacote.
     *
     * @return list<string>
     */
    public function arquivos(TemplatePagina $template): array
    {
        $raiz = $this->caminho($template);

        if (! is_dir($raiz)) return [];

        $arquivos = [];

        foreach (Finder::create()->files()->in($raiz)->sortByName() as $arquivo) {
            /** @var SplFileInfo $arquivo */
            $arquivos[] = str_replace('\\', '/', $arquivo->getRelativePathname());
        }

        return $arquivos;
    }

    /**
     * Registra os templates que estao em templates/ mas ainda nao no banco.
     *
     * Serve aos templates que vem versionados com o codigo: sem isto, alguem precisaria
     * exportar e reimportar um template que ja esta instalado para poder usa-lo.
     *
     * @return int quantos foram registrados agora
     */
    public function sincronizar(): int
    {
        $raiz = base_path(self::PASTA);

        if (! is_dir($raiz)) return 0;

        $registradas = TemplatePagina::query()->pluck('pasta')->all();
        $novos = 0;

        foreach (File::directories($raiz) as $caminho) {
            $pasta = basename($caminho);

            if (in_array($pasta, $registradas, true)) continue;
            if (! is_readable($caminho.'/'.self::ENTRADA)) continue;

            $manifesto = [];
            if (is_readable($caminho.'/'.self::MANIFESTO)) {
                $manifesto = json_decode((string) File::get($caminho.'/'.self::MANIFESTO), true) ?: [];
            }

            TemplatePagina::create([
                'nome' => mb_substr((string) ($manifesto['nome'] ?? $pasta), 0, 150),
                'pasta' => $pasta,
                'descricao' => isset($manifesto['descricao']) ? mb_substr((string) $manifesto['descricao'], 0, 500) : null,
                'versao' => isset($manifesto['versao']) ? mb_substr((string) $manifesto['versao'], 0, 20) : null,
                'variaveis' => $this->variaveis($manifesto),
                'ativo' => true,
            ]);
            $novos++;
        }

        return $novos;
    }

    /**
     * Compactadores costumam embrulhar tudo em uma pasta com o nome do projeto. Quando o
     * ZIP inteiro esta sob uma unica pasta, ela e removida para o index.html cair na raiz.
     *
     * @param  array<string, string>  $conteudo
     * @return array<string, string>
     */
    private function semPastaRaiz(array $conteudo): array
    {
        $raizes = [];

        foreach (array_keys($conteudo) as $caminho) {
            $raizes[explode('/', $caminho)[0]] = true;
            if (! str_contains($caminho, '/')) return $conteudo;
        }

        if (count($raizes) !== 1) return $conteudo;

        $prefixo = array_key_first($raizes).'/';
        $limpo = [];

        foreach ($conteudo as $caminho => $bytes) $limpo[substr($caminho, strlen($prefixo))] = $bytes;

        return $limpo;
    }

    /**
     * @param  array<string, string>  $conteudo
     * @return array<string, mixed>
     */
    private function manifesto(array $conteudo): array
    {
        if (! isset($conteudo[self::MANIFESTO])) return [];

        $dados = json_decode($conteudo[self::MANIFESTO], true);

        if (! is_array($dados)) {
            throw new RuntimeException('O arquivo '.self::MANIFESTO.' não contém um JSON válido.');
        }

        return $dados;
    }

    /**
     * Variaveis declaradas pelo template, normalizadas.
     *
     * @param  array<string, mixed>  $manifesto
     * @return list<array{nome: string, rotulo: string, padrao: string}>
     */
    private function variaveis(array $manifesto): array
    {
        $variaveis = [];

        foreach ((array) ($manifesto['variaveis'] ?? []) as $declarada) {
            $nome = is_array($declarada) ? (string) ($declarada['nome'] ?? '') : (string) $declarada;
            $nome = trim($nome);

            // Nome precisa ser um identificador simples: e ele que o template usa em
            // {{ nome }}, e a linguagem so aceita letras, numeros e sublinhado.
            if (! preg_match('/^[a-z_][a-z0-9_]*$/i', $nome)) continue;
            if (in_array($nome, PaginaEventoRenderer::RESERVADOS, true)) continue;

            $variaveis[$nome] = [
                'nome' => $nome,
                'rotulo' => trim((string) (is_array($declarada) ? ($declarada['rotulo'] ?? $nome) : $nome)),
                'padrao' => (string) (is_array($declarada) ? ($declarada['padrao'] ?? '') : ''),
            ];
        }

        return array_values($variaveis);
    }

    private function pastaDisponivel(string $nome): string
    {
        $base = Str::slug($nome) ?: 'template';
        $pasta = $base;
        $sufixo = 2;

        while (TemplatePagina::query()->where('pasta', $pasta)->exists() || is_dir($this->caminho($pasta))) {
            $pasta = $base.'-'.$sufixo++;
        }

        return mb_substr($pasta, 0, 100);
    }

    private function mime(string $extensao): string
    {
        return [
            'css' => 'text/css', 'js' => 'text/javascript', 'json' => 'application/json',
            'map' => 'application/json', 'svg' => 'image/svg+xml', 'png' => 'image/png',
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'avif' => 'image/avif', 'ico' => 'image/x-icon',
            'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf',
            'otf' => 'font/otf', 'eot' => 'application/vnd.ms-fontobject',
            'txt' => 'text/plain', 'md' => 'text/markdown', 'html' => 'text/html',
        ][$extensao] ?? 'application/octet-stream';
    }
}
