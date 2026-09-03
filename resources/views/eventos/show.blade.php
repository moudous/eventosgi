@extends('layouts.app')
@section('title', 'Visualizar evento')
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><h1 class="page-title">Visualizar evento</h1><p class="page-description mb-0">Dados completos do evento.</p></div>
    <div class="d-flex gap-2">
        <a href="{{ route('eventos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Voltar</a>
        @if(app(\App\Services\GiPermissionService::class)->permite('eventos.editar'))<a href="{{ route('eventos.edit', $evento) }}" class="btn btn-primary"><i class="bi bi-pencil-fill me-2"></i>Editar</a>@endif
    </div>
</div>
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados do evento</h2></div><div class="card-body p-4"><div class="row g-4">
    <div class="col-12 col-md-3"><div class="small fw-bold text-secondary mb-1">ID</div>{{ $evento->id }}</div>
    <div class="col-12 col-md-9"><div class="small fw-bold text-secondary mb-1">Nome</div>{{ $evento->nome }}</div>
    <div class="col-12 col-md-4"><div class="small fw-bold text-secondary mb-1">Status</div>@include('eventos.partials.status')</div>
    <div class="col-12 col-md-4"><div class="small fw-bold text-secondary mb-1">Criado em</div>{{ $evento->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
    <div class="col-12 col-md-4"><div class="small fw-bold text-secondary mb-1">Alterado em</div>{{ $evento->updated_at?->format('d/m/Y H:i') ?? '—' }}</div>
</div></div></div>
@endsection
