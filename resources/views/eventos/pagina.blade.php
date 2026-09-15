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
                        @php($valorVariavel = old('variaveis.'.$variavel['nome'], $valores[$variavel['nome']] ?? $variavel['padrao']))
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
            @php
                $catalogoSistema = [
                    ['titulo' => 'Evento', 'icone' => 'bi-calendar-event', 'descricao' => 'Dados básicos do evento atual.', 'campos' => ['evento.id', 'evento.nome', 'evento.ativo', 'evento.criado_em']],
                    ['titulo' => 'Atividades', 'icone' => 'bi-list-check', 'descricao' => 'Use dentro de “for atividade in atividades”.', 'campos' => ['atividade.id', 'atividade.nome', 'atividade.subtitulo', 'atividade.local', 'atividade.hora_inicio', 'atividade.hora_fim', 'atividade.dia', 'atividade.hands_on', 'atividade.inscricao_geral', 'atividade.formato', 'atividade.modalidade', 'atividade.data_inicio', 'atividade.data_fim', 'atividade.data_inicio_iso', 'atividade.data_fim_iso', 'atividade.categoria', 'atividade.categoria_id', 'atividade.convidados', 'atividade.sessoes', 'atividade.pode_inscrever', 'atividade.lista_reserva', 'atividade.inscricao_rotulo', 'atividade.url_inscricao', 'atividade.shortcode']],
                    ['titulo' => 'Sessões da atividade', 'icone' => 'bi-clock-history', 'descricao' => 'Use dentro de “for sessao in atividade.sessoes”.', 'campos' => ['sessao.id', 'sessao.nome', 'sessao.data_inicio', 'sessao.data_fim', 'sessao.limite_vagas', 'sessao.vagas_restantes']],
                    ['titulo' => 'Convidados', 'icone' => 'bi-people', 'descricao' => 'Disponível em convidados e atividade.convidados.', 'campos' => ['convidado.id', 'convidado.nome', 'convidado.titulacao', 'convidado.descricao', 'convidado.curriculo', 'convidado.curriculo_texto', 'convidado.local', 'convidado.email', 'convidado.foto']],
                    ['titulo' => 'Currículo e redes', 'icone' => 'bi-person-vcard', 'descricao' => 'Coleções internas de cada convidado.', 'campos' => ['convidado.curriculo_itens', 'item', 'convidado.redes_sociais', 'rede.rede', 'rede.nome', 'rede.url']],
                    ['titulo' => 'Submissões', 'icone' => 'bi-file-earmark-text', 'descricao' => 'Use dentro de “for submissao in submissoes”.', 'campos' => ['submissao.id', 'submissao.titulo', 'submissao.data_inicio', 'submissao.data_fim', 'submissao.aberta', 'submissao.url']],
                    ['titulo' => 'Categorias', 'icone' => 'bi-tags', 'descricao' => 'Use dentro de “for categoria in categorias”.', 'campos' => ['categoria.id', 'categoria.nome']],
                    ['titulo' => 'Dias da programação', 'icone' => 'bi-calendar-week', 'descricao' => 'Disponível em dias e programacao_dias.', 'campos' => ['dia.data', 'dia.data_iso', 'dia.dia_semana', 'dia.total', 'dia.atividades']],
                    ['titulo' => 'Menu por categoria', 'icone' => 'bi-menu-button-wide', 'descricao' => 'Use dentro de “for grupo in menu”.', 'campos' => ['grupo.id', 'grupo.nome', 'grupo.total', 'grupo.atividades']],
                    ['titulo' => 'Outros eventos', 'icone' => 'bi-collection', 'descricao' => 'Use dentro de “for outro in eventos”.', 'campos' => ['outro.id', 'outro.nome']],
                    ['titulo' => 'Controle do laço', 'icone' => 'bi-arrow-repeat', 'descricao' => 'Disponível dentro de qualquer comando for.', 'campos' => ['loop.indice', 'loop.primeiro', 'loop.ultimo']],
                    ['titulo' => 'Totais', 'icone' => 'bi-bar-chart', 'descricao' => 'Valores calculados para o evento.', 'campos' => ['total_convidados', 'total_programacao']],
                ];
            @endphp
            <div class="system-reference-hero"><div><span class="system-reference-mark"><i class="bi bi-code-square"></i></span><div><h2 class="h5 fw-bold mb-1">Referência da linguagem do template</h2><p class="text-secondary mb-0">Clique em qualquer variável ou copie um exemplo completo para colar no editor.</p></div></div><span class="badge rounded-pill text-bg-primary">Saída HTML protegida</span></div>
            <div class="system-collections mb-4">
                <h3 class="system-reference-title">Coleções disponíveis</h3>
                <div class="system-collection-list">
                    @foreach(['atividades', 'categorias', 'convidados', 'submissoes', 'eventos', 'dias', 'programacao_dias', 'menu', 'hands_on'] as $colecao)
                        <button type="button" class="system-token system-token-collection" data-copy-expression="{{ $colecao }}" title="Copiar nome da coleção"><i class="bi bi-layers"></i>{{ $colecao }}<i class="bi bi-copy ms-auto"></i></button>
                    @endforeach
                </div>
            </div>
            <div class="system-variable-grid">
                @foreach($catalogoSistema as $grupo)
                    <article class="system-variable-card"><header><span><i class="bi {{ $grupo['icone'] }}"></i></span><div><h3>{{ $grupo['titulo'] }}</h3><p>{{ $grupo['descricao'] }}</p></div></header><div class="system-token-list">
                        @foreach($grupo['campos'] as $campo)
                            <button type="button" class="system-token" data-copy-expression="{{ $campo }}" title="Copiar variável {{ $campo }}"><code>{{ $campo }}</code><i class="bi bi-copy"></i></button>
                        @endforeach
                    </div></article>
                @endforeach
            </div>
            <section class="system-examples">
                <div class="d-flex align-items-end justify-content-between gap-3 mb-3"><div><h3 class="system-reference-title mb-1">Comandos e exemplos</h3><p class="small text-secondary mb-0">A linguagem aceita leitura de valores, laços, condições simples e arquivos do template.</p></div></div>
                <div class="system-example-grid">
                    <article class="system-example"><header><div><span>VALOR</span><strong>Mostrar uma variável</strong></div><button type="button" class="system-copy-example"><i class="bi bi-copy"></i> Copiar</button></header><pre><code>&#123;&#123; evento.nome &#125;&#125;</code></pre></article>
                    <article class="system-example"><header><div><span>IF</span><strong>Exibição condicional</strong></div><button type="button" class="system-copy-example"><i class="bi bi-copy"></i> Copiar</button></header><pre><code>&#123;% if submissao.aberta %&#125;
  &lt;a href="&#123;&#123; submissao.url &#125;&#125;"&gt;Submeter trabalho&lt;/a&gt;
