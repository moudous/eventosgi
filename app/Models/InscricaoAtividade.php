<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InscricaoAtividade extends Model
{
    protected $table = 'inscricoes_atividade';
    protected $fillable = ['atividade_id', 'participante_id', 'participante_email', 'resposta', 'ip', 'user_agent', 'dispositivo', 'comprovante_hash'];
    protected $casts = ['resposta' => 'array', 'dispositivo' => 'array', 'participante_id' => 'integer'];

    protected static function booted(): void
    {
        static::creating(function (InscricaoAtividade $inscricao): void {
            $inscricao->comprovante_hash ??= bin2hex(random_bytes(32));
        });
    }

    public function atividade(): BelongsTo
    {
        return $this->belongsTo(Atividade::class);
    }
}
