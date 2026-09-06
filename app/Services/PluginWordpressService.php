<?php

namespace App\Services;

use Illuminate\Http\Response;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Empacota o plugin do WordPress para download.
 *
 * O ZIP e montado com zlib em vez da extensao zip porque ela nao esta habilitada
 * em todas as instalacoes de PHP onde esta aplicacao roda.
 */
class PluginWordpressService
{
    /** Pasta do plugin dentro do projeto, que tambem da nome ao arquivo baixado. */
    public const PASTA = 'eventosgi-formularios';

    public function caminho(): string
    {
        return base_path('wordpress/'.self::PASTA);
    }

    public function versao(): string
    {
        $principal = $this->caminho().'/'.self::PASTA.'.php';

        return is_readable($principal) && preg_match('/^\s*\*\s*Version:\s*(.+)$/mi', (string) file_get_contents($principal), $encontrado)
            ? trim($encontrado[1])
            : '';
    }

    public function nomeDoArquivo(): string
    {
        $versao = $this->versao();

        return self::PASTA.($versao !== '' ? '.'.$versao : '').'.zip';
    }

    public function download(): Response
    {
        $arquivo = $this->nomeDoArquivo();

        return response($this->zip(), 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $arquivo, \Illuminate\Support\Str::ascii($arquivo)),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Conteudo binario do ZIP, com os arquivos dentro de uma pasta com o nome do plugin.
     */
    public function zip(): string
    {
        $raiz = $this->caminho();
        if (! is_dir($raiz)) throw new RuntimeException('A pasta do plugin do WordPress não foi encontrada.');

        $arquivos = [];
        $registros = [];
        $central = '';
        $corpo = '';
        $total = 0;

        foreach (Finder::create()->files()->in($raiz)->sortByName() as $arquivo) {
            /** @var SplFileInfo $arquivo */
            $arquivos[self::PASTA.'/'.str_replace('\\', '/', $arquivo->getRelativePathname())] = $arquivo->getPathname();
        }

        if ($arquivos === []) throw new RuntimeException('A pasta do plugin do WordPress está vazia.');

        foreach ($arquivos as $nome => $origem) {
            $conteudo = file_get_contents($origem);
            if ($conteudo === false) throw new RuntimeException("Não foi possível ler {$nome}.");

            $comprimido = gzdeflate($conteudo, 9);
            $crc = crc32($conteudo);
            $momento = $this->momentoDos((int) filemtime($origem));

            // Cabeçalho local: assinatura, versão 2.0, sinalizador UTF-8, método deflate.
            $cabecalho = pack('vvvv', 20, 0x0800, 8, $momento['hora'])
                .pack('v', $momento['data'])
                .pack('VVV', $crc, strlen($comprimido), strlen($conteudo))
                .pack('vv', strlen($nome), 0);

            $registros[] = ['nome' => $nome, 'cabecalho' => $cabecalho, 'posicao' => strlen($corpo)];
            $corpo .= "PK\x03\x04".$cabecalho.$nome.$comprimido;
            $total++;
        }

        foreach ($registros as $registro) {
            $central .= "PK\x01\x02".pack('v', 20).$registro['cabecalho']
                .pack('vvv', 0, 0, 0)          // comentário, disco e atributos internos
                .pack('V', 0100644 << 16)      // permissões do arquivo em sistemas Unix
                .pack('V', $registro['posicao'])
                .$registro['nome'];
        }

        return $corpo.$central."PK\x05\x06".pack('vv', 0, 0)
            .pack('vv', $total, $total)
            .pack('VV', strlen($central), strlen($corpo))
            .pack('v', 0);
    }

    /**
     * Converte um timestamp para os campos de hora e data do formato ZIP (padrão MS-DOS).
     *
     * @return array{hora: int, data: int}
     */
    private function momentoDos(int $timestamp): array
    {
        $partes = getdate(max($timestamp, mktime(0, 0, 0, 1, 1, 1980)));

        return [
            'hora' => ($partes['hours'] << 11) | ($partes['minutes'] << 5) | intdiv($partes['seconds'], 2),
            'data' => (($partes['year'] - 1980) << 9) | ($partes['mon'] << 5) | $partes['mday'],
        ];
    }
}
