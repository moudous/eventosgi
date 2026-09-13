<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Atividade extends Model
{
    use SoftDeletes;

    protected $fillable = ['tipo', 'formato', 'nome', 'palestrante', 'ativo', 'criado_por', 'evento_id', 'categoria_id', 'modalidade', 'data_inicio', 'data_fim', 'formulario', 'personalizacao', 'url'];
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

    public const MENSAGEM_IDENTIFICACAO = 'Informe seu e-mail e sua senha para entrar. Caso ainda não possua uma senha, solicite uma senha temporária por e-mail.';

    private const MENSAGENS_IDENTIFICACAO_ANTIGAS = [
        'Informe seu e-mail e sua senha para entrar. Caso ainda não possua uma senha, solicite um código temporário por e-mail.',
        'Informe o seu e-mail e confirme o código que enviaremos para ele. Assim conseguimos localizar o seu cadastro e emitir o certificado no nome certo.',
    ];

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

    public function urlPublica(): string
    {
        if ($this->url) {
            return route('inscricoes.publica.amigavel', ['atividade' => $this->url]);
        }

        return route('inscricoes.publica', ['atividade' => $this->hash_publica]);
    }

    public function inscricoes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InscricaoAtividade::class);
    }

    public function sessoes(): HasMany
    {
        return $this->hasMany(SessaoAtividade::class)->orderBy('ordem')->orderBy('data_inicio')->orderBy('id');
    }

    public function sessoesAtivas(): HasMany
    {
        return $this->sessoes()->where('ativo', true);
    }

    public function comSessoes(): bool
    {
        return $this->formato === 'com_sessoes';
    }

    public function temInscricoes(): bool
    {
        return $this->inscricoes()->exists();
    }

    public function vagasEsgotadas(): bool
    {
        if ($this->comSessoes()) {
            $sessoes = $this->sessoesAtivas()->withCount('inscricoes')->get();
            if ($sessoes->isEmpty()) return true;
            if ($sessoes->every(fn (SessaoAtividade $sessao) => $sessao->limite_vagas !== null && $sessao->vagasRestantes() < 1)) return true;
        }

        if (empty($this->formulario['limitar_inscricoes'])) return false;

        $config = app(\App\Services\DistribuicaoVagasService::class)->recalcular($this, salvar: false);

        return (int) ($config['distribuicao_vagas']['total']['restantes'] ?? 0) < 1;
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
        $mensagem = trim($this->formulario['mensagem_identificacao'] ?? '');

        return $mensagem === '' || in_array($mensagem, self::MENSAGENS_IDENTIFICACAO_ANTIGAS, true)
            ? self::MENSAGEM_IDENTIFICACAO
            : $mensagem;
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
