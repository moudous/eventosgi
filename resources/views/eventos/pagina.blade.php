@extends('layouts.app')
@section('title', 'Página do evento')
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><h1 class="page-title">Página do evento</h1><p class="page-description mb-0">{{ $evento->nome }}</p></div>
    <div class="d-flex flex-wrap gap-2">
        @if($podeEditarCodigo)
            <a href="{{ route('eventos.pagina.criador.index', $evento) }}" class="btn btn-primary"><i class="bi bi-code-square me-1"></i>Criador de template</a>
        @endif
        @if(app(\App\Services\GiPermissionService::class)->permite('eventos.listar'))<a href="{{ route('eventos.index') }}" class="btn btn-outline-secondary">Voltar</a>@endif
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
            @unless($podeEditarPagina)<input type="hidden" name="template_pagina_id" value="{{ $evento->template_pagina_id }}">@endunless
            <select class="form-select @error('template_pagina_id') is-invalid @enderror" id="template_pagina_id" name="template_pagina_id" onchange="this.form.submit()" @disabled(!$podeEditarPagina)>
                <option value="">Página Padrão do Sistema (Não utilizar template)</option>
                @foreach($templates as $template)
                    <option value="{{ $template->id }}" @selected((int) old('template_pagina_id', $evento->template_pagina_id) === $template->id)>{{ $template->nome }}@if($template->versao) (v{{ $template->versao }})@endif</option>
                @endforeach
            </select>
            @error('template_pagina_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">@if($podeEditarPagina)Trocar o template salva a escolha e recarrega as variáveis que ele declara.@else Seu perfil pode consultar este template, mas não pode trocá-lo.@endif</div>
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
            @if($podeVerVariaveis)<li class="nav-item" role="presentation"><button class="nav-link active" id="variaveis-tab" data-bs-toggle="tab" data-bs-target="#variaveis-template" type="button" role="tab" aria-controls="variaveis-template" aria-selected="true"><i class="bi bi-sliders me-2"></i>Variáveis do template</button></li>@endif
            <li class="nav-item" role="presentation"><button class="nav-link @if(!$podeVerVariaveis) active @endif" id="variaveis-sistema-tab" data-bs-toggle="tab" data-bs-target="#variaveis-sistema" type="button" role="tab" aria-controls="variaveis-sistema" aria-selected="{{ $podeVerVariaveis ? 'false' : 'true' }}"><i class="bi bi-braces-asterisk me-2"></i>Variáveis do sistema</button></li>
            @if($podeVerCodigo)<li class="nav-item" role="presentation"><button class="nav-link" id="codigo-fonte-tab" data-bs-toggle="tab" data-bs-target="#codigo-fonte" type="button" role="tab" aria-controls="codigo-fonte" aria-selected="false"><i class="bi bi-code-slash me-2"></i>Editor de arquivos de código-fonte</button></li>@endif
        </ul>
    </div>
    <div class="card-body p-0"><div class="tab-content">
        @if($podeVerVariaveis)<div class="tab-pane fade show active p-4" id="variaveis-template" role="tabpanel" aria-labelledby="variaveis-tab" tabindex="0">
            @if($variaveis !== [])
                <p class="text-muted">Valores que este template pede. Dentro do HTML eles aparecem pelo próprio nome, por exemplo <code>&#123;&#123; {{ $variaveis[0]['nome'] }} &#125;&#125;</code>.</p>
                <div class="row g-4">
                    @foreach($variaveis as $variavel)
                    <div class="col-12 col-md-6">
                        @php
                            $valorVariavel = old(
                                'variaveis.'.$variavel['nome'],
                                $valores[$variavel['nome']] ?? $variavel['padrao'],
                            );
                        @endphp
                        @if(($variavel['tipo'] ?? 'text') === 'checkbox')
                            <input type="hidden" name="variaveis[{{ $variavel['nome'] }}]" value="0" @disabled(!$podeEditarVariaveis)>
                            <div class="form-check form-switch pt-2">
                                <input class="form-check-input" id="var-{{ $variavel['nome'] }}" name="variaveis[{{ $variavel['nome'] }}]" type="checkbox" value="1" @checked(!in_array($valorVariavel, [null, false, '', 0, '0'], true)) @disabled(!$podeEditarVariaveis)>
                                <label class="form-check-label fw-semibold" for="var-{{ $variavel['nome'] }}">{{ $variavel['rotulo'] }}</label>
                            </div>
                        @else
                        <label class="form-label fw-semibold" for="var-{{ $variavel['nome'] }}">{{ $variavel['rotulo'] }}</label>
                        @if(($variavel['tipo'] ?? 'text') === 'textarea')
                            <textarea class="form-control" id="var-{{ $variavel['nome'] }}" name="variaveis[{{ $variavel['nome'] }}]" rows="5" maxlength="2000" @readonly(!$podeEditarVariaveis)>{{ old('variaveis.'.$variavel['nome'], $valores[$variavel['nome']] ?? $variavel['padrao']) }}</textarea>
                        @else
                            <input class="form-control" id="var-{{ $variavel['nome'] }}" name="variaveis[{{ $variavel['nome'] }}]" type="{{ in_array(($variavel['tipo'] ?? 'text'), ['color','url'], true) ? $variavel['tipo'] : 'text' }}" maxlength="2000" value="{{ old('variaveis.'.$variavel['nome'], $valores[$variavel['nome']] ?? $variavel['padrao']) }}" @readonly(!$podeEditarVariaveis)>
                        @endif
                        @endif
                        <div class="form-text"><code>&#123;&#123; {{ $variavel['nome'] }} &#125;&#125;</code></div>
                    </div>
                    @endforeach
                </div>
            @else
                <div class="alert alert-light border mb-0">Este template não declara variáveis.</div>
            @endif
        </div>@endif
        <div class="tab-pane fade p-4 system-reference @if(!$podeVerVariaveis) show active @endif" id="variaveis-sistema" role="tabpanel" aria-labelledby="variaveis-sistema-tab" tabindex="0">
            @include('templates.partials.referencia-sistema')
        </div>
        @if($podeVerCodigo)<div class="tab-pane fade" id="codigo-fonte" role="tabpanel" aria-labelledby="codigo-fonte-tab" tabindex="0">
            <div class="source-editor-intro"><div><h2 class="h6 fw-bold mb-1">Editor de arquivos de código-fonte</h2><p class="small text-secondary mb-0">Edite arquivos textuais do template. Salvar altera todos os eventos que usam esta versão; para isolar a mudança, crie uma nova versão.</p></div><span class="badge text-bg-light border">Template v{{ $evento->templatePagina?->versao ?: 'sem versão' }}</span></div>
            <div class="source-workspace">
                <section class="source-editor-pane" id="sourceEditorPane" aria-label="Editor de código">
                    <header class="source-editor-toolbar">
                        <div class="source-current-file"><i class="bi bi-file-earmark-code me-2"></i><strong id="sourceCurrentFile">Selecione um arquivo</strong><span class="source-language" id="sourceLanguage">TEXTO</span></div>
                        <div class="source-toolbar-actions">
                            <span class="badge text-bg-secondary" id="sourceState">Aguardando</span>
                            <button type="button" class="source-tool-button" id="sourceToggleExplorer" title="Ocultar ou mostrar a raiz do template" aria-pressed="false"><i class="bi bi-layout-sidebar-reverse"></i><span>Ocultar raiz</span></button>
                            <button type="button" class="source-tool-button" id="sourceToggleFullscreen" title="Editor em tela inteira" aria-pressed="false"><i class="bi bi-arrows-fullscreen"></i><span>Tela inteira</span></button>
                        </div>
                    </header>
                    <div class="source-editor-shell" id="sourceEditorShell">
                        <pre class="source-highlight" id="sourceHighlight" aria-hidden="true"><code></code></pre>
                        <textarea id="sourceEditor" class="source-editor" aria-label="Conteúdo do arquivo" spellcheck="false" autocapitalize="off" autocomplete="off" wrap="off" disabled></textarea>
                        <span class="source-resize-label" aria-hidden="true"><i class="bi bi-grip-horizontal"></i> Arraste para alterar a altura</span>
                    </div>
                    <div class="source-editor-actions">
                        <button type="button" class="btn btn-primary" id="sourceSave" disabled @if(!$podeEditarCodigo) title="Seu perfil possui somente visualização" @endif><i class="bi bi-floppy me-2"></i>Salvar arquivo</button>
                        <button type="button" class="btn btn-outline-primary" id="sourceSaveVersion" disabled @if(!$podeEditarCodigo) title="Seu perfil possui somente visualização" @endif><i class="bi bi-copy me-2"></i>Salvar como nova versão</button>
                        <a class="btn btn-outline-info ms-lg-auto" href="{{ route('eventos.pagina.visualizar', $evento) }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-2"></i>Visualizar</a>
                    </div>
                    <div class="small mt-2" id="sourceFeedback" role="status" aria-live="polite"></div>
                </section>
                <aside class="source-explorer" id="sourceExplorer" aria-label="Arquivos do template"><header><i class="bi bi-folder2-open me-2"></i><strong>Raiz do template</strong><small>{{ $evento->templatePagina?->pasta }}/</small></header><div class="source-file-list">
                    @forelse($arquivosCodigo as $arquivo)
                        @php($extensao = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION)))
                        <button type="button" class="source-file source-file-{{ $extensao }}" data-arquivo="{{ $arquivo }}" data-extensao="{{ $extensao }}"><i class="bi {{ match($extensao) {'html','htm' => 'bi-filetype-html', 'css' => 'bi-filetype-css', 'js' => 'bi-filetype-js', 'json' => 'bi-braces', default => 'bi-file-earmark-code'} }}" aria-hidden="true"></i><span>{{ $arquivo }}</span><small>{{ strtoupper($extensao) }}</small></button>
                    @empty
                        <p class="small text-secondary p-3 mb-0">Nenhum arquivo textual editável.</p>
                    @endforelse
                </div></aside>
            </div>
        </div>@endif
    </div></div>
