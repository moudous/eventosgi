<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InscricaoAtividade extends Model
{
    protected $table = 'inscricoes_atividade';
    protected $fillable = ['atividade_id', 'sessao_atividade_id', 'participante_id', 'participante_email', 'utm_rastreio', 'lista_reserva', 'resposta', 'ip', 'user_agent', 'dispositivo', 'comprovante_hash', 'presente', 'data_presenca', 'presenca_validada_por', 'codigo_qr'];
    protected $casts = [
        'resposta' => 'array', 'dispositivo' => 'array', 'participante_id' => 'integer', 'sessao_atividade_id' => 'integer',
        'presente' => 'boolean', 'lista_reserva' => 'boolean', 'data_presenca' => 'datetime', 'presenca_validada_por' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (InscricaoAtividade $inscricao): void {
            $inscricao->comprovante_hash ??= bin2hex(random_bytes(32));
        });
        static::deleting(fn (InscricaoAtividade $inscricao) => $inscricao->cobrancasPix()->delete());
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

    public function cobrancasPix(): HasMany
    {
        return $this->hasMany(PixCobranca::class, 'inscricao_atividade_id');
    }
}
