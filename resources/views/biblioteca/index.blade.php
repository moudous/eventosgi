@extends('layouts.app')
@section('title', 'Biblioteca de arquivos')
@push('styles')
<style>
.biblioteca-card{height:100%;transition:border-color .15s,box-shadow .15s}.biblioteca-card.selecionado{border-color:#0d6efd;box-shadow:0 0 0 3px rgba(13,110,253,.2)}.biblioteca-miniatura{aspect-ratio:4/3;background:#eef2f6;display:flex;align-items:center;justify-content:center;overflow:hidden}.biblioteca-miniatura img{width:100%;height:100%;object-fit:cover}.biblioteca-icone{font-size:3.5rem}.biblioteca-nome{overflow-wrap:anywhere}.biblioteca-tags{min-height:1.6rem}.biblioteca-tags .badge{font-weight:500}.crop-canvas{width:100%;max-height:58vh;background:#20252b;object-fit:contain;touch-action:none}.colar-imagem-area{min-height:86px;border:2px dashed #adb5bd;background:#f8f9fa;cursor:text;transition:border-color .15s,background-color .15s}.colar-imagem-area:focus{border-color:#0d6efd;background:#eef5ff;box-shadow:0 0 0 .25rem rgba(13,110,253,.15);outline:0}.colar-imagem-preview{width:72px;height:64px;object-fit:contain;background:#fff}
</style>
@endpush
@section('content')
@php($permissoes = app(\App\Services\GiPermissionService::class))
<div class="container-fluid py-4 px-3 px-lg-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div><h1 class="page-title">Biblioteca de arquivos</h1><p class="page-description mb-0">Imagens, documentos, planilhas e apresentações disponíveis por link público.</p></div>
        <div class="d-flex flex-wrap gap-2">
            @if($permissoes->permite('biblioteca.enviar'))
                <button class="btn btn-primary" type="button" id="abrirUploadBiblioteca" data-bs-toggle="modal" data-bs-target="#uploadBiblioteca"><i class="bi bi-cloud-arrow-up me-1"></i>Upload de arquivo</button>
            @endif
            @if($permissoes->permite('biblioteca.recortar'))
                <button class="btn btn-outline-secondary" type="button" id="recortarSelecionada" disabled><i class="bi bi-crop me-1"></i>Cortar imagem selecionada</button>
            @endif
        </div>
    </div>

    @if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif
    <div class="alert alert-danger d-none" id="erroClipboard" role="alert"></div>

    @if($permissoes->permite('biblioteca.enviar'))
    <div class="card content-card mb-4"><div class="card-body p-3">
        <div class="row g-3 align-items-stretch">
            <div class="col-12 col-lg">
                <div id="areaColarImagem" class="colar-imagem-area rounded-3 p-2 d-flex align-items-center gap-3" tabindex="0" role="button" aria-label="Área para colar arquivo copiado" aria-describedby="instrucaoColarImagem" data-extensoes="{{ \App\Http\Controllers\BibliotecaController::EXTENSOES }}">
                    <i class="bi bi-clipboard-plus fs-2 text-secondary" id="iconeColarImagem" aria-hidden="true"></i>
                    <img id="previewImagemColada" class="colar-imagem-preview rounded border d-none" alt="Prévia da imagem colada">
                    <div>
                        <div class="fw-semibold" id="statusColarImagem">Clique aqui e pressione Ctrl+V</div>
                        <div class="small text-secondary" id="instrucaoColarImagem">Cole imagens, PDF, Word, PowerPoint ou planilhas do Microsoft Office e LibreOffice.</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-auto d-grid">
                <button class="btn btn-success px-4" type="button" id="salvarImagemColada" disabled><i class="bi bi-floppy me-1"></i>Salvar arquivo colado</button>
            </div>
        </div>
    </div></div>
    @endif

    <form class="card content-card mb-4" method="GET" action="{{ route('biblioteca.index') }}">
        <div class="card-body"><div class="row g-3 align-items-end">
            <div class="col-12 col-lg-5"><label class="form-label" for="busca">Buscar por nome ou tag</label><input class="form-control" id="busca" name="busca" value="{{ $busca }}" placeholder="Digite um nome ou uma tag"></div>
            <div class="col-12 col-md-4 col-lg-3"><label class="form-label" for="tipo">Tipo de arquivo</label><select class="form-select" id="tipo" name="tipo"><option value="">Todos os tipos</option>@foreach(\App\Models\ArquivoBiblioteca::TIPOS as $valor => $rotulo)<option value="{{ $valor }}" @selected($tipo === $valor)>{{ $rotulo }}</option>@endforeach</select></div>
            <div class="col-12 col-md-4 col-lg-2"><label class="form-label" for="categoria">Categoria da imagem</label><select class="form-select" id="categoria" name="categoria" @disabled($tipo !== 'imagem')><option value="">Todas</option>@foreach(\App\Models\ArquivoBiblioteca::CATEGORIAS as $valor => $rotulo)<option value="{{ $valor }}" @selected($categoria === $valor)>{{ $rotulo }}</option>@endforeach</select></div>
            <div class="col-12 col-md-4 col-lg-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>Buscar</button><a class="btn btn-outline-secondary" href="{{ route('biblioteca.index') }}" title="Limpar filtros"><i class="bi bi-x-lg"></i></a></div>
        </div></div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-3"><p class="text-muted mb-0">{{ $arquivos->total() }} {{ $arquivos->total() === 1 ? 'arquivo encontrado' : 'arquivos encontrados' }}</p><span class="small text-muted">Página {{ $arquivos->currentPage() }} de {{ max(1, $arquivos->lastPage()) }}</span></div>
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-6 g-3">
        @forelse($arquivos as $arquivo)
        @php($url = route('biblioteca.abrir', ['arquivo' => $arquivo->arquivo]))
        <div class="col">
            <article class="card biblioteca-card" @if($arquivo->tipo === 'imagem') data-biblioteca-item data-url="{{ $url }}" data-nome="{{ $arquivo->nome }}" data-tags="{{ implode(', ', $arquivo->tags ?? []) }}" data-categoria="{{ $arquivo->categoria }}" @endif>
                <div class="biblioteca-miniatura card-img-top">
                    @if($arquivo->tipo === 'imagem')<img src="{{ $url }}" alt="Miniatura de {{ $arquivo->nome }}" loading="lazy">@else<i class="bi {{ $arquivo->icone() }} biblioteca-icone" aria-hidden="true"></i>@endif
                </div>
                <div class="card-body p-3">
                    <h2 class="h6 biblioteca-nome mb-2" title="{{ $arquivo->nome }}">{{ $arquivo->nome }}</h2>
                    <dl class="small mb-2">
                        <div class="d-flex justify-content-between gap-2"><dt>Tamanho</dt><dd class="mb-0 text-end">{{ $arquivo->tamanhoFormatado() }}</dd></div>
                        <div class="d-flex justify-content-between gap-2"><dt>Formato</dt><dd class="mb-0 text-uppercase">{{ $arquivo->formato }}</dd></div>
                        @if($arquivo->tipo === 'imagem')<div class="d-flex justify-content-between gap-2"><dt>Dimensões</dt><dd class="mb-0">{{ $arquivo->largura && $arquivo->altura ? $arquivo->largura.' × '.$arquivo->altura.' px' : 'Não informadas' }}</dd></div><div class="d-flex justify-content-between gap-2"><dt>Categoria</dt><dd class="mb-0 text-end">{{ \App\Models\ArquivoBiblioteca::CATEGORIAS[$arquivo->categoria] ?? '—' }}</dd></div>@endif
                    </dl>
                    <div class="biblioteca-tags d-flex flex-wrap gap-1">@foreach($arquivo->tags ?? [] as $tag)<span class="badge text-bg-light border">{{ $tag }}</span>@endforeach</div>
                </div>
                <div class="card-footer bg-white border-0 p-3 pt-0 d-grid gap-2">
                    @if($arquivo->tipo === 'imagem' && $permissoes->permite('biblioteca.recortar'))<button class="btn btn-sm btn-outline-primary" type="button" data-selecionar aria-pressed="false"><i class="bi bi-check2-square me-1"></i>Selecionar</button>@endif
                    @if($arquivo->tipo === 'imagem' && $permissoes->permite('imagem.formatos'))
                        @if($arquivo->formato !== 'svg')
                        <form method="POST" action="{{ route('biblioteca.formatos', $arquivo) }}" data-gerar-formato>
                            @csrf
                            <div class="dropdown d-grid">
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-images me-1"></i>Gerar formato</button>
                                <ul class="dropdown-menu">
                                    @foreach(\App\Services\BibliotecaImagemService::FORMATOS as $valor => $rotulo)
                                    <li><button class="dropdown-item" type="submit" name="formato" value="{{ $valor }}">{{ $rotulo }}</button></li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="form-text small">Salva uma cópia. Tamanhos em KB são aproximados. SVG em preto e branco é indicado para logos; GIFs geram imagem estática.</div>
                        </form>
                        @else
                        <span class="small text-secondary">Esta imagem já está em formato vetorial SVG.</span>
                        @endif
                    @endif
                    @if($permissoes->permite('biblioteca.excluir_permanentemente'))
                    <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#excluirBiblioteca" data-excluir-url="{{ route('biblioteca.excluir-permanentemente', $arquivo) }}" data-excluir-nome="{{ $arquivo->nome }}"><i class="bi bi-trash me-1"></i>Excluir permanentemente</button>
                    @endif
                    <a class="btn btn-sm btn-outline-secondary" href="{{ $url }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Abrir link público</a>
                </div>
            </article>
        </div>
        @empty
        <div class="col-12 w-100"><div class="text-center border rounded-3 bg-light py-5"><i class="bi bi-folder2-open fs-1 text-muted"></i><p class="mt-3 mb-0 text-muted">Nenhum arquivo encontrado.</p></div></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $arquivos->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
</div>

@if($permissoes->permite('biblioteca.excluir_permanentemente'))
<div class="modal fade" id="excluirBiblioteca" tabindex="-1" aria-labelledby="excluirBibliotecaTitulo" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <form method="POST" id="formExcluirBiblioteca">@csrf @method('DELETE')
        <div class="modal-header"><h2 class="modal-title fs-5" id="excluirBibliotecaTitulo">Excluir permanentemente</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body">
            <p>Excluir <strong id="nomeExcluirBiblioteca"></strong> da biblioteca e do disco?</p>
            <p>Esta ação não pode ser desfeita. Páginas que usam o link deste arquivo deixarão de exibi-lo.</p>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="confirmar_exclusao" value="1" id="confirmarExclusaoBiblioteca" required><label class="form-check-label" for="confirmarExclusaoBiblioteca">Confirmo a exclusão permanente.</label></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger" type="submit">Excluir permanentemente</button></div>
    </form>
</div></div></div>
@endif

@if($permissoes->permite('biblioteca.enviar'))
<div class="modal fade" id="uploadBiblioteca" tabindex="-1" aria-labelledby="uploadTitulo" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <form method="POST" action="{{ route('biblioteca.store') }}" enctype="multipart/form-data" id="formUploadBiblioteca">@csrf
        <div class="modal-header"><h2 class="modal-title fs-5" id="uploadTitulo">Adicionar à biblioteca</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label" for="arquivoUpload">Arquivo</label><input class="form-control" type="file" id="arquivoUpload" name="arquivo" required accept=".{{ str_replace(',', ',.', \App\Http\Controllers\BibliotecaController::EXTENSOES) }}"><div class="form-text">Imagens, PDF, Word, PowerPoint, planilhas e formatos equivalentes do LibreOffice. Máximo de 25 MB.</div></div>
            <div class="mb-3"><label class="form-label" for="nomeUpload">Nome</label><input class="form-control" id="nomeUpload" name="nome" maxlength="255" required value="{{ old('nome') }}"></div>
            <div class="mb-3"><label class="form-label" for="tagsUpload">Tags</label><input class="form-control" id="tagsUpload" name="tags" maxlength="500" value="{{ old('tags') }}" placeholder="evento, banner, odontologia"><div class="form-text">Até 10 tags, separadas por vírgula.</div></div>
            <div data-categoria-upload><label class="form-label" for="categoriaUpload">Categoria da imagem</label><select class="form-select" id="categoriaUpload" name="categoria"><option value="">Selecione...</option>@foreach(\App\Models\ArquivoBiblioteca::CATEGORIAS as $valor => $rotulo)<option value="{{ $valor }}" @selected(old('categoria') === $valor)>{{ $rotulo }}</option>@endforeach</select></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit"><i class="bi bi-cloud-arrow-up me-1"></i>Enviar arquivo</button></div>
    </form>
</div></div></div>
@endif

@if($permissoes->permite('biblioteca.recortar'))
<div class="modal fade" id="cropBiblioteca" tabindex="-1" aria-labelledby="cropTitulo" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <form method="POST" action="{{ route('biblioteca.recortar') }}" enctype="multipart/form-data" id="formCropBiblioteca">@csrf
        <div class="modal-header"><h2 class="modal-title fs-5" id="cropTitulo">Cortar imagem selecionada</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body">
            <canvas id="cropBibliotecaCanvas" class="crop-canvas rounded"></canvas>
            <div class="row g-3 mt-1">
                <div class="col-12 col-md-4"><label class="form-label" for="cropProporcao">Proporção</label><select class="form-select" id="cropProporcao"><option value="original">Original</option><option value="1">Quadrada (1:1)</option><option value="1.333333">4:3</option><option value="1.777778">16:9</option></select></div>
                <div class="col-12 col-md-8"><label class="form-label" for="cropZoom">Ampliação</label><input class="form-range" id="cropZoom" type="range" min="1" max="4" step="0.01" value="1"></div>
                <div class="col-6"><label class="form-label" for="cropX">Posição horizontal</label><input class="form-range" id="cropX" type="range" min="0" max="1" step="0.01" value="0.5"></div>
                <div class="col-6"><label class="form-label" for="cropY">Posição vertical</label><input class="form-range" id="cropY" type="range" min="0" max="1" step="0.01" value="0.5"></div>
                <div class="col-12 col-md-6"><label class="form-label" for="cropNome">Salvar com outro nome</label><input class="form-control" id="cropNome" name="nome" maxlength="255" required></div>
                <div class="col-12 col-md-6"><label class="form-label" for="cropCategoria">Categoria</label><select class="form-select" id="cropCategoria" name="categoria" required>@foreach(\App\Models\ArquivoBiblioteca::CATEGORIAS as $valor => $rotulo)<option value="{{ $valor }}">{{ $rotulo }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label" for="cropTags">Tags</label><input class="form-control" id="cropTags" name="tags" maxlength="500"><div class="form-text">Até 10 tags, separadas por vírgula.</div></div>
            </div>
            <input type="file" name="arquivo" id="cropArquivo" accept="image/png" class="visually-hidden" required>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="button" id="salvarRecorte"><i class="bi bi-crop me-1"></i>Salvar cópia recortada</button></div>
    </form>
</div></div></div>
@endif
@endsection
@push('scripts')<script src="{{ asset('biblioteca.js') }}?v={{ filemtime(public_path('biblioteca.js')) }}" defer></script>@endpush
