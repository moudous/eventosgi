<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evento extends Model
{
    use SoftDeletes;

    protected $fillable = ['nome', 'ativo', 'template_pagina_id', 'pagina_variaveis'];

    protected $casts = [
        'ativo' => 'boolean',
        'deleted_at' => 'datetime',
        'template_pagina_id' => 'integer',
        'pagina_variaveis' => 'array',
    ];

    public function templatePagina(): BelongsTo
    {
        return $this->belongsTo(TemplatePagina::class, 'template_pagina_id');
    }
}
