<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InscricaoAtividade extends Model
{
    protected $table = 'inscricoes_atividade';
    protected $fillable = ['atividade_id', 'sessao_atividade_id', 'participante_id', 'participante_email', 'resposta', 'ip', 'user_agent', 'dispositivo', 'comprovante_hash', 'presente', 'data_presenca', 'presenca_validada_por', 'codigo_qr'];
    protected $casts = [
        'resposta' => 'array', 'dispositivo' => 'array', 'participante_id' => 'integer', 'sessao_atividade_id' => 'integer',
        'presente' => 'boolean', 'data_presenca' => 'datetime', 'presenca_validada_por' => 'integer',
    ];

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

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class);
    }

    public function sessao(): BelongsTo
    {
        return $this->belongsTo(SessaoAtividade::class, 'sessao_atividade_id')->withTrashed();
    }

    public function validadorPresenca(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'presenca_validada_por');
    }
}
