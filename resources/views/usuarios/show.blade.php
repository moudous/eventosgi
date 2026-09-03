@extends('layouts.app')
@section('title', 'Visualizar usuário')
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><h1 class="page-title">Visualizar usuário</h1><p class="page-description mb-0">Dados do usuário sincronizado com o GI.</p></div>
    <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Voltar</a>
</div>
<div class="card content-card">
    <div class="card-header"><h2 class="h5 fw-bold mb-0">Dados do usuário</h2></div>
    <div class="card-body p-4"><div class="row g-4">
        <div class="col-12 col-md-4"><div class="small fw-bold text-secondary mb-1">ID do usuário no GI</div>{{ $usuario->id }}</div>
        <div class="col-12 col-md-8"><div class="small fw-bold text-secondary mb-1">Nome</div>{{ $usuario->nome }}</div>
        <div class="col-12 col-md-6"><div class="small fw-bold text-secondary mb-1">E-mail</div>{{ $usuario->email ?: '—' }}</div>
        <div class="col-12 col-md-6"><div class="small fw-bold text-secondary mb-1">Perfil</div>{{ $usuario->perfil ?: '—' }}</div>
        <div class="col-12 col-md-6"><div class="small fw-bold text-secondary mb-1">Status</div><span class="badge {{ $usuario->ativo ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $usuario->ativo ? 'Ativo' : 'Inativo' }}</span></div>
        <div class="col-12 col-md-6"><div class="small fw-bold text-secondary mb-1">Último acesso</div>{{ $usuario->ultimo_acesso?->format('d/m/Y H:i') ?: 'Nunca acessou' }}</div>
    </div></div>
</div>
@endsection
