<?php

namespace App\Services;

use RuntimeException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Escreve arquivos ZIP com zlib.
 *
 * A extensao zip do PHP nao esta habilitada em todas as instalacoes onde esta aplicacao
 * roda -- ZipLeitorService existe pelo mesmo motivo, do lado da leitura. Usado tanto
 * para baixar o plugin do WordPress quanto para exportar um template de pagina.
 */
class ZipEscritorService
{
    /**
     * Conteudo binario de um ZIP com tudo o que esta no diretorio.
     *
     * @param  string  $prefixo  pasta que embrulha os arquivos dentro do ZIP; vazio para a raiz
     */
    public function deDiretorio(string $raiz, string $prefixo = ''): string
    {
        if (! is_dir($raiz)) {
            throw new RuntimeException('A pasta a compactar não foi encontrada.');
        }

        $arquivos = [];
        $prefixo = $prefixo === '' ? '' : rtrim($prefixo, '/').'/';

        foreach (Finder::create()->files()->in($raiz)->sortByName() as $arquivo) {
            /** @var SplFileInfo $arquivo */
            $arquivos[$prefixo.str_replace('\\', '/', $arquivo->getRelativePathname())] = $arquivo->getPathname();
        }

        if ($arquivos === []) {
            throw new RuntimeException('A pasta a compactar está vazia.');
        }

        return $this->montar($arquivos);
    }

    /**
     * @param  array<string, string>  $arquivos  caminho dentro do ZIP => caminho no disco
     */
    private function montar(array $arquivos): string
    {
        $registros = [];
        $central = '';
        $corpo = '';
        $total = 0;

        foreach ($arquivos as $nome => $origem) {
            $conteudo = file_get_contents($origem);

            if ($conteudo === false) {
                throw new RuntimeException("Não foi possível ler {$nome}.");
            }

            $comprimido = gzdeflate($conteudo, 9);
            $momento = $this->momentoDos((int) filemtime($origem));

            // Cabeçalho local: versão 2.0, sinalizador UTF-8, método deflate.
            $cabecalho = pack('vvvv', 20, 0x0800, 8, $momento['hora'])
                .pack('v', $momento['data'])
                .pack('VVV', crc32($conteudo), strlen($comprimido), strlen($conteudo))
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
