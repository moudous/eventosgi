<?php

namespace App\Http\Controllers;

use App\Models\Atividade;
use App\Services\CaptchaInscricaoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CaptchaInscricaoController
{
    public function __invoke(Request $request, Atividade $atividade, CaptchaInscricaoService $captcha): Response
    {
        abort_unless($atividade->ativo && $atividade->formulario, 404);

        return $captcha->imagem($request, $atividade, $request->boolean('novo'));
    }
}
