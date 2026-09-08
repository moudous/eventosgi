<?php

namespace App\Services;

use App\Models\Atividade;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class CaptchaInscricaoService
{
    private const MINUTOS_VALIDADE = 10;
    private const CARACTERES = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function imagem(Request $request, Atividade $atividade, bool $renovar = false): Response
    {
        $chave = $this->chave($atividade);
        $desafio = $request->session()->get($chave);
        if ($renovar || ! is_array($desafio) || empty($desafio['texto'])
            || (int) ($desafio['expira_em'] ?? 0) < now()->timestamp) {
            $texto = $this->texto();
            $desafio = ['texto' => $texto, 'expira_em' => now()->addMinutes(self::MINUTOS_VALIDADE)->timestamp];
            $request->session()->put($chave, $desafio);
        }

        return response($this->png((string) $desafio['texto']), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function validar(Request $request, Atividade $atividade, string $resposta): void
    {
        $chave = $this->chave($atividade);
        $desafio = $request->session()->pull($chave);
        $informado = mb_strtoupper(preg_replace('/\s+/', '', trim($resposta)) ?? '');
        $valido = is_array($desafio)
            && (int) ($desafio['expira_em'] ?? 0) >= now()->timestamp
            && hash_equals((string) ($desafio['texto'] ?? ''), $informado);

        if (! $valido) {
            throw ValidationException::withMessages([
                'captcha' => 'O texto da imagem está incorreto ou expirou. Veja a nova imagem e tente novamente.',
            ])->errorBag('identificacao');
        }
    }

    private function texto(): string
    {
        $texto = '';
        for ($i = 0; $i < 6; $i++) $texto .= self::CARACTERES[random_int(0, strlen(self::CARACTERES) - 1)];

        return $texto;
    }

    private function chave(Atividade $atividade): string
    {
        return 'captcha_inscricao.'.$atividade->id;
    }

    private function png(string $texto): string
    {
        $imagem = imagecreatetruecolor(220, 70);
        $fundo = imagecolorallocate($imagem, random_int(232, 248), random_int(235, 248), random_int(238, 250));
        imagefill($imagem, 0, 0, $fundo);

        for ($i = 0; $i < 9; $i++) {
            $cor = imagecolorallocatealpha($imagem, random_int(60, 180), random_int(60, 180), random_int(60, 180), 65);
            imageline($imagem, random_int(0, 219), random_int(0, 69), random_int(0, 219), random_int(0, 69), $cor);
        }
        for ($i = 0; $i < 180; $i++) {
            $cor = imagecolorallocatealpha($imagem, random_int(30, 200), random_int(30, 200), random_int(30, 200), 75);
            imagesetpixel($imagem, random_int(0, 219), random_int(0, 69), $cor);
        }

        $fonte = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        foreach (str_split($texto) as $indice => $caractere) {
            $cor = imagecolorallocate($imagem, random_int(15, 65), random_int(25, 80), random_int(35, 95));
            $x = 17 + $indice * 32 + random_int(-2, 2);
            $y = 48 + random_int(-4, 4);
            if (is_file($fonte) && function_exists('imagettftext')) {
                imagettftext($imagem, 25, random_int(-14, 14), $x, $y, $cor, $fonte, $caractere);
            } else {
                imagestring($imagem, 5, $x, 25 + random_int(-3, 3), $caractere, $cor);
            }
        }

        ob_start();
        imagepng($imagem);
        $png = (string) ob_get_clean();
        imagedestroy($imagem);

        return $png;
    }
}
