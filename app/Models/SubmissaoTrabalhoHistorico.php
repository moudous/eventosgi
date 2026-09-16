<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissaoTrabalhoHistorico extends Model
{
    public $timestamps = false;

    protected $table = 'submissao_trabalho_historicos';
    protected $guarded = [];
    protected $casts = ['dados' => 'array', 'data_hora' => 'datetime'];
}
