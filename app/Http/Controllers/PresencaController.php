<?php

namespace App\Http\Controllers;

use App\Models\InscricaoAtividade;
use App\Services\PresencaQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PresencaController
{
    public function index(): View
    {
        return view('atividades.validador-presenca');
    }

    public function validar(Request $request, PresencaQrService $presenca): RedirectResponse
    {
        $dados = $request->validate([
            'codigo_qr' => ['required', 'string', 'max:64'],
        ], ['codigo_qr.required' => 'Leia o QR Code ou informe o código da inscrição.']);

        $codigo = mb_strtoupper(trim($dados['codigo_qr']));
        $inscricao = InscricaoAtividade::query()->with(['atividade.evento'])
            ->where('codigo_qr', $codigo)->first();

        if (! $inscricao || empty($inscricao->atividade?->formulario['registrar_presenca_qrcode'])) {
            return back()->withErrors(['codigo_qr' => 'QR Code inválido ou não habilitado para presença.'])->withInput();
        }

        return back()->with('presenca_confirmacao', $this->resultado(
            $inscricao,
            $inscricao->presente ? 'Esta presença já está registrada. Confirme os dados antes de continuar.' : 'Confira os dados antes de registrar a presença.',
            'confirmacao',
        ));
    }

    public function confirmar(Request $request, PresencaQrService $presenca): RedirectResponse
    {
        $dados = $request->validate([
            'codigo_qr' => ['required', 'string', 'max:64'],
            'decisao' => ['required', 'in:validar,cancelar'],
        ]);
        $inscricao = InscricaoAtividade::query()->with(['atividade.evento'])
            ->where('codigo_qr', mb_strtoupper(trim($dados['codigo_qr'])))->firstOrFail();

        if ($dados['decisao'] === 'cancelar') {
            return back()->with('presenca_resultado', $this->resultado($inscricao, 'Validação de presença cancelada.', 'cancelada'));
        }

        if (! $inscricao->presente) $presenca->definir($inscricao, true, $this->usuarioGi($request));

        return back()->with('presenca_resultado', $this->resultado(
            $inscricao->refresh(),
            'Presença validada com sucesso.',
            'validada',
        ));
    }

    public function definir(Request $request, InscricaoAtividade $inscricao, PresencaQrService $presenca): JsonResponse
    {
        $dados = $request->validate(['presente' => ['required', 'boolean']]);
        $presenca->definir($inscricao, (bool) $dados['presente'], $this->usuarioGi($request));
        $inscricao->refresh();

        return response()->json([
            'message' => $inscricao->presente ? 'Presença registrada.' : 'Presença removida.',
            'presente' => $inscricao->presente,
            'data_presenca' => $inscricao->data_presenca?->format('d/m/Y H:i:s'),
        ]);
    }

    private function usuarioGi(Request $request): int
    {
        $usuario = (int) $request->session()->get('gi_context.usuario.id');
        abort_unless($usuario > 0, 401, 'Abra esta aplicação pelo menu do GI.');

        return $usuario;
    }

    /** @return array<string, mixed> */
    private function resultado(InscricaoAtividade $inscricao, string $mensagem, string $status): array
    {
        $participante = $inscricao->participante_id ? $inscricao->participante()->first() : null;

        return [
            'mensagem' => $mensagem,
            'status' => $status,
            'codigo_qr' => $inscricao->codigo_qr,
            'inscricao' => $inscricao->id,
            'participante' => $participante?->nome ?: $inscricao->participante_email ?: 'Participante não identificado',
            'email' => $inscricao->participante_email ?: $participante?->email,
            'cpf' => $participante?->cpf,
            'atividade' => $inscricao->atividade->nome,
            'evento' => $inscricao->atividade->evento?->nome,
            'data_inscricao' => $inscricao->created_at?->format('d/m/Y H:i:s'),
            'data_presenca' => $inscricao->data_presenca?->format('d/m/Y H:i:s'),
        ];
    }
}
