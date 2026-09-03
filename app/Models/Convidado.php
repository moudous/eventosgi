<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Convidado extends Model
{
    protected $fillable = ['nome','sobrenome','titulacao','curriculo','descricao','local','telefone_whatsapp','redes_sociais','email'];
    protected $casts = ['redes_sociais' => 'array'];
}
