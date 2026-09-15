<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissaoAutorNotificacao extends Model
{
    protected $table = 'submissao_autor_notificacoes';

    protected $fillable = [
        'inscrito_submissao_trabalho_id', 'email', 'nome', 'principal', 'ativo',
        'adicionado_em', 'adicao_enviada_em', 'removido_em', 'remocao_enviada_em',
    ];

    protected $casts = [
        'inscrito_submissao_trabalho_id' => 'integer',
        'principal' => 'boolean',
        'ativo' => 'boolean',
        'adicionado_em' => 'datetime',
        'adicao_enviada_em' => 'datetime',
        'removido_em' => 'datetime',
        'remocao_enviada_em' => 'datetime',
    ];

    public function trabalho(): BelongsTo
    {
        return $this->belongsTo(InscricaoSubmissaoTrabalho::class, 'inscrito_submissao_trabalho_id');
    }
}
