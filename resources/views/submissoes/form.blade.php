@extends('layouts.app')
@section('title', $submissao->exists ? 'Editar submissão' : 'Adicionar submissão')
@push('styles')<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet"><style>#modeloEditor.ql-container{height:420px}.modelo-editor-wrapper{margin-bottom:1rem}</style>@endpush
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><h1 class="page-title">{{ $submissao->exists ? 'Editar submissão' : 'Adicionar submissão' }}</h1><p class="page-description mb-0">Configure o período e o modelo entregue aos autores.</p></div>
    <div class="d-flex flex-wrap gap-2"><a href="{{ route('submissoes.index') }}" class="btn btn-outline-secondary">Voltar</a>@if($submissao->exists)<a href="{{ route('submissoes.publicas.formulario', $submissao) }}" target="_blank" rel="noopener" class="btn btn-outline-dark"><i class="bi bi-box-arrow-up-right me-1"></i>Formulário público</a>@endif</div>
</div>
@if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif
<form id="submissaoForm" method="POST" action="{{ $submissao->exists ? route('submissoes.update',$submissao) : route('submissoes.store') }}">@csrf @if($submissao->exists)@method('PUT')@endif
<div class="card content-card mb-4"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados da submissão</h2></div><div class="card-body p-4"><div class="row g-4">
    <div class="col-12 col-lg-6"><label class="form-label fw-semibold" for="evento_id">Evento *</label><select class="form-select @error('evento_id') is-invalid @enderror" id="evento_id" name="evento_id" required><option value="">Selecione</option>@foreach($eventos as $evento)<option value="{{ $evento->id }}" @selected((int)old('evento_id',$submissao->evento_id)===$evento->id)>{{ $evento->nome }}</option>@endforeach</select>@error('evento_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-12 col-lg-6"><label class="form-label fw-semibold" for="titulo">Descrição *</label><input class="form-control @error('titulo') is-invalid @enderror" id="titulo" name="titulo" maxlength="255" required value="{{ old('titulo',$submissao->titulo) }}">@error('titulo')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-12 col-md-5"><label class="form-label fw-semibold" for="data_inicio">Data de início *</label><input type="datetime-local" class="form-control @error('data_inicio') is-invalid @enderror" id="data_inicio" name="data_inicio" required value="{{ old('data_inicio',$submissao->data_inicio?->format('Y-m-d\TH:i')) }}">@error('data_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-12 col-md-5"><label class="form-label fw-semibold" for="data_fim">Data de fim *</label><input type="datetime-local" class="form-control @error('data_fim') is-invalid @enderror" id="data_fim" name="data_fim" required value="{{ old('data_fim',$submissao->data_fim?->format('Y-m-d\TH:i')) }}">@error('data_fim')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-12 col-md-2"><label class="form-label fw-semibold" for="ativo">Status *</label><select class="form-select" id="ativo" name="ativo" required><option value="1" @selected((int)old('ativo',$submissao->exists?(int)$submissao->ativo:1)===1)>Ativo</option><option value="0" @selected((int)old('ativo',$submissao->exists?(int)$submissao->ativo:1)===0)>Inativo</option></select></div>
    <div class="col-12 col-md-4"><label class="form-label fw-semibold" for="qtde_resumo">Quantidade máxima de caracteres do resumo *</label><input type="number" class="form-control @error('qtde_resumo') is-invalid @enderror" id="qtde_resumo" name="qtde_resumo" min="1" max="100000" step="1" required value="{{ old('qtde_resumo', $submissao->qtde_resumo ?? 1600) }}">@error('qtde_resumo')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="form-text">O valor inicial é 1600 caracteres.</div></div>
    <div class="col-12 col-md-4"><label class="form-label fw-semibold" for="qtde_autores">Quantidade máxima de autores *</label><input type="number" class="form-control @error('qtde_autores') is-invalid @enderror" id="qtde_autores" name="qtde_autores" min="1" max="100" step="1" required value="{{ old('qtde_autores', $submissao->qtde_autores ?? 8) }}">@error('qtde_autores')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="form-text">Inclui o primeiro autor. O valor inicial é 8.</div></div>
    <div class="col-12 modelo-editor-wrapper"><label class="form-label fw-semibold">Modelo de trabalho</label><input type="hidden" id="modelo_trabalho" name="modelo_trabalho"><div id="modeloEditor" class="bg-white">{!! old('modelo_trabalho',$submissao->modelo_trabalho) !!}</div><div class="form-text">O modelo ocupará toda a largura do formulário público e será carregado inicialmente para cada novo trabalho.</div></div>
</div></div></div>
@php($estiloPagina = old('personalizacao', $submissao->estiloPagina()))
@php($alterarFundoPagina = (bool) ($estiloPagina['alterar_cor_fundo_pagina'] ?? false))
@php($fundoHerdado = $submissao->evento?->corFundoPagina('submissao') ?? \App\Models\Evento::COR_FUNDO_PAGINA_SUBMISSAO)
<div class="card content-card mb-4"><div class="card-header"><h2 class="h5 fw-bold mb-0">Personalizar</h2></div><div class="card-body p-4">
    <input type="hidden" name="personalizacao[alterar_cor_fundo_pagina]" value="0">
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="alterar_cor_fundo_pagina" name="personalizacao[alterar_cor_fundo_pagina]" value="1" @checked($alterarFundoPagina) data-alterar-fundo-pagina>
        <label class="form-check-label fw-semibold" for="alterar_cor_fundo_pagina">Alterar cor de fundo da página</label>
    </div>
    <div class="mt-3" data-cor-fundo-pagina-container @if(!$alterarFundoPagina) hidden @endif>
        <label class="form-label" for="cor_fundo_pagina_submissao">Cor de fundo da página</label>
        <div class="input-group" style="max-width:360px">
            <input type="color" class="form-control form-control-color" id="cor_fundo_pagina_submissao" value="{{ $estiloPagina['cor_fundo_pagina'] }}" data-fundo-color-picker aria-label="Selecionar cor de fundo da página">
            <input type="text" class="form-control font-monospace @error('personalizacao.cor_fundo_pagina') is-invalid @enderror" name="personalizacao[cor_fundo_pagina]" value="{{ $estiloPagina['cor_fundo_pagina'] }}" maxlength="7" pattern="#[0-9A-Fa-f]{6}" data-fundo-color-text aria-label="Código da cor de fundo da página">
        </div>
        @error('personalizacao.cor_fundo_pagina')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>
    <div class="form-text">Desmarcado: herda a cor do evento ({{ $fundoHerdado }}).</div>
</div></div>
<div class="d-flex justify-content-end gap-2"><a href="{{ route('submissoes.index') }}" class="btn btn-outline-secondary">Cancelar</a><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button></div>
</form>
@endsection
@push('scripts')<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script><script src="{{ asset('cor-fundo-pagina.js') }}?v={{ filemtime(public_path('cor-fundo-pagina.js')) }}" defer></script><script>document.addEventListener('DOMContentLoaded',()=>{const quill=new Quill('#modeloEditor',{theme:'snow',modules:{toolbar:[[{header:[1,2,3,4,false]}],['bold','italic','underline','strike'],[{align:[]}],[{list:'ordered'},{list:'bullet'}],['blockquote'],['clean']]}});document.getElementById('submissaoForm').addEventListener('submit',()=>document.getElementById('modelo_trabalho').value=quill.root.innerHTML)});</script>@endpush
