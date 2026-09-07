<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    protected $table = 'categorias';

    protected $fillable = ['nome', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    public function atividades(): HasMany
    {
        return $this->hasMany(Atividade::class);
    }

    /**
     * Ha atividade usando esta categoria?
     *
     * Conta tambem as apagadas: elas podem ser restauradas, e voltariam apontando para
     * uma categoria que nao existe mais.
     */
    public function temAtividades(): bool
    {
        return $this->atividades()->withTrashed()->exists();
    }
}
