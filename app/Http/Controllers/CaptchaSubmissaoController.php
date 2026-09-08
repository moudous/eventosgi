<?php

namespace App\Http\Controllers;

use App\Models\Submissao;
use App\Services\CaptchaInscricaoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CaptchaSubmissaoController
{
    public function __invoke(Request $request, Submissao $submissao, CaptchaInscricaoService $captcha): Response
    {
        abort_unless($submissao->ativo && (bool) $submissao->evento?->ativo, 404);

        return $captcha->imagemSubmissao($request, $submissao, $request->boolean('novo'));
    }
}
