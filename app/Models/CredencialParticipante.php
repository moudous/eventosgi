<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CredencialParticipante extends Model
{
    protected $table = 'credenciais_participante';

    protected $fillable = ['participante_id', 'email', 'senha', 'credencial_versao'];

    protected $hidden = ['senha'];

    protected $casts = [
        'participante_id' => 'integer',
        'credencial_versao' => 'integer',
    ];
}
