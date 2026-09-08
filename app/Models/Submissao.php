<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Submissao extends Model
{
    protected $table = 'submissoes';

    protected $fillable = ['evento_id', 'titulo', 'data_inicio', 'data_fim', 'ativo', 'modelo_trabalho', 'qtde_resumo', 'qtde_autores'];

    protected $attributes = [
        'qtde_resumo' => 1600,
        'qtde_autores' => 8,
    ];

    protected $casts = [
        'evento_id' => 'integer',
        'data_inicio' => 'datetime',
        'data_fim' => 'datetime',
        'ativo' => 'boolean',
        'qtde_resumo' => 'integer',
        'qtde_autores' => 'integer',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class)->withTrashed();
    }

    public function inscricoes(): HasMany
    {
        return $this->hasMany(InscricaoSubmissao::class);
    }

    public function trabalhos(): HasManyThrough
    {
        return $this->hasManyThrough(
            InscricaoSubmissaoTrabalho::class,
            InscricaoSubmissao::class,
            'submissao_id',
            'inscrito_submissao_id',
        );
    }

    public function aindaNaoAbriu(): bool
    {
        return now()->lt($this->data_inicio);
    }

    public function encerrada(): bool
    {
        return now()->gt($this->data_fim);
    }

    public function aberta(): bool
    {
        return $this->ativo && (bool) $this->evento?->ativo
            && ! $this->aindaNaoAbriu() && ! $this->encerrada();
    }

    /** Trabalhos não avaliados passam à fila da comissão assim que o prazo termina. */
    public function atualizarStatusDoPrazo(): void
    {
        if ($this->encerrada()) {
            $this->trabalhos()->where('status', 'rascunho')->update(['status' => 'submetido']);
        }
    }
}
