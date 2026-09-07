<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplatePagina extends Model
{
    protected $table = 'templates_pagina';

    protected $fillable = ['nome', 'pasta', 'descricao', 'versao', 'variaveis', 'ativo', 'importado_por'];

    protected $casts = ['variaveis' => 'array', 'ativo' => 'boolean', 'importado_por' => 'integer'];

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class, 'template_pagina_id');
    }

    /** Ha evento usando este template? Conta tambem os apagados, que podem voltar. */
    public function temEventos(): bool
    {
        return $this->eventos()->withTrashed()->exists();
    }
}
