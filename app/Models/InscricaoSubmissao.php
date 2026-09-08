<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InscricaoSubmissao extends Model
{
    protected $table = 'inscritos_submissao';

    protected $fillable = [
        'submissao_id', 'email', 'senha', 'credencial_versao',
    ];

    protected $hidden = ['senha'];

    protected $casts = [
        'submissao_id' => 'integer',
        'credencial_versao' => 'integer',
    ];

    public function submissao(): BelongsTo
    {
        return $this->belongsTo(Submissao::class);
    }

    public function trabalhos(): HasMany
    {
        return $this->hasMany(InscricaoSubmissaoTrabalho::class, 'inscrito_submissao_id')->orderByDesc('updated_at');
    }
}
