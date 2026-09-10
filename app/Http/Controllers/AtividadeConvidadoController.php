<?php

namespace App\Http\Controllers;

use App\Models\Atividade;
use App\Models\Convidado;
use App\Services\HistoricoService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AtividadeConvidadoController
{
    public function edit(Atividade $atividade): View
    {
        $permissoes = app(\App\Services\GiPermissionService::class);
        $podeEditar = $permissoes->permite('atividades.convidados.editar');
        abort_unless($podeEditar || $permissoes->permite('atividades.convidados.visualizar'), 403);
        return view('atividades.convidados', [
            'podeEditar' => $podeEditar,
            'atividade' => $atividade,
            'convidados' => Convidado::orderBy('nome')->orderBy('sobrenome')->get(['id', 'nome', 'sobrenome', 'foto_nome']),
            'selecionados' => $atividade->convidados()->pluck('convidados.id')->all(),
        ]);
    }

    public function update(Request $request, Atividade $atividade, HistoricoService $historico): RedirectResponse
    {
        app(\App\Services\GiPermissionService::class)->exigir('atividades.convidados.editar', $request);
        $dados = $request->validate([
            'convidados' => ['nullable', 'array'],
            'convidados.*' => ['required', 'integer', 'distinct', 'exists:convidados,id'],
        ]);
        $ids = array_values(($dados['convidados'] ?? []) ?: []);
        DB::transaction(function () use ($atividade, $ids, $historico, $request) {
            Atividade::whereKey($atividade->id)->lockForUpdate()->firstOrFail();
            $antes = $atividade->convidados()->pluck('convidados.id')->all();
            $vinculos = [];
            foreach ($ids as $ordem => $id) $vinculos[(int) $id] = ['ordem' => $ordem + 1];
            $atividade->convidados()->sync($vinculos);
            $alteracoes = $historico->alteracoes(['convidados' => $antes], ['convidados' => array_map('intval', $ids)]);
            if ($alteracoes !== []) $historico->atividade($atividade, 'Convidados / Palestrantes alterados', $alteracoes, $request);
        });
        return redirect()->route('atividades.convidados.edit', $atividade)->with('status', 'Convidados / Palestrantes salvos com sucesso.');
    }
}
