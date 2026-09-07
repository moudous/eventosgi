@extends('layouts.app')
@section('title', 'Visualizar categoria')
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><h1 class="page-title">Visualizar categoria</h1><p class="page-description mb-0">Dados completos da categoria.</p></div>
    <div class="d-flex gap-2">
        <a href="{{ route('categorias.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Voltar</a>
        @if(app(\App\Services\GiPermissionService::class)->permite('categorias.editar'))<a href="{{ route('categorias.edit', $categoria) }}" class="btn btn-primary"><i class="bi bi-pencil-fill me-2"></i>Editar</a>@endif
    </div>
</div>
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados da categoria</h2></div><div class="card-body p-4"><div class="row g-4">
    <div class="col-12 col-md-3"><div class="small fw-bold text-secondary mb-1">ID</div>{{ $categoria->id }}</div>
    <div class="col-12 col-md-9"><div class="small fw-bold text-secondary mb-1">Nome</div>{{ $categoria->nome }}</div>
    <div class="col-12 col-md-3"><div class="small fw-bold text-secondary mb-1">Status</div>@include('categorias.partials.status')</div>
    <div class="col-12 col-md-3"><div class="small fw-bold text-secondary mb-1">Atividades vinculadas</div>{{ $categoria->atividades_count }}</div>
    <div class="col-12 col-md-3"><div class="small fw-bold text-secondary mb-1">Criado em</div>{{ $categoria->created_at?->format('d/m/Y H:i') ?? '—' }}</div>
    <div class="col-12 col-md-3"><div class="small fw-bold text-secondary mb-1">Alterado em</div>{{ $categoria->updated_at?->format('d/m/Y H:i') ?? '—' }}</div>
    @if($categoria->atividades_count > 0)
    <div class="col-12"><div class="alert alert-light border mb-0"><i class="bi bi-lock me-1"></i>Categoria em uso por atividades: não pode ser excluída nem desativada.</div></div>
    @endif
</div></div></div>
@endsection
