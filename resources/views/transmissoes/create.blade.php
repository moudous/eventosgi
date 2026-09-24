@extends('layouts.app')

@section('title', 'Criar transmissão')

@section('content')
<div class="mb-4">
    <h1 class="page-title">Criar transmissão</h1>
    <p class="page-description mb-0">Agende uma transmissão e associe-a a um evento.</p>
</div>

<form method="POST" action="{{ route('transmissoes.store') }}">
    @csrf
    <div class="card content-card">
        <div class="card-header"><h2 class="h5 fw-bold mb-0">Dados da transmissão</h2></div>
        <div class="card-body p-4">
            @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="evento_id">Evento *</label>
                    <select class="form-select" id="evento_id" name="evento_id" required>
                        <option value="">Selecione um evento</option>
                        @foreach($eventos as $evento)<option value="{{ $evento->id }}" @selected((int)old('evento_id') === $evento->id)>{{ $evento->nome }}</option>@endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="agendada_para">Data e hora *</label>
                    <input class="form-control" id="agendada_para" name="agendada_para" type="datetime-local" value="{{ old('agendada_para') }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="titulo">Título *</label>
                    <input class="form-control" id="titulo" name="titulo" maxlength="255" value="{{ old('titulo') }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="descricao">Descrição</label>
                    <textarea class="form-control" id="descricao" name="descricao" rows="4" maxlength="5000">{{ old('descricao') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label" for="miniatura_url">URL da miniatura</label>
                    <input class="form-control" id="miniatura_url" name="miniatura_url" type="url" maxlength="2048" placeholder="https://..." value="{{ old('miniatura_url') }}">
                    <div class="form-text">Nesta etapa a URL fica preparada para a miniatura do YouTube. O upload pela API será conectado na próxima etapa.</div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('transmissoes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                <button class="btn btn-primary"><i class="bi bi-calendar-plus me-1"></i>Agendar transmissão</button>
            </div>
        </div>
    </div>
</form>
@endsection