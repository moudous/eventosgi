<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Participante extends Model
{
    use SoftDeletes;
    protected $connection = 'cert';
    protected $table = 'participantes';
    public const CREATED_AT = 'criado_em';
    public const UPDATED_AT = 'atualizado_em';
    public const DELETED_AT = 'excluido_em';
    protected $fillable = ['nome','email','email2','email_institucional','instituicao_ensino','sexo','grupo','ativo','cpf','criado_por'];
    protected $casts = ['ativo'=>'boolean','criado_em'=>'datetime','atualizado_em'=>'datetime','excluido_em'=>'datetime'];

    protected function setKeysForSelectQuery($query): mixed
    {
        return $query->where('id', $this->getAttribute('id'))->where('nome', $this->getOriginal('nome', $this->getAttribute('nome')));
    }

    protected function setKeysForSaveQuery($query): mixed
    {
        return $this->setKeysForSelectQuery($query);
    }
}
