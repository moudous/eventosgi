<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class GiUsuarioSynchronizer
{
    public function syncFromGi(string $accessToken): int
    {
        $response = Http::withToken($accessToken)->acceptJson()->timeout(10)
            ->get(rtrim(config('gi.gi_url'), '/').'/api/integracoes/v1/usuarios');

        abort_unless($response->successful(), $response->status(), 'Não foi possível importar os usuários do GI.');

        $usuarios = $response->json('data');
        if (! is_array($usuarios)) {
            $usuarios = $response->json('usuarios', []);
        }
        if (Arr::isAssoc($usuarios) && isset($usuarios['id'])) {
            $usuarios = [$usuarios];
        }

        foreach ($usuarios as $dados) {
            if (! is_array($dados) || ! isset($dados['id'])) {
                continue;
            }

            Usuario::query()->updateOrCreate(
                ['id' => $dados['id']],
                [
                    'nome' => $dados['nome'] ?? $dados['name'] ?? 'Sem nome',
                    'email' => $dados['email'] ?? null,
                    'perfil' => data_get($dados, 'perfil.nome'),
                    'perfil_id' => data_get($dados, 'perfil.id'),
                    'perfis' => $dados['perfis'] ?? null,
                    'ativo' => $dados['ativo'] ?? true,
                    'ultimo_acesso' => $dados['ultimo_acesso'] ?? null,
                ],
            );
        }

        return count($usuarios);
    }
}