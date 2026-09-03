<?php

namespace App\Services;

use Illuminate\Http\Request;

class GiPermissionService
{
    public function permite(string $permissao, ?Request $request = null): bool
    {
        $request ??= request();

        return in_array($permissao, (array) $request->session()->get('gi_context.permissoes', []), true);
    }

    public function exigir(string $permissao, ?Request $request = null): void
    {
        abort_unless($this->permite($permissao, $request), 403, "Seu perfil não possui a permissão {$permissao}.");
    }
}