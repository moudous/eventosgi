<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Atividade extends Model
{
    use SoftDeletes;

    protected $fillable = ['tipo', 'nome', 'palestrante', 'ativo', 'criado_por', 'evento_id', 'categoria_id', 'modalidade', 'data_inicio', 'data_fim', 'formulario', 'personalizacao'];
    protected $casts = [
        'ativo' => 'boolean', 'criado_por' => 'integer', 'evento_id' => 'integer', 'categoria_id' => 'integer',
        'data_inicio' => 'datetime', 'data_fim' => 'datetime', 'deleted_at' => 'datetime', 'formulario' => 'array',
        'personalizacao' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Atividade $atividade): void {
            $atividade->hash_publica = bin2hex(random_bytes(32));
        });
    }

    public const MENSAGEM_VAGAS_ESGOTADAS = 'Todas as vagas para esta atividade foram preenchidas. Agradecemos seu interesse e esperamos você nas próximas oportunidades!';

    public const MENSAGEM_JA_INSCRITO = 'Você já está inscrito nesta atividade. Cada participante pode se inscrever uma única vez.';

    public const MENSAGEM_IDENTIFICACAO = 'Informe seu e-mail e sua senha para entrar. Caso ainda não possua uma senha, solicite um código temporário por e-mail.';

    public function estiloImagem(): array
    {
        return array_replace([
            'imagem' => null,
            'posicao' => 'esquerda',
            'borda' => false,
            'cor_borda' => '#ffffff',
            'alterar_cor_fundo_pagina' => false,
            'cor_fundo_pagina' => Evento::COR_FUNDO_PAGINA_ATIVIDADE,
        ], $this->personalizacao ?? []);
    }

    public function corFundoPagina(): string
    {
        $estilo = $this->estiloImagem();
        $cor = (string) $estilo['cor_fundo_pagina'];

        if (! empty($estilo['alterar_cor_fundo_pagina']) && preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
            return strtolower($cor);
        }

        return $this->evento?->corFundoPagina('atividade') ?? Evento::COR_FUNDO_PAGINA_ATIVIDADE;
    }

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

    public function mensagemJaInscrito(): string
    {
        return trim($this->formulario['mensagem_ja_inscrito'] ?? '') ?: self::MENSAGEM_JA_INSCRITO;
    }

    /** Texto de apoio da etapa em que o visitante confirma o e-mail. */
    public function mensagemIdentificacao(): string
    {
        return trim($this->formulario['mensagem_identificacao'] ?? '') ?: self::MENSAGEM_IDENTIFICACAO;
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(Evento::class)->withTrashed();
    }

    public function convidados(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Convidado::class, 'atividade_convidado')->withPivot('ordem')->orderByPivot('ordem');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'criado_por');
    }
}
