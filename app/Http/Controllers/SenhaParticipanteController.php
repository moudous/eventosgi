<?php

namespace App\Http\Controllers;

use App\Models\CodigoInscricao;
use App\Models\CredencialParticipante;
use App\Models\InscricaoSubmissao;
use App\Services\IdentificacaoParticipanteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SenhaParticipanteController
{
    public function edit(string $token, IdentificacaoParticipanteService $identificacao): View
    {
        $codigo = $this->codigoValido($token);
        if (! $codigo) return view('participantes.senha', ['valido' => false]);

        $resolucao = $identificacao->resolverParticipante((string) $codigo->email);
        $participante = $resolucao['participante'];
        if ((int) $codigo->participante_id !== (int) $participante->id) {
            $codigo->update(['participante_id' => (int) $participante->id]);
        }

        return view('participantes.senha', [
            'valido' => true,
            'token' => $token,
            'nome' => (string) $participante->nome,
            'email' => (string) $codigo->email,
        ]);
    }

    public function update(Request $request, string $token, IdentificacaoParticipanteService $identificacao): View
    {
        $dados = $request->validate([
            'senha' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'usar_na_submissao' => ['required', 'boolean'],
        ], [
            'senha.confirmed' => 'A confirmação da senha não confere.',
            'usar_na_submissao.required' => 'Informe se deseja usar a senha também nas submissões.',
        ]);

        $codigo = $this->codigoValido($token);
        if (! $codigo) return view('participantes.senha', ['valido' => false]);

        $resolucao = $identificacao->resolverParticipante((string) $codigo->email);
        $participante = $resolucao['participante'];
        $senhaHash = Hash::make($dados['senha']);
        $submissoesAtualizadas = 0;

        DB::transaction(function () use ($codigo, $participante, $senhaHash, $dados, &$submissoesAtualizadas): void {
            $bloqueado = CodigoInscricao::query()->whereKey($codigo->id)->lockForUpdate()->first();
            if (! $bloqueado || $bloqueado->redefinicao_usado_em || ! $bloqueado->redefinicao_expira_em
                || $bloqueado->redefinicao_expira_em->isPast()) {
                abort(410, 'Este link expirou ou já foi utilizado.');
            }

            $credencial = CredencialParticipante::query()->where('email', $bloqueado->email)->first();
            if ($credencial) {
                $credencial->update([
                    'participante_id' => (int) $participante->id,
                    'senha' => $senhaHash,
                    'credencial_versao' => $credencial->credencial_versao + 1,
                ]);
            } else {
                CredencialParticipante::create([
                    'participante_id' => (int) $participante->id,
                    'email' => (string) $bloqueado->email,
                    'senha' => $senhaHash,
                ]);
            }

            if ((bool) $dados['usar_na_submissao']) {
                $submissoesAtualizadas = InscricaoSubmissao::query()
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $bloqueado->email)])
                    ->update([
                        'senha' => $senhaHash,
                        'credencial_versao' => DB::raw('credencial_versao + 1'),
                        'updated_at' => now(),
                    ]);
            }

            $bloqueado->update([
                'participante_id' => (int) $participante->id,
                'redefinicao_usado_em' => now(),
            ]);
        });

        return view('participantes.senha', [
            'valido' => false,
            'sucesso' => true,
            'nome' => (string) $participante->nome,
            'submissoesAtualizadas' => $submissoesAtualizadas,
        ]);
    }

    private function codigoValido(string $token): ?CodigoInscricao
    {
        if (! preg_match('/^[A-Za-z0-9]{64}$/', $token)) return null;

        return CodigoInscricao::query()
            ->where('redefinicao_token_hash', hash('sha256', $token))
            ->whereNull('redefinicao_usado_em')
            ->where('redefinicao_expira_em', '>', now())
            ->first();
    }
}
