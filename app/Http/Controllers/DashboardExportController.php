<?php

namespace App\Http\Controllers;

use App\Services\DashboardExportService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DashboardExportController
{
    private const CARDS = ['inscricoes-categoria', 'inscritos-opcao', 'evolucao-inscricoes', 'inscricoes-dispositivo'];
    private const FORMATOS = ['html', 'pdf', 'imagem'];

    public function link(Request $request, string $card, string $formato): RedirectResponse
    {
        $this->validarRota($card, $formato);
        $dados = $request->validate([
            'evento' => ['sometimes', 'integer', 'min:0'],
            'atividade' => ['sometimes', 'integer', 'min:0'],
            'campo' => ['sometimes', 'nullable', 'string', 'max:150'],
            'dispositivo_evento' => ['sometimes', 'integer', 'min:0'],
            'dispositivo_atividade' => ['sometimes', 'integer', 'min:0'],
        ]);

        $permitidos = match ($card) {
            'inscritos-opcao' => ['evento', 'atividade', 'campo'],
            'evolucao-inscricoes' => ['evento', 'atividade'],
            'inscricoes-dispositivo' => ['dispositivo_evento', 'dispositivo_atividade'],
            default => [],
        };
        $filtros = array_intersect_key($dados, array_flip($permitidos));
        $url = URL::temporarySignedRoute('dashboard.exportar', now()->addMinutes(5), [
            'card' => $card,
            'formato' => $formato,
            ...$filtros,
        ]);

        return redirect()->to($url);
    }

    public function show(Request $request, string $card, string $formato, DashboardExportService $exportacao): Response
    {
        $this->validarRota($card, $formato);
        $dadosDashboard = app(DashboardController::class)($request)->getData();
        $relatorio = $exportacao->relatorio($card, $dadosDashboard);
        $nome = 'dashboard-'.(Str::slug($relatorio['titulo']) ?: 'exportacao').'-'.now()->format('Y-m-d-His');
        $cabecalhos = [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Referrer-Policy' => 'no-referrer',
        ];

        if ($formato === 'imagem') {
            return response($exportacao->png($relatorio), 200, $cabecalhos + [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'attachment; filename="'.$nome.'.png"',
            ]);
        }

        $html = view('dashboard-exportacao', ['relatorio' => $relatorio, 'pdf' => $formato === 'pdf'])->render();
        if ($formato === 'html') {
            return response($html, 200, $cabecalhos + [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$nome.'.html"',
            ]);
        }

        $opcoes = new Options;
        $opcoes->set('isRemoteEnabled', false);
        $opcoes->set('defaultFont', 'DejaVu Sans');
        $documento = new Dompdf($opcoes);
        $documento->loadHtml($html, 'UTF-8');
        $documento->setPaper('A4', 'landscape');
        $documento->render();

        return response($documento->output(), 200, $cabecalhos + [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nome.'.pdf"',
        ]);
    }

    private function validarRota(string $card, string $formato): void
    {
        abort_unless(in_array($card, self::CARDS, true) && in_array($formato, self::FORMATOS, true), 404);
    }
}
