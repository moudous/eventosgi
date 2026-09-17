<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PixCobranca extends Model
{
    protected $table = 'pix_cobrancas';
    protected $fillable = ['inscricao_atividade_id', 'campo', 'ambiente', 'txid', 'valor', 'status', 'pagador_nome', 'pagador_documento', 'end_to_end_id', 'pix_copia_cola', 'location', 'resposta_api', 'pago_em'];
    protected $casts = ['valor' => 'decimal:2', 'resposta_api' => 'array', 'pago_em' => 'datetime'];

    public function pagamentoConfirmado(): bool
    {
        return trim(mb_strtoupper((string) $this->status)) === 'CONCLUIDA'
            || $this->pago_em !== null;
    }

    public function removidaSemPagamento(): bool
    {
        return in_array(trim(mb_strtoupper((string) $this->status)), [
            'REMOVIDA_PELO_USUARIO_RECEBEDOR',
            'REMOVIDA_PELO_PSP',
        ], true) && ! $this->pagamentoConfirmado();
    }

    public function scopeConfirmadas(Builder $query): Builder
    {
        return $query->where(function (Builder $confirmada): void {
            $confirmada->whereRaw('UPPER(TRIM(status)) = ?', ['CONCLUIDA'])
                ->orWhereNotNull('pago_em');
        });
    }

    public function inscricao(): BelongsTo
    {
        return $this->belongsTo(InscricaoAtividade::class, 'inscricao_atividade_id')->withoutGlobalScope('ativas');
    }
}