&#123;% endif %&#125;</code></pre></article>
                    <article class="system-example"><header><div><span>IF / ELSE</span><strong>Duas possibilidades</strong></div><button type="button" class="system-copy-example"><i class="bi bi-copy"></i> Copiar</button></header><pre><code>&#123;% if atividade.pode_inscrever %&#125;
  &lt;a href="&#123;&#123; atividade.url_inscricao &#125;&#125;"&gt;Inscreva-se&lt;/a&gt;
&#123;% else %&#125;
  &lt;span&gt;&#123;&#123; atividade.inscricao_rotulo &#125;&#125;&lt;/span&gt;
&#123;% endif %&#125;</code></pre></article>
                    <article class="system-example"><header><div><span>FOR</span><strong>Listar atividades</strong></div><button type="button" class="system-copy-example"><i class="bi bi-copy"></i> Copiar</button></header><pre><code>&#123;% for atividade in atividades %&#125;
  &lt;article&gt;
    &lt;h2&gt;&#123;&#123; atividade.nome &#125;&#125;&lt;/h2&gt;
    &lt;p&gt;&#123;&#123; atividade.data_inicio &#125;&#125;&lt;/p&gt;
  &lt;/article&gt;
&#123;% endfor %&#125;</code></pre></article>
                    <article class="system-example"><header><div><span>FOR ANINHADO</span><strong>Programação por dia</strong></div><button type="button" class="system-copy-example"><i class="bi bi-copy"></i> Copiar</button></header><pre><code>&#123;% for dia in programacao_dias %&#125;
  &lt;h2&gt;&#123;&#123; dia.dia_semana &#125;&#125; · &#123;&#123; dia.data &#125;&#125;&lt;/h2&gt;
  &#123;% for atividade in dia.atividades %&#125;
    &lt;p&gt;&#123;&#123; loop.indice &#125;&#125;. &#123;&#123; atividade.hora_inicio &#125;&#125; — &#123;&#123; atividade.nome &#125;&#125;&lt;/p&gt;
  &#123;% endfor %&#125;
