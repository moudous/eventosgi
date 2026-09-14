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

@if($evento->template_pagina_id)
<div class="card content-card mb-4 template-workspace-card">
    <div class="card-header p-0">
        <ul class="nav nav-tabs px-3 pt-3 border-0" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" id="variaveis-tab" data-bs-toggle="tab" data-bs-target="#variaveis-template" type="button" role="tab" aria-controls="variaveis-template" aria-selected="true"><i class="bi bi-sliders me-2"></i>Variáveis do template</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" id="codigo-fonte-tab" data-bs-toggle="tab" data-bs-target="#codigo-fonte" type="button" role="tab" aria-controls="codigo-fonte" aria-selected="false"><i class="bi bi-code-slash me-2"></i>Editor de arquivos de código-fonte</button></li>
        </ul>
    </div>
    <div class="card-body p-0"><div class="tab-content">
        <div class="tab-pane fade show active p-4" id="variaveis-template" role="tabpanel" aria-labelledby="variaveis-tab" tabindex="0">
            @if($variaveis !== [])
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
            @else
                <div class="alert alert-light border mb-0">Este template não declara variáveis.</div>
            @endif
        </div>
        <div class="tab-pane fade" id="codigo-fonte" role="tabpanel" aria-labelledby="codigo-fonte-tab" tabindex="0">
            <div class="source-editor-intro"><div><h2 class="h6 fw-bold mb-1">Editor de arquivos de código-fonte</h2><p class="small text-secondary mb-0">Edite arquivos textuais do template. Salvar altera todos os eventos que usam esta versão; para isolar a mudança, crie uma nova versão.</p></div><span class="badge text-bg-light border">Template v{{ $evento->templatePagina?->versao ?: 'sem versão' }}</span></div>
            <div class="source-workspace">
                <section class="source-editor-pane" aria-label="Editor de código">
                    <header class="source-editor-toolbar"><div><i class="bi bi-file-earmark-code me-2"></i><strong id="sourceCurrentFile">Selecione um arquivo</strong></div><span class="badge text-bg-secondary" id="sourceState">Aguardando</span></header>
                    <textarea id="sourceEditor" class="source-editor" aria-label="Conteúdo do arquivo" spellcheck="false" autocapitalize="off" autocomplete="off" disabled></textarea>
                    <div class="source-editor-actions">
                        <button type="button" class="btn btn-primary" id="sourceSave" disabled><i class="bi bi-floppy me-2"></i>Salvar arquivo</button>
                        <button type="button" class="btn btn-outline-primary" id="sourceSaveVersion" disabled><i class="bi bi-copy me-2"></i>Salvar como nova versão</button>
                        <a class="btn btn-outline-info ms-lg-auto" href="{{ route('eventos.pagina.visualizar', $evento) }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-2"></i>Visualizar</a>
                    </div>
                    <div class="small mt-2" id="sourceFeedback" role="status" aria-live="polite"></div>
                </section>
                <aside class="source-explorer" aria-label="Arquivos do template"><header><i class="bi bi-folder2-open me-2"></i><strong>Raiz do template</strong><small>{{ $evento->templatePagina?->pasta }}</small></header><div class="source-file-list">
                    @forelse($arquivosCodigo as $arquivo)
                        <button type="button" class="source-file" data-arquivo="{{ $arquivo }}"><i class="bi bi-file-earmark-code" aria-hidden="true"></i><span>{{ $arquivo }}</span></button>
                    @empty
                        <p class="small text-secondary p-3 mb-0">Nenhum arquivo textual editável.</p>
                    @endforelse
                </div></aside>
            </div>
        </div>
    </div></div>
</div>
@endif

@if(!$evento->template_pagina_id)
    @include('eventos.partials.pagina-padrao')
@endif

<div class="d-flex justify-content-end gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Salvar</button></div>
</form>

@if($evento->template_pagina_id)
<div class="modal fade" id="sourceVersionModal" tabindex="-1" aria-labelledby="sourceVersionTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><div><h2 class="modal-title fs-5" id="sourceVersionTitle">Salvar como nova versão</h2><p class="small text-secondary mb-0">A pasta completa será copiada e esta nova versão será aplicada somente a este evento.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
    <div class="modal-body"><div class="mb-3"><label class="form-label fw-semibold" for="sourceVersionName">Nome do template</label><input class="form-control" id="sourceVersionName" maxlength="150" value="{{ $evento->templatePagina?->nome }}"></div><div><label class="form-label fw-semibold" for="sourceVersionNumber">Nova versão</label><input class="form-control" id="sourceVersionNumber" maxlength="20" pattern="[0-9A-Za-z][0-9A-Za-z._-]*" value="{{ $proximaVersao }}"><div class="form-text">A versão foi incrementada automaticamente, mas pode ser editada.</div></div><div class="small mt-3" id="sourceVersionFeedback" role="status" aria-live="polite"></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="sourceCreateVersion"><i class="bi bi-copy me-2"></i>Criar e aplicar versão</button></div>
