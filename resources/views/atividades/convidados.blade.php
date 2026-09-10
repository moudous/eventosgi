@extends('layouts.app')
@section('title', 'Convidados / Palestrantes')
@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
<style>
.convidado-avatar{width:36px;height:36px;flex:0 0 36px;border-radius:50%;object-fit:cover;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1rem}
.convidado-identidade{display:flex;align-items:center;gap:.65rem;min-width:0;white-space:normal}
.convidado-combo .select2-container{min-width:0;flex:1}
</style>
@endpush
@section('content')
<div class="mb-4"><h1 class="page-title">Convidados / Palestrantes</h1><p class="page-description">{{ $atividade->nome }}</p></div>
@if($podeEditar)
<form method="POST" action="{{ route('atividades.convidados.update', $atividade) }}">
    @csrf @method('PUT')
@endif
    <div class="card content-card"><div class="card-body p-4">
        @if($podeEditar)
        <p>Selecione convidados já cadastrados. Use as setas para definir a ordem de apresentação e salve as alterações.</p>
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif
        <label for="convidado-selecao" class="form-label">Convidado / Palestrante</label>
        <div class="d-flex gap-2 mb-4 align-items-start convidado-combo"><select id="convidado-selecao" class="form-select"><option value="">Selecione um convidado...</option>@foreach($convidados as $convidado)<option value="{{ $convidado->id }}" data-nome="{{ $convidado->nome }}" data-foto="{{ $convidado->foto_nome ? route('convidados.foto', $convidado->foto_nome) : '' }}">{{ trim($convidado->nome.' '.$convidado->sobrenome) }} (#{{ $convidado->id }})</option>@endforeach</select><button type="button" id="adicionar-convidado" class="btn btn-outline-primary">Adicionar</button></div>
        @if($convidados->isEmpty())<p class="text-muted">Nenhum convidado cadastrado. Cadastre os convidados no menu Convidados.</p>@endif
        <input type="hidden" name="convidados" value="">
        <ol id="atividade-convidados" class="list-group list-group-numbered"></ol>
        <p id="convidados-vazio" class="text-muted">Nenhum convidado vinculado.</p>
        <p id="convidados-status" class="small mt-3" role="status" aria-live="polite"></p>
        @else
        <ol class="list-group list-group-numbered">
            @forelse($atividade->convidados as $convidado)
            <li class="list-group-item d-flex align-items-center gap-2">
                @if($convidado->foto_nome)
                <img class="convidado-avatar" src="{{ route('convidados.foto', $convidado->foto_nome) }}" alt="">
                @else
                <span class="convidado-avatar" style="background:{{ ['#175CD3','#6941C6','#027A48','#B54708','#C11574','#0E7090'][$convidado->id % 6] }}" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim($convidado->nome), 0, 1)) }}</span>
                @endif
                <span>{{ trim($convidado->nome.' '.$convidado->sobrenome) }}</span>
            </li>
            @empty
            <li class="list-group-item text-muted">Nenhum convidado vinculado.</li>
            @endforelse
        </ol>
        @endif
    </div></div>
    <div class="mt-4 d-flex justify-content-end gap-2"><a href="{{ route('atividades.index') }}" class="btn btn-outline-secondary">Voltar</a>@if($podeEditar)<button type="submit" class="btn btn-primary">Salvar</button>@endif</div>
@if($podeEditar)</form>@endif
@endsection
@if($podeEditar)
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>window.atividadeConvidadosSelecionados = @json(old('convidados', $selecionados));</script>
<script src="{{ asset('atividade-convidados.js') }}?v={{ filemtime(public_path('atividade-convidados.js')) }}" defer></script>
@endpush

@endif
