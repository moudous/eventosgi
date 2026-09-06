<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InscricaoAtividade extends Model
{
    protected $table = 'inscricoes_atividade';
    protected $fillable = ['atividade_id', 'resposta'];
    protected $casts = ['resposta' => 'array'];

    public function atividade(): BelongsTo
    {
        return $this->belongsTo(Atividade::class);
    }
}