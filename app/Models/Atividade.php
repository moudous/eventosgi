<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Atividade extends Model
{
    use SoftDeletes;

    protected $fillable = ['nome', 'ativo', 'criado_por', 'evento_id', 'modalidade', 'data_inicio', 'data_fim', 'formulario'];
    protected $casts = [
        'ativo' => 'boolean', 'criado_por' => 'integer', 'evento_id' => 'integer',
        'data_inicio' => 'datetime', 'data_fim' => 'datetime', 'deleted_at' => 'datetime', 'formulario' => 'array',
    ];

    public const MENSAGEM_VAGAS_ESGOTADAS = 'Todas as vagas para esta atividade foram preenchidas. Agradecemos seu interesse e esperamos você nas próximas oportunidades!';

    public function inscricoes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InscricaoAtividade::class);
    }

    public function vagasEsgotadas(): bool
    {
        return !empty($this->formulario['limitar_inscricoes'])
            && $this->inscricoes()->count() >= (int) ($this->formulario['limite_inscricoes'] ?? 0);
    }

    public function mensagemVagasEsgotadas(): string
    {
        return trim($this->formulario['mensagem_vagas_esgotadas'] ?? '') ?: self::MENSAGEM_VAGAS_ESGOTADAS;
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class)->withTrashed();
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'criado_por');
    }
}
