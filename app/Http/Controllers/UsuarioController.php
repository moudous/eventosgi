<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\GiUsuarioSynchronizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsuarioController
{
    public function index(): View
    {
        return view('usuarios.index', [
            'usuarios' => Usuario::query()->orderByDesc('id')->get(),
        ]);
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