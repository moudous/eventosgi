<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SessaoAtividade extends Model
{
    use SoftDeletes;

    protected $table = 'sessoes_atividade';
    protected $fillable = ['atividade_id', 'nome', 'data_inicio', 'data_fim', 'limite_vagas', 'ativo', 'ordem'];
    protected $casts = [
        'atividade_id' => 'integer', 'data_inicio' => 'datetime', 'data_fim' => 'datetime',
        'limite_vagas' => 'integer', 'ativo' => 'boolean', 'ordem' => 'integer',
    ];

    public function atividade(): BelongsTo
    {
        return $this->belongsTo(Atividade::class);
    }

    public function inscricoes(): HasMany
    {
        return $this->hasMany(InscricaoAtividade::class, 'sessao_atividade_id');
    }

    public function vagasRestantes(): ?int
    {
        if ($this->limite_vagas === null) return null;

        $usadas = isset($this->inscricoes_count) ? (int) $this->inscricoes_count : $this->inscricoes()->count();
        return max(0, $this->limite_vagas - $usadas);
    }

    public function rotuloPublico(): string
    {
        $periodo = $this->data_inicio?->format('d/m/Y H:i');
        if ($this->data_fim) $periodo .= ' a '.$this->data_fim->format('d/m/Y H:i');

        return $this->nome.($periodo ? ' — '.$periodo : '');
    }
}
