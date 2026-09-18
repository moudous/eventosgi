@extends('layouts.app')
@section('title', 'Criador de template')
@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
<link href="{{ asset('template-editor.css') }}?v=1" rel="stylesheet">
<link href="{{ asset('template-build.css') }}?v=3" rel="stylesheet">
@endpush
@section('content')
<div id="templateBuild" data-base="{{ route('eventos.pagina.criador.index', $evento) }}" data-token="{{ csrf_token() }}" data-evento="{{ $evento->nome }}" data-tem-templates="{{ $temTemplates ? '1' : '0' }}" data-extensoes='@json($extensoes)'>
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
        <div><h1 class="page-title">Criador de template</h1><p class="page-description mb-1">{{ $evento->nome }}</p><span id="buildName" class="fw-semibold"></span> <span id="buildVersion" class="badge text-bg-light"></span></div>
        <div class="d-flex flex-wrap gap-2 align-items-start">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#buildOpenModal" data-mutate><i class="bi bi-folder2-open me-1"></i>Abrir template</button>
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#buildNewModal" data-mutate><i class="bi bi-plus-lg me-1"></i>Novo template</button>
            @if(app(\App\Services\GiPermissionService::class)->permiteAlguma(['eventos.pagina.editar', 'templates.variaveis.visualizar', 'templates.variaveis.editar', 'templates.codigo_fonte.visualizar', 'templates.codigo_fonte.editar']))<a href="{{ route('eventos.pagina.editar', $evento) }}" class="btn btn-outline-secondary">Voltar</a>@endif
        </div>
    </div>
    <div id="buildFeedback" class="alert alert-info" role="status" aria-live="polite">Preparando o editor...</div>
    <div id="buildEmpty" class="alert alert-info d-none">
        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>Não há template aberto.
        <button type="button" class="btn btn-link p-0 align-baseline alert-link" data-bs-toggle="modal" data-bs-target="#buildOpenModal" data-mutate>Clique aqui para abrir o template.</button>
    </div>
    <div id="buildActions" class="d-none d-flex flex-wrap gap-2 mb-3">
        <button id="buildSaveAll" class="btn btn-primary" data-mutate><i class="bi bi-floppy-fill me-1"></i>Salvar template</button>
        <button id="buildVersionSave" class="btn btn-outline-primary" data-mutate><i class="bi bi-copy me-1"></i>Salvar em nova versão</button>
        <button id="buildPreview" class="btn btn-outline-info" data-mutate><i class="bi bi-eye me-1"></i>Visualizar</button>
        <button id="buildExport" class="btn btn-outline-success" data-mutate><i class="bi bi-file-earmark-zip me-1"></i>Exportar template ZIP</button>
        <span id="buildDirty" class="align-self-center small text-secondary"></span>
    </div>
    <div id="buildCard" class="card content-card d-none">
        <div class="card-header p-0"><ul class="nav nav-tabs px-3 pt-3 border-0" role="tablist">
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#buildVariables" type="button" role="tab" aria-selected="false">Variáveis do template</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#buildSystem" type="button" role="tab" aria-selected="false">Variáveis do sistema</button></li>
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#buildCode" type="button" role="tab" aria-selected="true">Editor de arquivos de código</button></li>
        </ul></div>
        <div class="card-body p-0 tab-content">
            <div id="buildVariables" class="tab-pane fade p-4" role="tabpanel">
                <p class="text-secondary">Defina as variáveis e seus valores padrão. As alterações ficam no arquivo <code>template.json</code>.</p>
                <div class="row g-3 mb-3"><div class="col-md-6"><label for="buildManifestName" class="form-label">Nome do template</label><input id="buildManifestName" class="form-control" maxlength="150"></div><div class="col-md-6"><label for="buildDescription" class="form-label">Descrição</label><input id="buildDescription" class="form-control" maxlength="500"></div></div>
                <div id="buildVariableRows"></div>
                <button id="buildAddVariable" class="btn btn-outline-primary" data-mutate><i class="bi bi-plus-lg me-1"></i>Adicionar variável</button>
            </div>
            <div id="buildSystem" class="tab-pane fade p-4 system-reference" role="tabpanel">@include('templates.partials.referencia-sistema')</div>
            <div id="buildCode" class="tab-pane fade show active" role="tabpanel">
                <div class="build-workspace">
                    <div id="buildEditorPane" class="source-editor-pane p-3">
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2"><strong id="buildCurrentFile">Selecione um arquivo</strong><div class="d-flex gap-1"><button type="button" id="buildValidatePaths" class="btn btn-sm btn-outline-warning" title="validar caminhos" aria-label="validar caminhos" data-mutate><i class="bi bi-link-45deg" aria-hidden="true"></i></button><button id="buildFullscreen" class="btn btn-sm btn-outline-secondary" title="Alternar tela inteira" aria-label="Alternar tela inteira"><i class="bi bi-arrows-fullscreen"></i></button></div></div>
                        <div class="source-editor-shell"><div id="buildLineNumbers" class="build-line-numbers" aria-label="Linhas do arquivo"></div><div class="build-warning-overlay" aria-hidden="true"><div id="buildWarningLines"></div></div><pre id="buildHighlight" class="source-highlight" aria-hidden="true"><code></code></pre><textarea id="buildEditor" class="source-editor" spellcheck="false" autocomplete="off" autocapitalize="off" aria-label="Código do arquivo" disabled></textarea></div>
                        <div id="buildValidationStatus" class="small mt-2 d-none" role="status" aria-live="polite"></div>
                        <div id="buildBinary" class="d-none p-4 border rounded text-center"></div>
                        <div class="d-flex align-items-center gap-3 mt-3"><button id="buildSaveFile" class="btn btn-primary" data-mutate><i class="bi bi-floppy me-1"></i>Salvar arquivo</button><span id="buildFileState" class="small text-secondary"></span></div>
                    </div>
                    <aside class="build-explorer border-start p-3">
                        <h2 class="h6 fw-bold">Raiz do template</h2>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <button class="btn btn-sm btn-outline-primary" id="buildAddFile" title="Adicionar arquivo" aria-label="Adicionar arquivo" data-mutate><i class="bi bi-file-earmark-plus"></i></button>
                            <button class="btn btn-sm btn-outline-danger" id="buildDeleteFile" title="Remover arquivo selecionado" aria-label="Remover arquivo selecionado" data-mutate><i class="bi bi-file-earmark-minus"></i></button>
                            <button class="btn btn-sm btn-outline-primary" id="buildAddFolder" title="Criar pasta" aria-label="Criar pasta" data-mutate><i class="bi bi-folder-plus"></i></button>
                            <button class="btn btn-sm btn-outline-danger" id="buildDeleteFolder" title="Remover pasta vazia selecionada" aria-label="Remover pasta vazia selecionada" data-mutate><i class="bi bi-folder-minus"></i></button>
                            <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#buildUploadModal" title="Enviar arquivos" aria-label="Enviar arquivos" data-mutate><i class="bi bi-upload"></i></button>
                            <button class="btn btn-sm btn-outline-secondary" id="buildRefreshTree" title="Atualizar lista de arquivos" aria-label="Atualizar lista de arquivos" data-mutate><i class="bi bi-arrow-clockwise"></i></button>
                        </div>
                        <p class="small text-secondary">Arraste os arquivos para outra pasta ou para /.</p>
                        <div id="buildTree" aria-label="Arquivos e pastas"></div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="buildNewModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5">Novo template</h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><label for="buildNewName" class="form-label">Nome</label><input id="buildNewName" class="form-control" maxlength="150" value="Template {{ $evento->nome }}"></div><div class="modal-footer"><button id="buildCreate" class="btn btn-primary" data-mutate>Criar template</button></div></div></div></div>