</div>
@endif

@if(!$evento->template_pagina_id)
    @include('eventos.partials.pagina-padrao')
@endif

@if($podeEditarPagina || $podeEditarVariaveis)<div class="d-flex justify-content-end gap-2"><button class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Salvar</button></div>@endif
</form>

@if($evento->template_pagina_id && $podeVerCodigo)
<div class="modal fade" id="sourceVersionModal" tabindex="-1" aria-labelledby="sourceVersionTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><div><h2 class="modal-title fs-5" id="sourceVersionTitle">Salvar como nova versão</h2><p class="small text-secondary mb-0">A pasta completa será copiada e esta nova versão será aplicada somente a este evento.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
    <div class="modal-body"><div class="mb-3"><label class="form-label fw-semibold" for="sourceVersionName">Nome do template</label><input class="form-control" id="sourceVersionName" maxlength="150" value="{{ $evento->templatePagina?->nome }}"></div><div><label class="form-label fw-semibold" for="sourceVersionNumber">Nova versão</label><input class="form-control" id="sourceVersionNumber" maxlength="20" pattern="[0-9A-Za-z][0-9A-Za-z._-]*" value="{{ $proximaVersao }}"><div class="form-text">A versão foi incrementada automaticamente, mas pode ser editada.</div></div><div class="small mt-3" id="sourceVersionFeedback" role="status" aria-live="polite"></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="sourceCreateVersion"><i class="bi bi-copy me-2"></i>Criar e aplicar versão</button></div>
