<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CredencialSubmissao extends Model
{
    protected $table = 'credenciais_submissao';
    protected $fillable = ['email', 'senha', 'credencial_versao', 'temporaria_hash', 'temporaria_expira_em', 'redefinicao_token_hash', 'redefinicao_expira_em'];
    protected $hidden = ['senha', 'temporaria_hash', 'redefinicao_token_hash'];
    protected $casts = ['credencial_versao' => 'integer', 'temporaria_expira_em' => 'datetime', 'redefinicao_expira_em' => 'datetime'];
}
