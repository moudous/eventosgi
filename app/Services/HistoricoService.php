<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\Evento;
use App\Models\HistoricoAtividade;
use App\Models\HistoricoEvento;
use Illuminate\Http\Request;

class HistoricoService
{
    public function evento(Evento $evento, string $texto, array $dados, Request $request): void
    {
        HistoricoEvento::create(['evento_id' => $evento->id, 'historico' => $texto, 'usuario' => $this->usuario($request), 'dados' => $dados, 'data_hora' => now()]);
    }

    public function atividade(Atividade $atividade, string $texto, array $dados, Request $request): void
    {
        HistoricoAtividade::create(['atividade_id' => $atividade->id, 'historico' => $texto, 'usuario' => $this->usuario($request), 'dados' => $dados, 'data_hora' => now()]);
    }

    public function alteracoes(array $antes, array $depois): array
    {
        $resultado = [];
        foreach ($depois as $campo => $valor) {
            if (($antes[$campo] ?? null) != $valor) $resultado[$campo] = ['antes' => $antes[$campo] ?? null, 'depois' => $valor];
        }
        return $resultado;
    }

    private function usuario(Request $request): ?int
    {
        $id = $request->session()->get('gi_context.usuario.id');
        return is_numeric($id) ? (int) $id : null;
    }
}