</div></div></div>
@endif
@endsection

@push('styles')
<link href="{{ asset('template-editor.css') }}?v=1" rel="stylesheet">
@endpush

@if($evento->template_pagina_id && $podeVerCodigo)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const editor=document.getElementById('sourceEditor'),destaque=document.getElementById('sourceHighlight'),codigoDestacado=destaque.querySelector('code'),editorShell=document.getElementById('sourceEditorShell'),editorPane=document.getElementById('sourceEditorPane'),workspace=document.querySelector('.source-workspace'),arquivoAtual=document.getElementById('sourceCurrentFile'),linguagem=document.getElementById('sourceLanguage'),estado=document.getElementById('sourceState'),feedback=document.getElementById('sourceFeedback'),salvar=document.getElementById('sourceSave'),salvarVersao=document.getElementById('sourceSaveVersion'),criarVersao=document.getElementById('sourceCreateVersion'),alternarRaiz=document.getElementById('sourceToggleExplorer'),alternarTela=document.getElementById('sourceToggleFullscreen'),modalElemento=document.getElementById('sourceVersionModal'),modal=bootstrap.Modal.getOrCreateInstance(modalElemento),botoes=[...document.querySelectorAll('[data-arquivo]')];
    const urls={ler:@json(route('eventos.pagina.codigo-fonte',$evento)),salvar:@json(route('eventos.pagina.codigo-fonte.salvar',$evento)),versao:@json(route('eventos.pagina.codigo-fonte.nova-versao',$evento))};
    const token=@json(csrf_token()),podeEditarCodigo=@json($podeEditarCodigo);let atual='',original='',ocupado=false;
    const chaves={raiz:'eventosgi.editor.raiz.oculta',altura:'eventosgi.editor.altura'};
    const lerPreferencia=chave=>{try{return localStorage.getItem(chave)}catch(erro){return null}};
    const guardarPreferencia=(chave,valor)=>{try{localStorage.setItem(chave,valor)}catch(erro){}};
    const avisoCopia=document.createElement('div');avisoCopia.className='copy-toast';avisoCopia.setAttribute('role','status');document.body.appendChild(avisoCopia);let tempoCopia;
    const mostrarCopia=texto=>{avisoCopia.textContent=texto;avisoCopia.classList.add('show');clearTimeout(tempoCopia);tempoCopia=setTimeout(()=>avisoCopia.classList.remove('show'),1800)};
    const copiar=async(texto,botao)=>{try{if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(texto)}else{const campo=document.createElement('textarea');campo.value=texto;campo.style.position='fixed';campo.style.opacity='0';document.body.appendChild(campo);campo.select();document.execCommand('copy');campo.remove()}botao.classList.add('copied');setTimeout(()=>botao.classList.remove('copied'),1200);mostrarCopia('Código copiado para a área de transferência.')}catch(erro){mostrarCopia('Não foi possível copiar automaticamente.')}};
    const mensagem=(texto,tipo='')=>{feedback.textContent=texto;feedback.className='small mt-2 '+(tipo?`source-feedback-${tipo}`:'');};
    const erroDaResposta=dados=>dados?.message||Object.values(dados?.errors||{})[0]?.[0]||'Não foi possível concluir a operação.';
    const requisicao=async(url,opcoes={})=>{const resposta=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token,...opcoes.headers},...opcoes});let dados={};try{dados=await resposta.json()}catch(e){}if(!resposta.ok)throw new Error(erroDaResposta(dados));return dados;};
    const escapar=valor=>valor.replace(/[&<>]/g,caractere=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[caractere]));
    const colorir=(codigo,expressao,classificar)=>{let saida='',posicao=0,achado;expressao.lastIndex=0;while((achado=expressao.exec(codigo))!==null){saida+=escapar(codigo.slice(posicao,achado.index));const classe=classificar(achado[0]);saida+=`<span class="${classe}">${escapar(achado[0])}</span>`;posicao=achado.index+achado[0].length}return saida+escapar(codigo.slice(posicao))};
    const extensao=()=>atual.includes('.')?atual.split('.').pop().toLowerCase():'txt';
    const realcar=codigo=>{
        const tipo=extensao();
        if(tipo==='html'||tipo==='htm')return colorir(codigo,/(\x7b\x7b[\s\S]*?\x7d\x7d|\x7b%[\s\S]*?%\x7d|<!--[\s\S]*?-->|<\/?[A-Za-z][^>]*>)/g,trecho=>trecho.startsWith('<!--')?'tok-comment':(trecho.startsWith('\x7b\x7b')||trecho.startsWith('\x7b%'))?'tok-template':'tok-tag');
        if(tipo==='css')return colorir(codigo,/\/\*[\s\S]*?\*\/|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|#[0-9a-fA-F]{3,8}\b|@[a-zA-Z-]+|(?:--)?[a-zA-Z_][\w-]*(?=\s*:)|\b\d+(?:\.\d+)?(?:%|px|rem|em|vh|vw|s|ms)?\b/g,trecho=>trecho.startsWith('/*')?'tok-comment':/^['"]/.test(trecho)?'tok-string':trecho.startsWith('#')?'tok-color':trecho.startsWith('@')?'tok-rule':/^[A-Za-z_-]/.test(trecho)?'tok-property':'tok-number');
        if(tipo==='js'||tipo==='mjs')return colorir(codigo,/\/\*[\s\S]*?\*\/|\/\/[^\n]*|`(?:\\.|[^`\\])*`|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|\b(?:const|let|var|function|return|if|else|for|while|class|new|async|await|try|catch|throw|import|export|from|default|switch|case|break|continue|this|typeof|instanceof|in|of)\b|\b(?:true|false|null|undefined)\b|\b\d+(?:\.\d+)?\b/g,trecho=>trecho.startsWith('//')||trecho.startsWith('/*')?'tok-comment':/^['"`]/.test(trecho)?'tok-string':/^(true|false|null|undefined)$/.test(trecho)?'tok-bool':/^\d/.test(trecho)?'tok-number':'tok-keyword');
        if(tipo==='json')return colorir(codigo,/"(?:\\.|[^"\\])*"|-?\b\d+(?:\.\d+)?(?:e[+-]?\d+)?\b|\b(?:true|false|null)\b/gi,trecho=>trecho.startsWith('"')?'tok-string':/^(true|false|null)$/i.test(trecho)?'tok-bool':'tok-number');
        return escapar(codigo);
    };
    const sincronizarRolagem=()=>{destaque.scrollTop=editor.scrollTop;destaque.scrollLeft=editor.scrollLeft};
    const atualizarCores=()=>{const tipo=extensao(),rotulos={html:'HTML',htm:'HTML',css:'CSS',js:'JAVASCRIPT',mjs:'JAVASCRIPT',json:'JSON',md:'MARKDOWN',txt:'TEXTO'};linguagem.textContent=rotulos[tipo]||tipo.toUpperCase();codigoDestacado.innerHTML=realcar(editor.value)+(editor.value.endsWith('\n')?' ':'');sincronizarRolagem()};
    const alterado=()=>atual!==''&&editor.value!==original;
    const atualizarEstado=()=>{const sujo=alterado();estado.textContent=ocupado?'Processando':(sujo?'Não salvo':(atual?'Salvo':'Aguardando'));estado.className='badge '+(ocupado?'text-bg-info':(sujo?'text-bg-warning':(atual?'text-bg-success':'text-bg-secondary')));};
    const ocupar=valor=>{ocupado=valor;salvar.disabled=!podeEditarCodigo||valor||!atual;salvarVersao.disabled=!podeEditarCodigo||valor||!atual;editor.disabled=valor||!atual;editor.readOnly=!podeEditarCodigo;atualizarEstado();};
    const abrir=async arquivo=>{if(ocupado||arquivo===atual)return;if(alterado()&&!confirm('Há alterações não salvas. Deseja abrir outro arquivo e descartá-las?'))return;ocupar(true);mensagem('Carregando arquivo...');try{const url=new URL(urls.ler,window.location.origin);url.searchParams.set('arquivo',arquivo);const dados=await requisicao(url);atual=dados.arquivo;original=dados.conteudo;editor.value=dados.conteudo;arquivoAtual.textContent=atual;botoes.forEach(botao=>botao.classList.toggle('active',botao.dataset.arquivo===atual));atualizarCores();editor.scrollTop=editor.scrollLeft=0;sincronizarRolagem();mensagem('Arquivo carregado.','success')}catch(erro){mensagem(erro.message,'error')}finally{ocupar(false)}};
    const salvarAtual=async()=>{if(!podeEditarCodigo||!atual||ocupado)return;ocupar(true);mensagem('Salvando arquivo...');try{const dados=await requisicao(urls.salvar,{method:'PUT',body:JSON.stringify({arquivo:atual,conteudo:editor.value})});original=editor.value;mensagem(dados.message,'success')}catch(erro){mensagem(erro.message,'error')}finally{ocupar(false)}};
    const definirRaizOculta=oculta=>{workspace.classList.toggle('explorer-hidden',oculta);alternarRaiz.setAttribute('aria-pressed',String(oculta));alternarRaiz.querySelector('span').textContent=oculta?'Mostrar raiz':'Ocultar raiz';alternarRaiz.querySelector('i').className=oculta?'bi bi-layout-sidebar':'bi bi-layout-sidebar-reverse';guardarPreferencia(chaves.raiz,oculta?'1':'0')};
    const definirTelaInteira=expandida=>{editorPane.classList.toggle('is-fullscreen',expandida);document.body.classList.toggle('source-fullscreen-lock',expandida);alternarTela.setAttribute('aria-pressed',String(expandida));alternarTela.querySelector('span').textContent=expandida?'Sair da tela inteira':'Tela inteira';alternarTela.querySelector('i').className=expandida?'bi bi-fullscreen-exit':'bi bi-arrows-fullscreen';requestAnimationFrame(sincronizarRolagem)};
    botoes.forEach(botao=>botao.addEventListener('click',()=>abrir(botao.dataset.arquivo)));
    document.querySelectorAll('[data-copy-expression]').forEach(botao=>botao.addEventListener('click',()=>{const expressao=botao.dataset.copyExpression;const abre=String.fromCharCode(123),fecha=String.fromCharCode(125);copiar(botao.classList.contains('system-token-collection')?expressao:`${abre}${abre} ${expressao} ${fecha}${fecha}`,botao)}));
    document.querySelectorAll('.system-copy-example').forEach(botao=>botao.addEventListener('click',()=>copiar(botao.closest('.system-example').querySelector('pre code').textContent.trim(),botao)));
    editor.addEventListener('input',()=>{atualizarEstado();atualizarCores()});
    editor.addEventListener('scroll',sincronizarRolagem);
    editor.addEventListener('keydown',evento=>{if((evento.ctrlKey||evento.metaKey)&&evento.key.toLowerCase()==='s'){evento.preventDefault();salvarAtual()}if(evento.key==='Tab'){evento.preventDefault();const inicio=editor.selectionStart,fim=editor.selectionEnd;editor.setRangeText('    ',inicio,fim,'end');editor.dispatchEvent(new Event('input'))}});
    salvar.addEventListener('click',salvarAtual);
    alternarRaiz.addEventListener('click',()=>definirRaizOculta(!workspace.classList.contains('explorer-hidden')));
    alternarTela.addEventListener('click',()=>definirTelaInteira(!editorPane.classList.contains('is-fullscreen')));
    salvarVersao.addEventListener('click',()=>{if(!podeEditarCodigo||!atual)return;document.getElementById('sourceVersionFeedback').textContent='';modal.show()});
    criarVersao.addEventListener('click',async()=>{if(!podeEditarCodigo||!atual||ocupado)return;const nome=document.getElementById('sourceVersionName').value.trim(),versao=document.getElementById('sourceVersionNumber').value.trim(),retorno=document.getElementById('sourceVersionFeedback');if(!nome||!versao){retorno.textContent='Informe o nome e a versão.';retorno.className='small mt-3 source-feedback-error';return}criarVersao.disabled=true;retorno.textContent='Copiando os arquivos e criando a versão...';retorno.className='small mt-3';try{const dados=await requisicao(urls.versao,{method:'POST',body:JSON.stringify({nome,versao,arquivo:atual,conteudo:editor.value})});retorno.textContent=dados.message;retorno.className='small mt-3 source-feedback-success';window.location.assign(dados.redirect)}catch(erro){retorno.textContent=erro.message;retorno.className='small mt-3 source-feedback-error';criarVersao.disabled=false}});
    document.getElementById('codigo-fonte-tab').addEventListener('shown.bs.tab',()=>{history.replaceState(null,'','#codigo-fonte');if(!atual){const preferido=botoes.find(botao=>botao.dataset.arquivo==='index.html')||botoes[0];if(preferido)abrir(preferido.dataset.arquivo)}});
    document.getElementById('variaveis-tab')?.addEventListener('shown.bs.tab',()=>history.replaceState(null,'',location.pathname+location.search));
    document.getElementById('variaveis-sistema-tab').addEventListener('shown.bs.tab',()=>history.replaceState(null,'','#variaveis-sistema'));
    document.addEventListener('keydown',evento=>{if(evento.key==='Escape'&&editorPane.classList.contains('is-fullscreen'))definirTelaInteira(false)});
    window.addEventListener('beforeunload',evento=>{if(!alterado())return;evento.preventDefault();evento.returnValue=''});
    const alturaSalva=Number(lerPreferencia(chaves.altura));if(alturaSalva>=320&&alturaSalva<=1600)editorShell.style.height=`${alturaSalva}px`;
    if('ResizeObserver'in window)new ResizeObserver(()=>{if(!editorPane.classList.contains('is-fullscreen'))guardarPreferencia(chaves.altura,String(Math.round(editorShell.getBoundingClientRect().height)))}).observe(editorShell);
    definirRaizOculta(lerPreferencia(chaves.raiz)==='1');
    if(location.hash==='#codigo-fonte')bootstrap.Tab.getOrCreateInstance(document.getElementById('codigo-fonte-tab')).show();
    else if(location.hash==='#variaveis-sistema')bootstrap.Tab.getOrCreateInstance(document.getElementById('variaveis-sistema-tab')).show();
});
</script>
@endpush
@endif
