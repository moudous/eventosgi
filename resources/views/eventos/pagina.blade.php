@extends('layouts.app')
@section('title', 'Página do evento')
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><h1 class="page-title">Página do evento</h1><p class="page-description mb-0">{{ $evento->nome }}</p></div>
    <div class="d-flex gap-2">
        <a href="{{ route('eventos.index') }}" class="btn btn-outline-secondary">Voltar</a>
        @if(app(\App\Services\GiPermissionService::class)->permite('eventos.pagina.visualizar'))
            <a href="{{ route('eventos.pagina.visualizar', $evento) }}" target="_blank" rel="noopener" class="btn btn-outline-info"><i class="bi bi-box-arrow-up-right me-1"></i>Visualizar página</a>
        @endif
        @if($evento->templatePagina && app(\App\Services\GiPermissionService::class)->permite('templates.exportar'))
            <a href="{{ route('templates.exportar', $evento->templatePagina) }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-zip me-1"></i>Exportar template ZIP</a>
        @endif
        @if(app(\App\Services\GiPermissionService::class)->permite('templates.listar'))
            <a href="{{ route('templates.index') }}" class="btn btn-outline-dark"><i class="bi bi-collection me-1"></i>Templates</a>
        @endif
    </div>
</div>

@if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ route('eventos.pagina.salvar', $evento) }}">@csrf @method('PUT')
<div class="card content-card mb-4"><div class="card-header"><h2 class="h5 fw-bold mb-0">Modelo da página</h2></div><div class="card-body p-4">
    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <label class="form-label fw-semibold" for="template_pagina_id">Template</label>
            <select class="form-select @error('template_pagina_id') is-invalid @enderror" id="template_pagina_id" name="template_pagina_id" onchange="this.form.submit()">
                <option value="">Página Padrão do Sistema (Não utilizar template)</option>
                @foreach($templates as $template)
                    <option value="{{ $template->id }}" @selected((int) old('template_pagina_id', $evento->template_pagina_id) === $template->id)>{{ $template->nome }}@if($template->versao) (v{{ $template->versao }})@endif</option>
                @endforeach
            </select>
            @error('template_pagina_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Trocar o template salva a escolha e recarrega as variáveis que ele declara.</div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="small fw-bold text-secondary">Arquivos do template</div>
            @forelse($arquivos as $arquivo)<div><code class="small">{{ $arquivo }}</code></div>@empty<div class="text-muted">—</div>@endforelse
        </div>
    </div>
</div></div>

@if($variaveis !== [])
<div class="card content-card mb-4"><div class="card-header"><h2 class="h5 fw-bold mb-0">Variáveis do template</h2></div><div class="card-body p-4">
    <p class="text-muted">Valores que este template pede. Dentro do HTML eles aparecem pelo próprio nome, por exemplo <code>&#123;&#123; {{ $variaveis[0]['nome'] }} &#125;&#125;</code>.</p>
    <div class="row g-4">
        @foreach($variaveis as $variavel)
        <div class="col-12 col-md-6">
            <label class="form-label fw-semibold" for="var-{{ $variavel['nome'] }}">{{ $variavel['rotulo'] }}</label>
            @if(($variavel['tipo'] ?? 'text') === 'textarea')
                <textarea class="form-control" id="var-{{ $variavel['nome'] }}" name="variaveis[{{ $variavel['nome'] }}]" rows="5" maxlength="2000">{{ old('variaveis.'.$variavel['nome'], $valores[$variavel['nome']] ?? $variavel['padrao']) }}</textarea>
            @else
                <input class="form-control" id="var-{{ $variavel['nome'] }}" name="variaveis[{{ $variavel['nome'] }}]" type="{{ in_array(($variavel['tipo'] ?? 'text'), ['color','url'], true) ? $variavel['tipo'] : 'text' }}" maxlength="2000" value="{{ old('variaveis.'.$variavel['nome'], $valores[$variavel['nome']] ?? $variavel['padrao']) }}">
            @endif
            <div class="form-text"><code>&#123;&#123; {{ $variavel['nome'] }} &#125;&#125;</code></div>
        </div>
        @endforeach
    </div>
</div></div>
@elseif($evento->template_pagina_id)
<div class="alert alert-light border">Este template não declara variáveis.</div>
@endif

@if(!$evento->template_pagina_id)
    @include('eventos.partials.pagina-padrao')
@endif

<div class="d-flex justify-content-end gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Salvar</button></div>
</form>
@endsection
