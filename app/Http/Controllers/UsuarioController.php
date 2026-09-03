<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\ArmazemService;
use App\Services\GiUsuarioSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsuarioController
{
    public function index(Request $request, ArmazemService $armazem): View
    {
        return view('usuarios.index', [
            'usuarios' => Usuario::query()->orderByDesc('id')->get(),
            'estadoTabela' => $armazem->recuperar('usuarios', $request),
        ]);
    }

    public function salvarEstadoTabela(Request $request, ArmazemService $armazem): JsonResponse
    {
        $dados = $request->validate([
            'page' => ['required', 'integer', 'min:1'],
            'pesquisar' => ['nullable', 'string', 'max:500'],
            'por_pagina' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $estado = $armazem->salvar(
            'usuarios',
            $request,
            (int) $dados['page'],
            (string) ($dados['pesquisar'] ?? ''),
            (int) $dados['por_pagina'],
        );

        return response()->json(['estado' => $estado]);
    }

    public function import(Request $request, GiUsuarioSynchronizer $synchronizer): JsonResponse
    {
        $accessToken = (string) $request->session()->get('gi_context.access_token', '');
        abort_if($accessToken === '', 401, 'Token de acesso do GI não encontrado. Abra novamente pelo menu do GI.');

        $total = $synchronizer->syncFromGi($accessToken);

        return response()->json([
            'message' => "$total usuário(s) importado(s) com sucesso.",
            'total' => $total,
        ]);
    }

    public function show(Usuario $usuario): View
    {
        return view('usuarios.show', compact('usuario'));
    }
}
