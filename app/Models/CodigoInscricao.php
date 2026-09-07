<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Codigo de uso unico enviado por e-mail para identificar o visitante antes do formulario da atividade.
 */
class CodigoInscricao extends Model
{
    protected $table = 'codigos_inscricao';
    protected $fillable = ['atividade_id', 'email', 'participante_id', 'codigo_hash', 'token_hash', 'tentativas', 'expira_em', 'token_expira_em', 'validado_em', 'ip'];
    protected $casts = ['atividade_id' => 'integer', 'participante_id' => 'integer', 'tentativas' => 'integer',
        'expira_em' => 'datetime', 'token_expira_em' => 'datetime', 'validado_em' => 'datetime'];

    public function expirado(): bool
    {
        return $this->expira_em === null || $this->expira_em->isPast();
    }
}