</div></div></div>
@endif
@endsection

@push('styles')
<style>
.template-workspace-card>.card-header{background:#f8fafc}.template-workspace-card .nav-tabs .nav-link{color:#52606d;font-weight:600}.template-workspace-card .nav-tabs .nav-link.active{color:#0d6efd}.source-editor-intro{align-items:center;background:#f8fafc;border-bottom:1px solid #dee2e6;display:flex;gap:20px;justify-content:space-between;padding:18px 22px}.source-workspace{display:grid;grid-template-columns:minmax(0,4fr) minmax(220px,1fr);min-height:620px}.source-editor-pane{display:flex;flex-direction:column;min-width:0;padding:18px}.source-editor-toolbar{align-items:center;background:#17212b;border-radius:8px 8px 0 0;color:#dce7ef;display:flex;gap:15px;justify-content:space-between;min-height:48px;padding:10px 15px}.source-editor{background:#0f1720;border:0;border-radius:0;color:#d9e5ed;flex:1;font:13px/1.55 ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;min-height:520px;outline:0;padding:18px;resize:vertical;tab-size:4;white-space:pre}.source-editor:disabled{color:#778691}.source-editor:focus{box-shadow:inset 0 0 0 2px #0d6efd}.source-editor-actions{align-items:center;background:#eef2f5;border:1px solid #d9e0e5;border-radius:0 0 8px 8px;display:flex;flex-wrap:wrap;gap:10px;padding:12px}.source-explorer{background:#f8fafc;border-left:1px solid #dee2e6;min-width:0}.source-explorer>header{border-bottom:1px solid #dee2e6;padding:17px 14px}.source-explorer>header small{color:#6c757d;display:block;margin:4px 0 0 24px;overflow-wrap:anywhere}.source-file-list{max-height:680px;overflow:auto;padding:8px}.source-file{align-items:flex-start;background:transparent;border:0;border-radius:6px;color:#34495e;display:flex;font:12px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;gap:8px;padding:8px;text-align:left;width:100%}.source-file:hover{background:#e9eef3;color:#0d6efd}.source-file.active{background:#dce9fb;color:#084298;font-weight:700}.source-file span{overflow-wrap:anywhere}.source-file i{flex:0 0 auto;margin-top:1px}.source-feedback-success{color:#146c43}.source-feedback-error{color:#b02a37}@media(max-width:900px){.source-workspace{grid-template-columns:1fr}.source-explorer{border-bottom:1px solid #dee2e6;border-left:0;grid-row:1;max-height:240px}.source-file-list{max-height:175px}.source-editor-pane{grid-row:2}.source-editor{min-height:460px}}@media(max-width:576px){.source-editor-intro{align-items:flex-start;flex-direction:column}.source-editor-actions .btn,.source-editor-actions a{width:100%}}
</style>
@endpush

@if($evento->template_pagina_id)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const editor=document.getElementById('sourceEditor'),arquivoAtual=document.getElementById('sourceCurrentFile'),estado=document.getElementById('sourceState'),feedback=document.getElementById('sourceFeedback'),salvar=document.getElementById('sourceSave'),salvarVersao=document.getElementById('sourceSaveVersion'),criarVersao=document.getElementById('sourceCreateVersion'),modalElemento=document.getElementById('sourceVersionModal'),modal=bootstrap.Modal.getOrCreateInstance(modalElemento),botoes=[...document.querySelectorAll('[data-arquivo]')];
    const urls={ler:@json(route('eventos.pagina.codigo-fonte',$evento)),salvar:@json(route('eventos.pagina.codigo-fonte.salvar',$evento)),versao:@json(route('eventos.pagina.codigo-fonte.nova-versao',$evento))};
    const token=@json(csrf_token());let atual='',original='',ocupado=false;
    const mensagem=(texto,tipo='')=>{feedback.textContent=texto;feedback.className='small mt-2 '+(tipo?`source-feedback-${tipo}`:'');};
    const erroDaResposta=dados=>dados?.message||Object.values(dados?.errors||{})[0]?.[0]||'Não foi possível concluir a operação.';
    const requisicao=async(url,opcoes={})=>{const resposta=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token,...opcoes.headers},...opcoes});let dados={};try{dados=await resposta.json()}catch(e){}if(!resposta.ok)throw new Error(erroDaResposta(dados));return dados;};
    const alterado=()=>atual!==''&&editor.value!==original;
    const atualizarEstado=()=>{const sujo=alterado();estado.textContent=ocupado?'Processando':(sujo?'Não salvo':(atual?'Salvo':'Aguardando'));estado.className='badge '+(ocupado?'text-bg-info':(sujo?'text-bg-warning':(atual?'text-bg-success':'text-bg-secondary')));};
    const ocupar=valor=>{ocupado=valor;salvar.disabled=valor||!atual;salvarVersao.disabled=valor||!atual;editor.disabled=valor||!atual;atualizarEstado();};
    const abrir=async arquivo=>{if(ocupado||arquivo===atual)return;if(alterado()&&!confirm('Há alterações não salvas. Deseja abrir outro arquivo e descartá-las?'))return;ocupar(true);mensagem('Carregando arquivo...');try{const url=new URL(urls.ler,window.location.origin);url.searchParams.set('arquivo',arquivo);const dados=await requisicao(url);atual=dados.arquivo;original=dados.conteudo;editor.value=dados.conteudo;arquivoAtual.textContent=atual;botoes.forEach(botao=>botao.classList.toggle('active',botao.dataset.arquivo===atual));mensagem('Arquivo carregado.','success')}catch(erro){mensagem(erro.message,'error')}finally{ocupar(false)}};
    const salvarAtual=async()=>{if(!atual||ocupado)return;ocupar(true);mensagem('Salvando arquivo...');try{const dados=await requisicao(urls.salvar,{method:'PUT',body:JSON.stringify({arquivo:atual,conteudo:editor.value})});original=editor.value;mensagem(dados.message,'success')}catch(erro){mensagem(erro.message,'error')}finally{ocupar(false)}};
    botoes.forEach(botao=>botao.addEventListener('click',()=>abrir(botao.dataset.arquivo)));
    editor.addEventListener('input',atualizarEstado);
    editor.addEventListener('keydown',evento=>{if((evento.ctrlKey||evento.metaKey)&&evento.key.toLowerCase()==='s'){evento.preventDefault();salvarAtual()}if(evento.key==='Tab'){evento.preventDefault();const inicio=editor.selectionStart,fim=editor.selectionEnd;editor.setRangeText('    ',inicio,fim,'end');editor.dispatchEvent(new Event('input'))}});
    salvar.addEventListener('click',salvarAtual);
    salvarVersao.addEventListener('click',()=>{if(!atual)return;document.getElementById('sourceVersionFeedback').textContent='';modal.show()});
    criarVersao.addEventListener('click',async()=>{if(!atual||ocupado)return;const nome=document.getElementById('sourceVersionName').value.trim(),versao=document.getElementById('sourceVersionNumber').value.trim(),retorno=document.getElementById('sourceVersionFeedback');if(!nome||!versao){retorno.textContent='Informe o nome e a versão.';retorno.className='small mt-3 source-feedback-error';return}criarVersao.disabled=true;retorno.textContent='Copiando os arquivos e criando a versão...';retorno.className='small mt-3';try{const dados=await requisicao(urls.versao,{method:'POST',body:JSON.stringify({nome,versao,arquivo:atual,conteudo:editor.value})});retorno.textContent=dados.message;retorno.className='small mt-3 source-feedback-success';window.location.assign(dados.redirect)}catch(erro){retorno.textContent=erro.message;retorno.className='small mt-3 source-feedback-error';criarVersao.disabled=false}});
    document.getElementById('codigo-fonte-tab').addEventListener('shown.bs.tab',()=>{history.replaceState(null,'','#codigo-fonte');if(!atual){const preferido=botoes.find(botao=>botao.dataset.arquivo==='index.html')||botoes[0];if(preferido)abrir(preferido.dataset.arquivo)}});
    document.getElementById('variaveis-tab').addEventListener('shown.bs.tab',()=>history.replaceState(null,'',location.pathname+location.search));
    if(location.hash==='#codigo-fonte')bootstrap.Tab.getOrCreateInstance(document.getElementById('codigo-fonte-tab')).show();
});
</script>
@endpush
@endif
