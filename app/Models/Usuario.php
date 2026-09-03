<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    protected $table = 'usuarios';

    protected $fillable = [
        'id',
        'nome',
        'email',
        'perfil',
        'perfil_id',
        'perfis',
        'ativo',
        'ultimo_acesso',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'perfil_id' => 'integer',
        'perfis' => 'array',
        'ultimo_acesso' => 'datetime',
    ];
}