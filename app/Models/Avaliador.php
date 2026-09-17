<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Avaliador extends Model
{
    protected $table = 'avaliadores';

    protected $fillable = ['nome', 'usuario_id'];

    protected $casts = ['usuario_id' => 'integer'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function trabalhos(): HasMany
    {
        return $this->hasMany(InscricaoSubmissaoTrabalho::class);
    }
}
