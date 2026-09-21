<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoPresencaLink extends Model
{
    protected $table = 'auto_presenca_links';

    protected $fillable = ['atividade_id', 'hash', 'inicio', 'fim', 'ajuste_minutos', 'cliques'];

    protected $casts = [
        'atividade_id' => 'integer',
        'inicio' => 'datetime',
        'fim' => 'datetime',
        'ajuste_minutos' => 'integer',
        'cliques' => 'integer',
    ];

    public function atividade(): BelongsTo
    {
        return $this->belongsTo(Atividade::class);
    }

    public function estaAberto(): bool
    {
        return now()->between($this->inicio, $this->fim);
    }
}
