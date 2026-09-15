<?php

namespace App\Services;

use App\Models\ArquivoBiblioteca;
use Illuminate\Validation\ValidationException;

class BibliotecaImagemService
{
    public const FORMATOS = [
        'icone' => 'Ícone — até 64 px',
        'miniatura_pequena' => 'Miniatura pequena — até 160 px',
        'miniatura_normal' => 'Miniatura normal — até 320 px',
        'compressao_pequena' => 'Compressão pequena — até ~500 KB',
        'compressao_media' => 'Compressão média — até ~250 KB',
        'compressao_grande' => 'Compressão grande — até ~100 KB',
        'pequena' => 'Pequena — até 640 px',
        'media' => 'Média — até 1280 px',
        'grande' => 'Grande — até 1920 px',
        'webp' => 'Formato WebP',
        'svg' => 'Vetorizar para SVG — preto e branco',
    ];

    public function caminho(ArquivoBiblioteca $arquivo): string
    {
        abort_unless(preg_match('/^[a-f0-9-]{36}\.[a-z0-9]{1,15}$/', $arquivo->arquivo), 422, 'Nome de arquivo inválido.');
        $caminho = storage_path('app/public/biblioteca/'.$arquivo->arquivo);
        abort_if(is_link($caminho), 422, 'Arquivo inválido.');
        return $caminho;
    }

