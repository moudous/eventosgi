<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaixaIpLiberada extends Model
{
    protected $table = 'faixas_ip_liberadas';

    protected $fillable = ['faixa', 'descricao', 'ativo', 'criado_por'];

    protected $casts = ['ativo' => 'boolean', 'criado_por' => 'integer'];

    public function criador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'criado_por');
    }
}
