<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissaoResultadoNotificacao extends Model
{
    public $timestamps = false;

    protected $table = 'submissao_resultado_notificacoes';
    protected $guarded = [];
    protected $casts = ['versao' => 'integer', 'principal' => 'boolean', 'enviado_em' => 'datetime'];
}
