@extends('layouts.app')
@section('title', 'Recebimento PIX #'.$recebimento->id)
@section('content')
@php
    $inscricao = $recebimento->inscricao;
    $atividade = $inscricao?->atividade;
    $participante = $inscricao?->participante;
@endphp
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3"><div><h1 class="page-title">Recebimento PIX #{{ $recebimento->id }}</h1><p class="page-description mb-0">{{ $atividade?->nome ?? 'Atividade não encontrada' }}</p></div><a href="{{ url()->previous() }}" class="btn btn-outline-secondary">Voltar</a></div>
<div class="row g-4 mb-4">
    <div class="col-md-4"><div class="card content-card h-100"><div class="card-body"><div class="text-muted small">Valor recebido</div><div class="display-6 fw-bold text-success">R$ {{ number_format((float)$recebimento->valor,2,',','.') }}</div><span class="badge text-bg-success mt-2">Confirmado</span></div></div></div>
    <div class="col-md-8"><div class="card content-card h-100"><div class="card-header"><h2 class="h5 fw-bold mb-0">Identificação</h2></div><div class="card-body"><dl class="row mb-0"><dt class="col-sm-4">Data e hora</dt><dd class="col-sm-8">{{ $recebimento->pago_em?->format('d/m/Y H:i:s') ?? '—' }}</dd><dt class="col-sm-4">Pagador</dt><dd class="col-sm-8">{{ $recebimento->pagador_nome ?: $participante?->nome ?: 'Não informado' }}</dd><dt class="col-sm-4">CPF/CNPJ</dt><dd class="col-sm-8">{{ \App\Services\RecebimentosPixService::formatarDocumento($recebimento->pagador_documento ?: $participante?->cpf) ?: 'Não informado' }}</dd><dt class="col-sm-4">E-mail da inscrição</dt><dd class="col-sm-8">{{ $inscricao?->participante_email ?? '—' }}</dd><dt class="col-sm-4">Evento</dt><dd class="col-sm-8">{{ $atividade?->evento?->nome ?? '—' }}</dd><dt class="col-sm-4">Atividade</dt><dd class="col-sm-8 mb-0">{{ $atividade?->nome ?? '—' }}</dd></dl></div></div></div>
</div>
<div class="card content-card mb-4"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados da transação</h2></div><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">TXID</dt><dd class="col-sm-9"><code class="text-break">{{ $recebimento->txid }}</code></dd><dt class="col-sm-3">EndToEndId</dt><dd class="col-sm-9"><code class="text-break">{{ $recebimento->end_to_end_id ?: 'Não informado' }}</code></dd><dt class="col-sm-3">Campo do formulário</dt><dd class="col-sm-9">{{ $recebimento->campo }}</dd><dt class="col-sm-3">Status Sicoob</dt><dd class="col-sm-9 mb-0">{{ $recebimento->status }}</dd></dl></div></div>
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Retorno completo da API Sicoob</h2></div><div class="card-body"><pre class="bg-light border rounded p-3 mb-0 text-wrap" style="white-space:pre-wrap">{{ json_encode($recebimento->resposta_api, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre></div></div>
@endsection
