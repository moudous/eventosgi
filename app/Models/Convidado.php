<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Convidado extends Model
{
    protected $fillable = ['evento_id','nome','sobrenome','titulacao','curriculo','descricao','local','telefone_whatsapp','redes_sociais','email','foto_nome'];
    protected $casts = ['evento_id' => 'integer', 'redes_sociais' => 'array'];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class)->withTrashed();
    }
}
