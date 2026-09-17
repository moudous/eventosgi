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
        'inscrito_submissao_id', 'avaliador_id', 'titulo_trabalho', 'conteudo', 'categoria_trabalho', 'palavras_chave', 'tem_apoio_financeiro', 'apoiador',
        'apresentacao', 'aprovacao_comite_etica', 'protocolo_comite_etica', 'status', 'nota', 'situacao',
        'eposter_arquivo', 'eposter_nome_original', 'eposter_enviado_em', 'notificacao_resultado_versao',
    ];

    public const CATEGORIAS_TRABALHO = [
        'pesquisa_original' => 'Pesquisa Original',
        'relato_caso_clinico' => 'Relato de Caso Clínico',
        'revisao_literatura' => 'Revisão de Literatura',
        'projeto_pesquisa' => 'Projeto de Pesquisa',
    ];

    public function categoriaTrabalhoRotulo(): ?string
    {
        return self::CATEGORIAS_TRABALHO[$this->categoria_trabalho] ?? null;
    }

    protected $casts = [
        'inscrito_submissao_id' => 'integer',
        'avaliador_id' => 'integer',
        'tem_apoio_financeiro' => 'boolean',
        'aprovacao_comite_etica' => 'boolean',
        'nota' => 'decimal:2',
        'eposter_enviado_em' => 'datetime',
        'notificacao_resultado_versao' => 'integer',
    ];

    public function inscricao(): BelongsTo
    {
        return $this->belongsTo(InscricaoSubmissao::class, 'inscrito_submissao_id');
    }

    public function avaliador(): BelongsTo
    {
        return $this->belongsTo(Avaliador::class);
    }

    public function autores(): HasMany
    {
        return $this->hasMany(SubmissaoAutor::class, 'inscrito_submissao_trabalho_id')->orderBy('ordem');
    }

    public function notificacoesAutores(): HasMany
    {
        return $this->hasMany(SubmissaoAutorNotificacao::class, 'inscrito_submissao_trabalho_id');
    }

    public function historicos(): HasMany
    {
        return $this->hasMany(SubmissaoTrabalhoHistorico::class, 'inscrito_submissao_trabalho_id');
    }

    public function notificacoesResultado(): HasMany
    {
        return $this->hasMany(SubmissaoResultadoNotificacao::class, 'inscrito_submissao_trabalho_id');
    }

    public function avaliada(): bool
    {
        return $this->status === 'avaliado';
    }

    public function aprovada(): bool
    {
        return $this->avaliada() && $this->situacao === 'aprovado';
    }

    public function temEposter(): bool
    {
        return filled($this->eposter_arquivo);
    }
}
