<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricoAtividade extends Model
{
    public $timestamps = false;
    protected $table = 'historico_atividades';
    protected $guarded = [];
    protected $casts = ['dados' => 'array', 'data_hora' => 'datetime'];
}
