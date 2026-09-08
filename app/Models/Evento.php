<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evento extends Model
{
    use SoftDeletes;

    protected $fillable = ['nome', 'ativo', 'template_pagina_id', 'pagina_variaveis', 'personalizacao'];

    protected $casts = [
        'ativo' => 'boolean',
        'deleted_at' => 'datetime',
        'template_pagina_id' => 'integer',
        'pagina_variaveis' => 'array',
        'personalizacao' => 'array',
    ];

    public function estiloFormulario(string $tipo): array
    {
        return array_replace([
            'tipo' => 'degrade',
            'degrade_inicio' => '#102a43',
            'degrade_fim' => '#176b87',
            'cor_solida' => '#102a43',
            'cor_fonte' => '#ffffff',
            'imagem' => null,
        ], $this->personalizacao[$tipo] ?? []);
    }

    public function fundoFormulario(string $tipo): string
    {
        $estilo = $this->estiloFormulario($tipo);
        return match ($estilo['tipo']) {
            'solida' => $estilo['cor_solida'],
            'imagem' => $estilo['imagem']
                ? 'url("'.route('eventos.personalizacao.imagem', ['arquivo' => $estilo['imagem']]).'") center / cover no-repeat'
                : $estilo['cor_solida'],
            default => "linear-gradient(135deg, {$estilo['degrade_inicio']}, {$estilo['degrade_fim']})",
        };
    }

    public function templatePagina(): BelongsTo
    {
        return $this->belongsTo(TemplatePagina::class, 'template_pagina_id');
    }

    public function submissoes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Submissao::class);
    }

    public function paginaPadrao(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PaginaPadraoEvento::class);
    }
}
