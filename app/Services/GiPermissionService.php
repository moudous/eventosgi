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

    public function permiteAlguma(array $permissoes, ?Request $request = null): bool
    {
        $request ??= request();

        foreach ($permissoes as $permissao) {
            if ($this->permite($permissao, $request)) return true;
        }

        return false;
    }

    public function exigirAlguma(array $permissoes, ?Request $request = null): void
    {
        abort_unless(
            $this->permiteAlguma($permissoes, $request),
            403,
            'Seu perfil não possui nenhuma das permissões necessárias: '.implode(', ', $permissoes).'.',
        );
    }
}
