<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InscricaoSubmissaoTrabalho extends Model
{
    use SoftDeletes;

    protected $table = 'inscritos_submissao_trabalhos';

    protected $fillable = [
        'inscrito_submissao_id', 'titulo_trabalho', 'conteudo', 'tem_apoio_financeiro', 'apoiador',
        'apresentacao', 'aprovacao_comite_etica', 'protocolo_comite_etica', 'status', 'nota', 'situacao',
    ];

    protected $casts = [
        'inscrito_submissao_id' => 'integer',
        'tem_apoio_financeiro' => 'boolean',
        'aprovacao_comite_etica' => 'boolean',
        'nota' => 'decimal:2',
    ];

    public function inscricao(): BelongsTo
    {
        return $this->belongsTo(InscricaoSubmissao::class, 'inscrito_submissao_id');
    }

    public function autores(): HasMany
    {
        return $this->hasMany(SubmissaoAutor::class, 'inscrito_submissao_trabalho_id')->orderBy('ordem');
    }

    public function avaliada(): bool
    {
        return $this->status === 'avaliado';
    }
}
