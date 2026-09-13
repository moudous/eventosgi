@extends('layouts.app')
@section('title','Templates de página')
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><h1 class="page-title">Templates de página</h1><p class="page-description mb-0">Modelos HTML usados nas páginas públicas dos eventos.</p></div>
    <a href="{{ route('eventos.index') }}" class="btn btn-outline-secondary">Voltar para eventos</a>
</div>
@if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif

@if($permissoes->permite('templates.importar'))
<div class="card content-card mb-4"><div class="card-header"><h2 class="h5 fw-bold mb-0"><i class="bi bi-file-earmark-zip me-2"></i>Importar template</h2></div><div class="card-body">
    <form method="POST" action="{{ route('templates.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
        @csrf
        <div class="col-md-8">
            <label class="form-label" for="pacote">Arquivo ZIP</label>
            <input class="form-control @error('pacote') is-invalid @enderror" type="file" id="pacote" name="pacote" accept=".zip" required>
        </div>
        <div class="col-md-4 d-grid"><button class="btn btn-primary"><i class="bi bi-upload me-1"></i>Importar</button></div>
    </form>
    <hr>
    <h3 class="h6 fw-bold">Como o pacote deve ser</h3>
    <pre class="bg-light border rounded p-3 mb-3 small">meu-template.zip
├── index.html          <span class="text-muted">obrigatório — o HTML da página</span>
├── template.json       <span class="text-muted">opcional — nome, descrição, versão e variáveis</span>
└── assets/
    ├── estilo.css
    └── script.js</pre>
    <h3 class="h6 fw-bold">O que o HTML pode usar</h3>
    <p class="text-muted small mb-2">O template <strong>não é PHP</strong>. Ele lê apenas os dados que o sistema entrega, com quatro construções:</p>
    <pre class="bg-light border rounded p-3 mb-3 small">&#123;&#123; evento.nome &#125;&#125;                          valor (sempre escapado)
&#123;&#123; asset('assets/estilo.css') &#125;&#125;            URL de um arquivo do próprio template
&#123;% for atividade in atividades %&#125; … &#123;% endfor %&#125;
&#123;% if convidados %&#125; … &#123;% else %&#125; … &#123;% endif %&#125;</pre>
    <h3 class="h6 fw-bold">Dados disponíveis</h3>
    <div class="row g-3 small text-muted">
        <div class="col-md-6"><code>evento</code> — id, nome, ativo, criado_em</div>
        <div class="col-md-6"><code>atividades</code> — id, nome, formato, modalidade, datas, categoria, shortcode e sessões (nome, datas, cota e vagas restantes)</div>
        <div class="col-md-6"><code>categorias</code> — id, nome</div>
        <div class="col-md-6"><code>convidados</code> — id, nome, titulacao, descricao, curriculo, local, email, redes_sociais</div>
        <div class="col-md-6"><code>eventos</code> — id, nome (todos os eventos ativos)</div>
        <div class="col-md-6"><code>loop</code> — dentro de um <code>for</code>: indice, primeiro, ultimo</div>
    </div>
    <p class="text-muted small mt-3 mb-0">As <strong>variáveis do template</strong> declaradas em <code>template.json</code> aparecem para preenchimento na página de cada evento e ficam disponíveis pelo próprio nome, por exemplo <code>&#123;&#123; chamada &#125;&#125;</code>.</p>
</div></div>
@endif

<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Templates importados</h2></div><div class="card-body p-0"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
@php($podeAgir = $permissoes->permite('templates.exportar') || $permissoes->permite('templates.excluir'))
<thead><tr><th>Nome</th><th>Descrição</th><th>Versão</th><th>Variáveis</th><th>Eventos</th><th>Importado em</th>@if($podeAgir)<th class="text-end">Ações</th>@endif</tr></thead>
<tbody>
@forelse($templates as $template)
<tr>
    <td><strong>{{ $template->nome }}</strong><br><code class="small text-muted">{{ $template->pasta }}</code></td>
    <td class="small">{{ $template->descricao ?: '—' }}</td>
    <td class="small">{{ $template->versao ?: '—' }}</td>
    <td class="small">@forelse($template->variaveis ?? [] as $variavel)<code>{{ $variavel['nome'] }}</code>@if(!$loop->last), @endif @empty<span class="text-muted">—</span>@endforelse</td>
    <td>{{ $template->eventos_count }}</td>
    <td class="small text-muted">{{ $template->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
    @if($podeAgir)
    <td class="text-end">
        @if($permissoes->permite('templates.exportar'))
        <a href="{{ route('templates.exportar', $template) }}" class="btn btn-sm btn-outline-dark" title="Exportar em ZIP"><i class="bi bi-download"></i></a>
        @endif
        {{-- Template em uso não é removido; o controller recusa mesmo assim. --}}
        @if($permissoes->permite('templates.excluir') && $template->eventos_count === 0)
        <form method="POST" action="{{ route('templates.destroy', $template) }}" class="m-0 d-inline" onsubmit="return confirm('Remover o template {{ $template->nome }}? Os arquivos serão apagados.')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger" title="Remover"><i class="bi bi-trash-fill"></i></button>
        </form>
        @elseif($permissoes->permite('templates.excluir'))<span class="text-muted small" title="Template em uso por evento">em uso</span>@endif
    </td>
    @endif
</tr>
@empty
<tr><td colspan="7" class="text-center text-muted py-4">Nenhum template importado.</td></tr>
@endforelse
</tbody></table>
</div></div></div>
@endsection
