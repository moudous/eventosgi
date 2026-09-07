<?php

namespace App\Services;

use RuntimeException;

/**
 * Leitor de arquivos ZIP escrito sobre zlib.
 *
 * A extensao zip do PHP nao esta habilitada em todas as instalacoes onde esta aplicacao
 * roda -- PluginWordpressService ja escreve ZIP com zlib pelo mesmo motivo, e aqui vale
 * para a leitura. So o necessario para importar um template: os dois metodos de
 * compressao que qualquer compactador produz (armazenado e deflate).
 *
 * A leitura e defensiva de proposito: o arquivo vem de fora, e um ZIP mal-intencionado
 * tenta escapar da pasta de destino, estourar o disco ao descompactar ou esconder mil
 * entradas em poucos kilobytes.
 */
class ZipLeitorService
{
    /** Entradas aceitas em um pacote. */
    private const MAX_ARQUIVOS = 200;

    /** Tamanho maximo de um arquivo depois de descompactado. */
    private const MAX_BYTES_ARQUIVO = 5 * 1024 * 1024;

    /** Soma maxima de todos os arquivos descompactados, contra zip bomb. */
    private const MAX_BYTES_TOTAL = 20 * 1024 * 1024;

    private const FIM_CENTRAL = "\x50\x4b\x05\x06";

    private const CENTRAL = "\x50\x4b\x01\x02";

    private const LOCAL = "\x50\x4b\x03\x04";

    /**
     * Le o ZIP e devolve os arquivos como caminho => conteudo.
     *
     * Pastas nao aparecem no resultado: a estrutura fica implicita nos caminhos.
     *
     * @return array<string, string>
     */
    public function ler(string $caminhoDoZip): array
    {
        $bruto = @file_get_contents($caminhoDoZip);

        if ($bruto === false || strlen($bruto) < 22) {
            throw new RuntimeException('O arquivo enviado não é um ZIP válido.');
        }

        $arquivos = [];
        $total = 0;

        foreach ($this->entradas($bruto) as $entrada) {
            if (count($arquivos) >= self::MAX_ARQUIVOS) {
                throw new RuntimeException('O pacote tem mais de '.self::MAX_ARQUIVOS.' arquivos.');
            }

            $nome = $this->normalizarNome($entrada['nome']);

            // Pasta: entra no ZIP como entrada de tamanho zero terminada em barra.
            if ($nome === null || str_ends_with($entrada['nome'], '/')) continue;

            if ($entrada['tamanho'] > self::MAX_BYTES_ARQUIVO) {
                throw new RuntimeException("O arquivo {$nome} passa de ".(self::MAX_BYTES_ARQUIVO / 1048576).' MB.');
            }

            $conteudo = $this->extrair($bruto, $entrada);
            $total += strlen($conteudo);

            if ($total > self::MAX_BYTES_TOTAL) {
                throw new RuntimeException('O conteúdo descompactado passa de '.(self::MAX_BYTES_TOTAL / 1048576).' MB.');
            }

            $arquivos[$nome] = $conteudo;
        }

        if ($arquivos === []) {
            throw new RuntimeException('O ZIP não contém nenhum arquivo.');
        }

        return $arquivos;
    }

    /**
     * Entradas do diretorio central, que e a lista autoritativa do que ha no ZIP.
     *
     * @return list<array{nome: string, metodo: int, comprimido: int, tamanho: int, offset: int}>
     */
    private function entradas(string $bruto): array
    {
        $fim = strrpos($bruto, self::FIM_CENTRAL);

        if ($fim === false) {
            throw new RuntimeException('O arquivo enviado não é um ZIP válido.');
        }

        $cabecalho = unpack('vdisco/vdiscoCentral/ventradasDisco/ventradas/Vtamanho/Voffset', substr($bruto, $fim + 4, 16));

        if ($cabecalho === false) {
            throw new RuntimeException('Não foi possível ler o índice do ZIP.');
        }

        $entradas = [];
        $posicao = (int) $cabecalho['offset'];

        for ($i = 0; $i < (int) $cabecalho['entradas']; $i++) {
            if (substr($bruto, $posicao, 4) !== self::CENTRAL) {
                throw new RuntimeException('O índice do ZIP está corrompido.');
            }

            // 42 bytes depois da assinatura, nas larguras exatas do formato ZIP:
            // v = 2 bytes, V = 4 bytes.
            $c = unpack(
                'vversaoFeita/vversaoNecessaria/vflags/vmetodo/vhora/vdata/Vcrc/Vcomprimido/Vtamanho'
                .'/vtamNome/vtamExtra/vtamComentario/vdiscoInicio/vatributosInternos/Vatributos/Voffset',
                substr($bruto, $posicao + 4, 42),
            );

            if ($c === false) {
                throw new RuntimeException('O índice do ZIP está corrompido.');
            }

            $tamNome = (int) $c['tamNome'];
            $tamExtra = (int) $c['tamExtra'];
            $tamComentario = (int) $c['tamComentario'];

            $entradas[] = [
                'nome' => substr($bruto, $posicao + 46, $tamNome),
                'metodo' => (int) $c['metodo'],
                'comprimido' => (int) $c['comprimido'],
                'tamanho' => (int) $c['tamanho'],
                'offset' => (int) $c['offset'],
            ];

            $posicao += 46 + $tamNome + $tamExtra + $tamComentario;
        }

        return $entradas;
    }

    /**
     * @param  array{nome: string, metodo: int, comprimido: int, tamanho: int, offset: int}  $entrada
     */
    private function extrair(string $bruto, array $entrada): string
    {
        if (substr($bruto, $entrada['offset'], 4) !== self::LOCAL) {
            throw new RuntimeException("Não foi possível ler {$entrada['nome']} dentro do ZIP.");
        }

        // O cabecalho local repete nome e extra com tamanhos proprios: e ele que diz onde
        // os dados comecam de fato, e o extra costuma diferir do que esta no indice.
        $tamanhos = unpack('vnome/vextra', substr($bruto, $entrada['offset'] + 26, 4));
        $dados = substr($bruto, $entrada['offset'] + 30 + (int) $tamanhos['nome'] + (int) $tamanhos['extra'], $entrada['comprimido']);

        $conteudo = match ($entrada['metodo']) {
            0 => $dados,
            8 => @gzinflate($dados),
            default => throw new RuntimeException("O arquivo {$entrada['nome']} usa um método de compressão não suportado."),
        };

        if ($conteudo === false) {
            throw new RuntimeException("Não foi possível descompactar {$entrada['nome']}.");
        }

        return $conteudo;
    }

    /**
     * Caminho seguro para gravar, ou null quando a entrada deve ser ignorada.
     *
     * Recusa tudo que tente sair da pasta de destino: caminho absoluto, "..", barra
     * invertida do Windows e os metadados que o Finder e alguns compactadores incluem.
     */
    private function normalizarNome(string $nome): ?string
    {
        $nome = str_replace('\\', '/', trim($nome));

        if ($nome === '' || str_starts_with($nome, '/') || preg_match('#(^|/)\.\.(/|$)#', $nome)) {
            throw new RuntimeException("O ZIP contém um caminho inválido: {$nome}");
        }

        foreach (explode('/', $nome) as $parte) {
            if ($parte === '__MACOSX' || str_starts_with($parte, '._') || $parte === '.DS_Store') return null;
        }

        return $nome;
    }
}
