<?php

namespace App\Services;

use App\Models\CodigoInscricao;
use App\Models\CredencialParticipante;
use App\Models\CredencialSubmissao;
use Illuminate\Support\Facades\DB;

class SenhaCompartilhadaService
{
    /** Chamado dentro da transação da troca de senha. Não cria cadastros no outro fluxo. */
    public function atualizarSubmissao(string $email, string $hash): int
    {
        return CredencialSubmissao::where('email', mb_strtolower(trim($email)))->update([
            'senha' => $hash, 'credencial_versao' => DB::raw('credencial_versao + 1'),
            'temporaria_hash' => null, 'temporaria_expira_em' => null,
            'redefinicao_token_hash' => null, 'redefinicao_expira_em' => null,
        ]);
    }

    public function atualizarAtividade(string $email, string $hash): int
    {
        $email = mb_strtolower(trim($email));
        $total = CredencialParticipante::where('email', $email)->update([
            'senha' => $hash, 'credencial_versao' => DB::raw('credencial_versao + 1'),
        ]);
        if ($total) $this->invalidarCodigosAtividade($email);
        return $total;
    }

    public function invalidarCodigosAtividade(string $email): void
    {
        CodigoInscricao::where('email', $email)->update([
            'expira_em' => now()->subSecond(), 'redefinicao_expira_em' => now()->subSecond(),
        ]);
    }
}