&#123;% endfor %&#125;</code></pre></article>
                    <article class="system-example"><header><div><span>ASSET</span><strong>Arquivo do template</strong></div><button type="button" class="system-copy-example"><i class="bi bi-copy"></i> Copiar</button></header><pre><code>&lt;link rel="stylesheet" href="&#123;&#123; asset('assets/estilo.css') &#125;&#125;"&gt;
&lt;img src="&#123;&#123; asset('assets/imagem.png') &#125;&#125;" alt=""&gt;</code></pre></article>
                </div>
                <div class="alert alert-info mt-4 mb-0"><i class="bi bi-info-circle me-2"></i>Condições aceitam uma variável simples, como <code>atividade.pode_inscrever</code>. Comparações como <code>categoria == 'Minicurso'</code> não fazem parte da linguagem atual.</div>
            </section>
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
<style>
.template-workspace-card>.card-header{background:#f8fafc}.template-workspace-card .nav-tabs .nav-link{color:#52606d;font-weight:600}.template-workspace-card .nav-tabs .nav-link.active{color:#0d6efd}.source-editor-intro{align-items:center;background:#f8fafc;border-bottom:1px solid #dee2e6;display:flex;gap:20px;justify-content:space-between;padding:18px 22px}.source-workspace{display:grid;grid-template-columns:minmax(0,4fr) minmax(240px,1fr);min-height:620px}.source-workspace.explorer-hidden{grid-template-columns:minmax(0,1fr)}.source-workspace.explorer-hidden .source-explorer{display:none}.source-editor-pane{display:flex;flex-direction:column;min-width:0;padding:18px}.source-editor-toolbar{align-items:center;background:#17212b;border-radius:8px 8px 0 0;color:#dce7ef;display:flex;gap:15px;justify-content:space-between;min-height:52px;padding:9px 12px 9px 15px}.source-current-file,.source-toolbar-actions{align-items:center;display:flex;gap:8px;min-width:0}.source-current-file strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.source-language{background:#293846;border:1px solid #445767;border-radius:4px;color:#8fd3ff;font:700 9px/1 ui-monospace,monospace;letter-spacing:.08em;padding:5px 6px}.source-tool-button{align-items:center;background:#ffffff0d;border:1px solid #ffffff26;border-radius:6px;color:#dce7ef;display:inline-flex;font-size:11px;gap:6px;padding:6px 9px}.source-tool-button:hover,.source-tool-button[aria-pressed="true"]{background:#0d6efd;border-color:#4f9aff;color:#fff}.source-editor-shell{background:#0f1720;height:580px;max-height:85vh;min-height:320px;overflow:hidden;position:relative;resize:vertical}.source-highlight,.source-editor{border:0;font:13px/1.55 ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;height:100%;inset:0;margin:0;min-height:0;overflow:auto;padding:18px 18px 34px;position:absolute;tab-size:4;white-space:pre;width:100%}.source-highlight{background:#0f1720;color:#d9e5ed;pointer-events:none;scrollbar-width:none}.source-highlight::-webkit-scrollbar{display:none}.source-highlight code{font:inherit}.source-editor{background:transparent;caret-color:#fff;color:transparent;outline:0;resize:none;-webkit-text-fill-color:transparent}.source-editor::selection{background:#32689acc;-webkit-text-fill-color:transparent}.source-editor:disabled{caret-color:transparent}.source-editor:focus{box-shadow:inset 0 0 0 2px #0d6efd}.source-resize-label{background:#17212bea;border-radius:5px 0 0;color:#91a4b4;bottom:0;font-size:9px;padding:3px 8px;pointer-events:none;position:absolute;right:0}.tok-comment{color:#728496;font-style:italic}.tok-string{color:#a9dc76}.tok-number,.tok-color{color:#ffd866}.tok-keyword,.tok-bool{color:#ff6188}.tok-tag,.tok-selector{color:#78dce8}.tok-attr,.tok-property{color:#fc9867}.tok-template,.tok-rule{color:#ab9df2}.source-editor-actions{align-items:center;background:#eef2: none;box-sizing: border-box}.system-reference{background:linear-gradient(180deg,#f8fbff 0,#fff 190px)}.system-reference-hero{align-items:center;background:#fff;border:1px solid #dfe7ef;border-radius:14px;box-shadow:0 12px 35px #17212b0d;display:flex;justify-content:space-between;margin-bottom:24px;padding:20px}.system-reference-hero>div{align-items:center;display:flex;gap:14px}.system-reference-mark{align-items:center;background:linear-gradient(135deg,#0d6efd,#6f42c1);border-radius:12px;color:#fff;display:flex;font-size:22px;height:48px;justify-content:center;width:48px}.system-reference-title{color:#253746;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.system-collection-list{display:flex;flex-wrap:wrap;gap:8px}.system-variable-grid{display:grid;gap:14px;grid-template-columns:repeat(3,minmax(0,1fr))}.system-variable-card{background:#fff;border:1px solid #dfe7ef;border-radius:12px;min-width:0;padding:16px}.system-variable-card>header{align-items:flex-start;display:flex;gap:11px;margin-bottom:13px}.system-variable-card>header>span{align-items:center;background:#eef4ff;border-radius:8px;color:#0d6efd;display:flex;flex:0 0 34px;height:34px;justify-content:center}.system-variable-card h3{font-size:14px;margin:0 0 3px}.system-variable-card p{color:#74818d;font-size:10px;line-height:1.45;margin:0}.system-token-list{display:flex;flex-wrap:wrap;gap:6px}.system-token{align-items:center;background:#f5f7f9;border:1px solid #e1e6eb;border-radius:6px;color:#34495e;display:inline-flex;font-size:10px;gap:7px;max-width:100%;padding:6px 8px;text-align:left}.system-token:hover{background:#eaf2ff;border-color:#8eb9f8;color:#084298}.system-token.copied,.system-copy-example.copied{background:#d1e7dd;border-color:#75b798;color:#0a5132}.system-token code{color:inherit;font-size:10px;overflow:hidden;text-overflow:ellipsis}.system-token-collection{background:#17212b;border-color:#17212b;color:#fff;font:11px ui-monospace,monospace;padding:8px 10px}.system-token-collection:hover{background:#263a4c;color:#fff}.system-examples{border-top:1px solid #dfe7ef;margin-top:28px;padding-top:25px}.system-example-grid{display:grid;gap:14px;grid-template-columns:repeat(2,minmax(0,1fr))}.system-example{background:#101820;border:1px solid #263747;border-radius:11px;color:#dce7ef;min-width:0;overflow:hidden}.system-example>header{align-items:center;background:#17212b;border-bottom:1px solid #293b4a;display:flex;justify-content:space-between;padding:10px 12px}.system-example>header div{display:flex;flex-direction:column}.system-example>header span{color:#8fd3ff;font-size:8px;font-weight:800;letter-spacing:.12em}.system-example>header strong{font-size:11px}.system-copy-example{background:#ffffff0d;border:1px solid #ffffff26;border-radius:5px;color:#dce7ef;font-size:9px;padding:5px 8px}.system-example pre{color:#bfcbd5;font:11px/1.6 ui-monospace,SFMono-Regular,Menlo,monospace;margin:0;max-height:270px;overflow:auto;padding:15px;white-space:pre}.system-example code{color:inherit;font:inherit}.copy-toast{background:#17212b;border-radius:8px;bottom:20px;box-shadow:0 12px 35px #0004;color:#fff;font-size:12px;opacity:0;padding:10px 14px;pointer-events:none;position:fixed;right:20px;transform:translateY(10px);transition:.2s;z-index:1200}.copy-toast.show{opacity:1;transform:translateY(0)}@media(max-width:1100px){.system-variable-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){.system-variable-grid,.system-example-grid{grid-template-columns:1fr}.system-reference-hero{align-items:flex-start;flex-direction:column;gap:14px}.system-reference{padding:16px!important}}
.source-editor-actions{align-items:center;background:#eef2f5;border:1px solid #d9e0e5;border-radius:0 0 8px 8px;display:flex;flex-wrap:wrap;gap:10px;padding:12px}.source-explorer{background:#f8fafc;border-left:1px solid #dee2e6;min-width:0}.source-explorer>header{border-bottom:1px solid #dee2e6;padding:17px 14px}.source-explorer>header small{color:#6c757d;display:block;font:11px ui-monospace,monospace;margin:4px 0 0 24px;overflow-wrap:anywhere}.source-file-list{max-height:680px;overflow:auto;padding:8px}.source-file{align-items:flex-start;background:transparent;border:0;border-radius:6px;color:#34495e;display:flex;font:12px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace;gap:8px;padding:8px;text-align:left;width:100%}.source-file:hover{background:#e9eef3;color:#0d6efd}.source-file.active{background:#dce9fb;color:#084298;font-weight:700}.source-file span{flex:1;min-width:0;overflow-wrap:anywhere}.source-file>small{background:#e7ebef;border-radius:3px;color:#687887;font-size:8px;padding:2px 4px}.source-file i{flex:0 0 auto;margin-top:1px}.source-file-html i{color:#e34c26}.source-file-css i{color:#264de4}.source-file-js i{color:#b59b00}.source-file-json i{color:#6f42c1}.source-feedback-success{color:#146c43}.source-feedback-error{color:#b02a37}.source-editor-pane.is-fullscreen{background:#101820;inset:0;padding:14px;position:fixed;z-index:1090}.source-editor-pane.is-fullscreen .source-editor-shell{flex:1;height:auto;max-height:none}.source-editor-pane.is-fullscreen .source-editor-actions{border-radius:0}.source-editor-pane.is-fullscreen #sourceFeedback{background:#eef2f5;margin:0!important;padding:4px 12px}.source-fullscreen-lock{overflow:hidden}@media(max-width:900px){.source-workspace{grid-template-columns:1fr}.source-explorer{border-bottom:1px solid #dee2e6;border-left:0;grid-row:1;max-height:240px}.source-file-list{max-height:175px}.source-editor-pane{grid-row:2}.source-editor-shell{height:500px}.source-tool-button span{display:none}}@media(max-width:576px){.source-editor-intro{align-items:flex-start;flex-direction:column}.source-editor-actions .btn,.source-editor-actions a{width:100%}.source-editor-toolbar{align-items:flex-start;flex-direction:column}.source-toolbar-actions{width:100%}}
</style>
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
