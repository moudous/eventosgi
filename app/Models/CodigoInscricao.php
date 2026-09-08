<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Código temporário global enviado por e-mail para identificar o visitante.
 */
class CodigoInscricao extends Model
{
    protected $table = 'codigos_inscricao';
    protected $fillable = ['email', 'participante_id', 'codigo_hash', 'token_hash', 'tentativas', 'expira_em',
        'token_expira_em', 'validado_em', 'ip', 'redefinicao_token_hash', 'redefinicao_expira_em', 'redefinicao_usado_em'];
    protected $casts = ['participante_id' => 'integer', 'tentativas' => 'integer', 'expira_em' => 'datetime',
        'token_expira_em' => 'datetime', 'validado_em' => 'datetime', 'redefinicao_expira_em' => 'datetime',
        'redefinicao_usado_em' => 'datetime'];

    public function expirado(): bool
    {
        return $this->expira_em === null || $this->expira_em->isPast();
    }
}
