<?php

namespace App\Services;

use Illuminate\Http\Response;
use RuntimeException;
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
        return app(ZipEscritorService::class)->deDiretorio($this->caminho(), self::PASTA);
    }
}
