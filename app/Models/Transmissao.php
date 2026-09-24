<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Transmissao extends Model
{
    /** O plural em português não é inferido corretamente pelo Eloquent. */
    protected $table = 'transmissoes';

    public const STATUS_AGENDADA = 'agendada';

    public const STATUS_EM_ANDAMENTO = 'em_andamento';

    public const STATUS_FINALIZADA = 'finalizada';

    public const STATUS_CANCELADA = 'cancelada';

    public const STATUS = [
        self::STATUS_AGENDADA,
        self::STATUS_EM_ANDAMENTO,
        self::STATUS_FINALIZADA,
        self::STATUS_CANCELADA,
    ];

    protected $fillable = [
        'evento_id',
        'titulo',
        'descricao',
        'status',
        'agendada_para',
        'iniciada_em',
        'finalizada_em',
        'youtube_broadcast_id',
        'youtube_video_id',
        'youtube_url',
        'miniatura_url',
    ];

    protected static function booted(): void
    {
        static::creating(function (Transmissao $transmissao): void {
            $transmissao->hash_publico ??= Str::random(64);
        });
    }

    protected $casts = [
        'evento_id' => 'integer',
        'agendada_para' => 'datetime',
        'iniciada_em' => 'datetime',
        'finalizada_em' => 'datetime',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_EM_ANDAMENTO => 'Em andamento',
            self::STATUS_FINALIZADA => 'Finalizada',
            self::STATUS_CANCELADA => 'Cancelada',
            default => 'Agendada',
        };
    }

    public function statusClass(): string
    {
        return match ($this->status) {
            self::STATUS_EM_ANDAMENTO => 'text-bg-danger',
            self::STATUS_FINALIZADA => 'text-bg-success',
            self::STATUS_CANCELADA => 'text-bg-secondary',
            default => 'text-bg-primary',
        };
    }
}
