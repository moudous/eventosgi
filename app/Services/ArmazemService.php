<?php

namespace App\Services;

use Illuminate\Http\Request;

class ArmazemService
{
    public function recuperar(string $recurso, Request $request): array
    {
        $estado = (array) $request->session()->get("armazem.{$recurso}", []);

        $estado['page'] = max(1, (int) ($estado['page'] ?? 1));
        $estado['pesquisar'] = (string) ($estado['pesquisar'] ?? '');
        $estado['por_pagina'] = min(100, max(1, (int) ($estado['por_pagina'] ?? 10)));

        return $estado;
    }

    public function salvar(
        string $recurso,
        Request $request,
        int $pagina,
        string $pesquisar,
        int $porPagina,
        array $filtros = [],
    ): array
    {
        $estado = array_merge($filtros, [
            'page' => max(1, $pagina),
            'pesquisar' => trim($pesquisar),
            'por_pagina' => min(100, max(1, $porPagina)),
        ]);

        $request->session()->put("armazem.{$recurso}", $estado);

        return $estado;
    }
}
