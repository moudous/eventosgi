<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoricoEvento extends Model
{
    public $timestamps = false;
    protected $table = 'historico_eventos';
    protected $guarded = [];
    protected $casts = ['dados' => 'array', 'data_hora' => 'datetime'];
}