    /** @return array{conteudo:string,extensao:string,largura:int,altura:int} */
    public function gerar(ArquivoBiblioteca $arquivo, string $formato): array
    {
        if (!isset(self::FORMATOS[$formato]) || $arquivo->tipo !== 'imagem' || !in_array($arquivo->formato, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            throw ValidationException::withMessages(['formato' => 'Selecione uma imagem JPG, PNG, GIF ou WebP para gerar versões.']);
        }
        $caminho = $this->caminho($arquivo);
        abort_unless(is_file($caminho), 404, 'Imagem não encontrada no disco.');
        $dimensoes = @getimagesize($caminho);
        if (!$dimensoes || $dimensoes[0] * $dimensoes[1] > 16000000 || filesize($caminho) > 25 * 1024 * 1024) {
            throw ValidationException::withMessages(['formato' => 'Para converter, use uma imagem de até 16 milhões de pixels e 25 MB.']);
        }
        $imagem = @imagecreatefromstring(file_get_contents($caminho));
        if (!$imagem) throw ValidationException::withMessages(['formato' => 'Não foi possível ler esta imagem.']);
        try {
            $limite = match ($formato) {
                'icone' => 64, 'miniatura_pequena' => 160,
                'miniatura_normal' => 320, 'pequena' => 640, 'media' => 1280, 'grande' => 1920,
                'svg' => 512, default => max(imagesx($imagem), imagesy($imagem)),
            };
            $redimensionada = $this->reduzir($imagem, $limite);
            imagedestroy($imagem);
            $imagem = $redimensionada;
            if ($formato === 'svg') {
                return ['conteudo' => $this->vetorizar($imagem), 'extensao' => 'svg', 'largura' => imagesx($imagem), 'altura' => imagesy($imagem)];
            }
            $alvo = match ($formato) {
                'compressao_pequena' => 500 * 1024, 'compressao_media' => 250 * 1024,
                'compressao_grande' => 100 * 1024, default => null,
            };
            // PNG mantém transparência nas miniaturas; WebP permite comprimir também imagens transparentes.
            $extensao = $alvo || $formato === 'webp' ? 'webp' : 'png';
            $conteudo = $this->codificar($imagem, $extensao, 85);
            if ($alvo) {
                while (true) {
                    foreach ([80, 65, 50, 35] as $qualidade) {
                        $conteudo = $this->codificar($imagem, 'webp', $qualidade);
                        if (strlen($conteudo) <= $alvo) break 2;
                    }
                    $menor = $this->reduzir($imagem, max(1, (int) (max(imagesx($imagem), imagesy($imagem)) * .8)));
                    imagedestroy($imagem);
                    $imagem = $menor;
                    if (max(imagesx($imagem), imagesy($imagem)) === 1) {
                        $conteudo = $this->codificar($imagem, 'webp', 35);
                        break;
                    }
                }
            }
            return ['conteudo' => $conteudo, 'extensao' => $extensao, 'largura' => imagesx($imagem), 'altura' => imagesy($imagem)];
        } finally {
            imagedestroy($imagem);
        }
    }

    private function reduzir(\GdImage $origem, int $limite): \GdImage
    {
        $escala = min(1, $limite / max(imagesx($origem), imagesy($origem)));
        $largura = max(1, (int) round(imagesx($origem) * $escala));
        $altura = max(1, (int) round(imagesy($origem) * $escala));
        $nova = imagecreatetruecolor($largura, $altura);
        imagealphablending($nova, false);
        imagesavealpha($nova, true);
        imagefill($nova, 0, 0, imagecolorallocatealpha($nova, 0, 0, 0, 127));
        imagecopyresampled($nova, $origem, 0, 0, 0, 0, $largura, $altura, imagesx($origem), imagesy($origem));
        return $nova;
    }

    private function codificar(\GdImage $imagem, string $extensao, int $qualidade): string
    {
        ob_start();
        try {
            $ok = $extensao === 'webp' ? imagewebp($imagem, null, $qualidade) : imagepng($imagem, null, 9);
            $conteudo = ob_get_contents();
            if (!$ok || $conteudo === '') throw new \RuntimeException('Não foi possível gerar a imagem.');
            return $conteudo;
        } finally {
            ob_end_clean();
        }
    }

    /** Contorna regiões escuras, produzindo caminhos vetoriais sem imagens incorporadas. */
    private function vetorizar(\GdImage $imagem): string
    {
        $w = imagesx($imagem); $h = imagesy($imagem);
        $mascara = [];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $cor = imagecolorsforindex($imagem, imagecolorat($imagem, $x, $y));
                $opacidade = 1 - $cor['alpha'] / 127;
                $luz = (.299 * $cor['red'] + .587 * $cor['green'] + .114 * $cor['blue']) * $opacidade + 255 * (1 - $opacidade);
                if ($luz < 160) $mascara[$y * $w + $x] = true;
            }
        }
        $arestas = [];
        $adicionar = function ($x1, $y1, $x2, $y2) use (&$arestas): void {
            $arestas["$x1,$y1"][] = "$x2,$y2";
        };
        foreach ($mascara as $indice => $_) {
            $x = $indice % $w; $y = intdiv($indice, $w);
            if ($y === 0 || !isset($mascara[$indice - $w])) $adicionar($x, $y, $x + 1, $y);
            if ($x === $w - 1 || !isset($mascara[$indice + 1])) $adicionar($x + 1, $y, $x + 1, $y + 1);
            if ($y === $h - 1 || !isset($mascara[$indice + $w])) $adicionar($x + 1, $y + 1, $x, $y + 1);
            if ($x === 0 || !isset($mascara[$indice - 1])) $adicionar($x, $y + 1, $x, $y);
        }
        $caminhos = '';
        while ($arestas) {
            $inicio = array_key_first($arestas); $atual = $inicio; $pontos = [];
            do {
                $pontos[] = array_map('intval', explode(',', $atual));
                $proximo = array_pop($arestas[$atual]);
                if (!$arestas[$atual]) unset($arestas[$atual]);
                $atual = $proximo;
            } while ($atual !== $inicio);
            $vertices = [];
            $n = count($pontos);
            for ($i = 0; $i < $n; $i++) {
                $a = $pontos[($i + $n - 1) % $n]; $b = $pontos[$i]; $c = $pontos[($i + 1) % $n];
                if (($b[0] - $a[0]) * ($c[1] - $b[1]) !== ($b[1] - $a[1]) * ($c[0] - $b[0])) $vertices[] = implode(',', $b);
            }
            $caminhos .= 'M'.implode('L', $vertices).'Z';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$w.'" height="'.$h.'" viewBox="0 0 '.$w.' '.$h.'"><path fill="black" fill-rule="evenodd" d="'.$caminhos.'"/></svg>';
    }
}
