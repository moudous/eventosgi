<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissaoAutor extends Model
{
    protected $table = 'submissao_autores';

    protected $fillable = [
        'inscrito_submissao_trabalho_id', 'nome', 'email', 'afiliacao', 'principal', 'ordem', 'numero',
    ];

    protected $casts = [
        'inscrito_submissao_trabalho_id' => 'integer',
        'principal' => 'boolean',
        'ordem' => 'integer',
        'numero' => 'integer',
    ];

    public function trabalho(): BelongsTo
    {
        return $this->belongsTo(InscricaoSubmissaoTrabalho::class, 'inscrito_submissao_trabalho_id');
    }
}