<div class="modal fade" id="buildOpenModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5">Templates em construção</h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body"><label for="buildSelect" class="form-label">Pesquisar template</label><select id="buildSelect" class="form-select" style="width:100%"><option></option></select></div><div class="modal-footer"><button id="buildOpen" class="btn btn-primary" data-mutate>Abrir selecionado</button></div></div></div></div>
<div class="modal fade" id="buildUploadModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5">Enviar arquivos</h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body">
    <div id="buildDropzone" class="build-dropzone mb-3" tabindex="0" role="region" aria-label="Área para colar ou soltar arquivos"><i class="bi bi-cloud-arrow-up fs-1"></i><p>Clique nesta área e cole arquivos ou imagens com Ctrl+V, ou arraste arquivos para cá.</p><label class="btn btn-outline-primary" for="buildUploadInput">Escolher arquivos</label><input class="visually-hidden" id="buildUploadInput" type="file" multiple accept="{{ implode(',', array_map(fn($extensao) => '.'.$extensao, $extensoes)) }}"><div class="small text-secondary">Até 20 MB por arquivo; código até 2 MB.</div></div>
    <label class="form-label" for="buildUploadFolder">Pasta inicial dos próximos arquivos</label><select id="buildUploadFolder" class="form-select mb-3"></select>
    <div id="buildUploadFeedback" role="status" class="small mb-2"></div><div id="buildUploadList"></div>
</div><div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button><button id="buildSendUploads" class="btn btn-primary" data-mutate><i class="bi bi-upload me-1"></i>Enviar arquivos</button></div></div></div></div>
<div class="modal fade" id="buildPreviewModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-fullscreen"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5">Prévia do template</h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body p-0"><iframe id="buildPreviewFrame" title="Prévia do template com dados do evento" sandbox="allow-scripts" style="width:100%;height:100%;border:0"></iframe></div></div></div></div>
@endsection
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ asset('template-asset-paths.js') }}?v=1"></script>
<script src="{{ asset('template-build.js') }}?v=3"></script>
@endpush
