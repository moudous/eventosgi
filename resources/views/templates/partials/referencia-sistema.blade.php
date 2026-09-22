            @php
                $catalogoSistema = [
                    ['titulo' => 'Evento', 'icone' => 'bi-calendar-event', 'descricao' => 'Dados básicos do evento atual.', 'campos' => ['evento.id', 'evento.nome', 'evento.ativo', 'evento.criado_em']],
                    ['titulo' => 'Atividades', 'icone' => 'bi-list-check', 'descricao' => 'Use dentro de “for atividade in atividades”.', 'campos' => ['atividade.id', 'atividade.nome', 'atividade.subtitulo', 'atividade.local', 'atividade.hora_inicio', 'atividade.hora_fim', 'atividade.dia', 'atividade.hands_on', 'atividade.inscricao_geral', 'atividade.formato', 'atividade.modalidade', 'atividade.mostrar_link_transmissao', 'atividade.tipo_link_transmissao', 'atividade.iframe_transmissao', 'atividade.url_transmissao', 'atividade.miniatura_transmissao', 'atividade.data_inicio', 'atividade.data_fim', 'atividade.data_inicio_iso', 'atividade.data_fim_iso', 'atividade.categoria', 'atividade.categoria_id', 'atividade.convidados', 'atividade.sessoes', 'atividade.pode_inscrever', 'atividade.lista_reserva', 'atividade.inscricao_rotulo', 'atividade.url_inscricao', 'atividade.shortcode']],
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
