<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Atividade extends Model
{
    use SoftDeletes;

    protected $fillable = ['nome', 'ativo', 'criado_por', 'evento_id', 'modalidade', 'data_inicio', 'data_fim', 'formulario'];
    protected $casts = [
        'ativo' => 'boolean', 'criado_por' => 'integer', 'evento_id' => 'integer',
        'data_inicio' => 'datetime', 'data_fim' => 'datetime', 'deleted_at' => 'datetime', 'formulario' => 'array',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class)->withTrashed();
    }
}
