<?php

namespace App\Http\Controllers;

use App\Models\CredencialSubmissao;
use App\Services\SenhaCompartilhadaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SenhaSubmissaoController
{
    public function edit(string $token): View
    {
        $credencial = $this->codigoValido($token)->first();
        return view('submissoes.senha', ['valido' => (bool) $credencial, 'email' => $credencial?->email, 'token' => $token]);
    }

    public function update(Request $request, string $token, SenhaCompartilhadaService $senhas): View
    {
        $dados = $request->validate([
            'senha' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'usar_na_atividade' => ['required', 'boolean'],
        ]);
        $resultado = DB::transaction(function () use ($token, $dados, $senhas): ?array {
            $credencial = $this->codigoValido($token)->lockForUpdate()->first();
            if (! $credencial) return null;
            $hash = Hash::make($dados['senha']);
            $senhas->atualizarSubmissao($credencial->email, $hash);
            $atividade = $dados['usar_na_atividade'] ? $senhas->atualizarAtividade($credencial->email, $hash) : 0;
            return ['atividadeAtualizada' => $atividade > 0];
        });
        return view('submissoes.senha', ['valido' => false, 'sucesso' => $resultado !== null] + ($resultado ?? []));
    }

    private function codigoValido(string $token): \Illuminate\Database\Eloquent\Builder
    {
        return CredencialSubmissao::where('redefinicao_token_hash', hash('sha256', $token))
            ->where('redefinicao_expira_em', '>', now());
    }
}
