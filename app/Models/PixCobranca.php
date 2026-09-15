<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PixCobranca extends Model
{
    protected $table = 'pix_cobrancas';
    protected $fillable = ['inscricao_atividade_id', 'campo', 'txid', 'valor', 'status', 'pagador_nome', 'pagador_documento', 'end_to_end_id', 'pix_copia_cola', 'location', 'resposta_api', 'pago_em'];
    protected $casts = ['valor' => 'decimal:2', 'resposta_api' => 'array', 'pago_em' => 'datetime'];

    public function inscricao(): BelongsTo
    {
        return $this->belongsTo(InscricaoAtividade::class, 'inscricao_atividade_id');
    }
}
