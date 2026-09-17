/* Criador de templates: buffers independentes mantêm edições ao trocar de arquivo. */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('templateBuild');
    if (!root) return;
    const el = id => document.getElementById(id);
    const base = root.dataset.base.replace(/\/$/, '');
    const editor = el('buildEditor'), destaque = el('buildHighlight');
    const buffers = new Map();
    let validacao = null;
    let build = null, atual = '', pasta = '', selecionado = null, ocupado = false, uploads = [];
    const modal = id => bootstrap.Modal.getOrCreateInstance(el(id));
    const feedback = (texto, erro = false) => {
        el('buildFeedback').classList.remove('d-none');
        el('buildFeedback').textContent = texto;
        el('buildFeedback').className = 'alert alert-' + (erro ? 'danger' : 'success');
    };
    const request = async (url, method = 'GET', dados = null) => {
        const headers = {Accept: 'application/json', 'X-CSRF-TOKEN': root.dataset.token};
        const options = {method, headers, credentials: 'same-origin'};
        if (dados instanceof FormData) options.body = dados;
        else if (dados !== null) { headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(dados); }
        const resposta = await fetch(url, options);
        const json = await resposta.json().catch(() => ({}));
        if (!resposta.ok) throw new Error(Object.values(json.errors || {}).flat().join(' ') || json.message || 'Não foi possível concluir a operação.');
        return json;
    };
    const url = sufixo => `${base}/templates/${build.id}/${sufixo}`;
    const pendentes = () => Object.fromEntries([...buffers].filter(([, b]) => b.texto !== b.original).map(([nome, b]) => [nome, b.texto]));
    const temPendentes = () => Object.keys(pendentes()).length > 0;
    const ocupar = valor => {
        ocupado = valor;
        document.querySelectorAll('[data-mutate]').forEach(b => b.disabled = valor);
        editor.readOnly = valor;
        el('buildValidatePaths').disabled = valor || editor.disabled || !atual;
        el('buildVariables').querySelectorAll('input,select,textarea,button').forEach(b => b.disabled = valor);
        el('buildUploadModal').querySelectorAll('input,select,button').forEach(b => b.disabled = valor);
    };
    const executar = async acao => {
        if (ocupado) return;
        ocupar(true);
        try { await acao(); } catch (erro) { feedback(erro.message, true); el('buildUploadFeedback').textContent = erro.message; }
        finally { ocupar(false); }
    };
    const elemento = (tag, classe, texto) => {
        const node = document.createElement(tag);
        if (classe) node.className = classe;
        if (texto !== undefined) node.textContent = texto;
        return node;
    };
    const botaoIcone = (icone, titulo, acao) => {
        const b = elemento('button', 'btn btn-sm btn-outline-secondary'); b.type = 'button'; b.title = titulo; b.setAttribute('aria-label', titulo);
        const i = elemento('i', 'bi ' + icone); i.setAttribute('aria-hidden', 'true'); b.append(i); b.addEventListener('click', acao); return b;
    };
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
    const sincronizarLinhas = () => {
        el('buildLineNumbers').style.bottom = `${editor.offsetHeight - editor.clientHeight}px`;
        el('buildLineNumbers').scrollTop = editor.scrollTop;
        el('buildWarningLines').style.transform = `translateY(${-editor.scrollTop}px)`;
    };
    const limparValidacao = () => {
        validacao = null;
        el('buildValidationStatus').classList.add('d-none');
    };
    const numerarLinhas = () => {
        if (validacao && (validacao.arquivo !== atual || validacao.codigo !== editor.value)) {
            limparValidacao();
        }
        const gutter = el('buildLineNumbers'), overlays = el('buildWarningLines');
        const fragmento = document.createDocumentFragment(), marcas = document.createDocumentFragment();
        const porLinha = new Map();
        (validacao?.avisos || []).forEach(aviso => {
            if (!porLinha.has(aviso.linha)) porLinha.set(aviso.linha, []);
            porLinha.get(aviso.linha).push(aviso.mensagem);
        });
        const altura = parseFloat(getComputedStyle(editor).lineHeight) || 20.15;
        for (let linha = 1, total = editor.value.split('\n').length; linha <= total; linha++) {
            const mensagens = porLinha.get(linha);
            const row = elemento('div', 'build-line-number' + (mensagens ? ' has-warning' : ''));
            row.dataset.line = linha;
            if (mensagens) {
                const descricao = `Linha ${linha}: ` + mensagens.join('\n');
                const aviso = botaoIcone('bi-exclamation-triangle-fill', descricao, () => {
                    const inicio = editor.value.split('\n').slice(0, linha - 1).reduce((n, texto) => n + texto.length + 1, 0);
                    editor.focus(); editor.setSelectionRange(inicio, inicio);
                    editor.scrollTop = Math.max(0, (linha - 3) * altura); editor.onscroll();
                });
                aviso.className = 'build-line-warning'; row.append(aviso); row.title = descricao;
                const marca = elemento('div', 'build-warning-line'); marca.style.top = `${18 + (linha - 1) * altura}px`; marcas.append(marca);
            }
            const numero = elemento('span', '', String(linha)); numero.setAttribute('aria-hidden', 'true'); row.append(numero); fragmento.append(row);
        }
        gutter.replaceChildren(fragmento); overlays.replaceChildren(marcas); sincronizarLinhas();
    };
    const cores = () => {
        destaque.querySelector('code').innerHTML = realcar(editor.value) + (editor.value.endsWith('\n') ? ' ' : '');
        destaque.scrollTop = editor.scrollTop; destaque.scrollLeft = editor.scrollLeft;
        numerarLinhas();
    };
    const estadoEditor = () => {
        const total = Object.keys(pendentes()).length;
        el('buildDirty').textContent = total ? `${total} arquivo(s) com alterações não salvas` : 'Todos os arquivos salvos';
        const buffer = buffers.get(atual);
        el('buildFileState').textContent = buffer ? (buffer.texto === buffer.original ? 'Salvo' : 'Não salvo') : '';
        el('buildTree').querySelectorAll('[data-file]').forEach(b => {
            const buf = buffers.get(b.dataset.file);
            b.querySelector('small').textContent = buf && buf.texto !== buf.original ? '●' : '';
        });
    };
    const carregarBuffer = async nome => {
        if (!buffers.has(nome)) {
            const dados = await request(url('arquivo') + '?arquivo=' + encodeURIComponent(nome));
            buffers.set(nome, {texto: dados.conteudo, original: dados.conteudo});
        }
        return buffers.get(nome);
    };
    const abrirArquivo = async nome => {
        const arquivo = build.arquivos.find(a => a.nome === nome);
        if (!arquivo) return;
        limparValidacao();
        atual = nome; selecionado = {nome, tipo: 'arquivo'}; pasta = nome.includes('/') ? nome.slice(0, nome.lastIndexOf('/')) : '';
        el('buildCurrentFile').textContent = nome;
        el('buildBinary').replaceChildren();
        el('buildBinary').classList.toggle('d-none', arquivo.editavel);
        editor.closest('.source-editor-shell').classList.toggle('d-none', !arquivo.editavel);
        el('buildSaveFile').classList.toggle('d-none', !arquivo.editavel);
        editor.disabled = !arquivo.editavel;
        if (arquivo.editavel) { editor.value = (await carregarBuffer(nome)).texto; cores(); editor.scrollTop = editor.scrollLeft = 0; }
        else {
            const asset = url('assets/') + nome.split('/').map(encodeURIComponent).join('/');
            if (/\.(png|jpe?g|gif|webp|avif|ico)$/i.test(nome)) { const img = elemento('img'); img.src = asset; img.alt = nome; el('buildBinary').append(img); }
            const link = elemento('a', 'btn btn-outline-primary d-block mt-3', 'Visualizar arquivo'); link.href = asset; link.target = '_blank'; link.rel = 'noopener'; el('buildBinary').append(link);
        }
        arvore(); estadoEditor();
    };
    const limparEditor = () => {
        limparValidacao();
        atual = ''; editor.value = ''; editor.disabled = true; cores(); el('buildCurrentFile').textContent = 'Selecione um arquivo'; el('buildBinary').replaceChildren(); estadoEditor();
    };
    const arvore = () => {
        if (validacao) { limparValidacao(); numerarLinhas(); }
        const tree = el('buildTree'); tree.replaceChildren();
        const desenhar = (destino, nivel) => {
            const folder = elemento('button', 'build-tree-item' + (selecionado?.tipo === 'pasta' && selecionado.nome === destino ? ' active' : ''));
            folder.type = 'button'; folder.style.paddingLeft = (nivel * 16 + 7) + 'px'; folder.dataset.folder = destino;
            folder.append(elemento('i', 'bi bi-folder2-open'), elemento('span', '', destino ? destino.split('/').pop() : '/'));
            folder.onclick = () => { if (ocupado) return; pasta = destino; selecionado = {nome: destino, tipo: 'pasta'}; arvore(); };
            folder.ondragover = e => { if (ocupado) return; e.preventDefault(); e.dataTransfer.dropEffect = 'move'; folder.classList.add('drop-target'); };
            folder.ondragleave = () => folder.classList.remove('drop-target');
            folder.ondrop = e => {
                e.preventDefault(); folder.classList.remove('drop-target');
                const origem = e.dataTransfer.getData('application/x-template-file');
                if (!origem || !build.arquivos.some(a => a.nome === origem)) return;
                const alvo = (destino ? destino + '/' : '') + origem.split('/').pop();
                if (origem === alvo) return;
                executar(async () => {
                    build = await request(url('estrutura'), 'POST', {acao: 'mover', nome: origem, destino: alvo});
                    if (buffers.has(origem)) { buffers.set(alvo, buffers.get(origem)); buffers.delete(origem); }
                    if (atual === origem) { atual = alvo; el('buildCurrentFile').textContent = alvo; }
                    pasta = destino; selecionado = {nome: alvo, tipo: 'arquivo'}; arvore(); estadoEditor(); feedback('Arquivo movido. Atualize os caminhos no código, se necessário.');
                });
            };
            tree.append(folder);
            build.pastas.filter(p => (p.includes('/') ? p.slice(0, p.lastIndexOf('/')) : '') === destino).forEach(p => desenhar(p, nivel + 1));
            build.arquivos.filter(a => (a.nome.includes('/') ? a.nome.slice(0, a.nome.lastIndexOf('/')) : '') === destino).forEach(a => {
                const b = elemento('div', 'build-tree-item' + (selecionado?.nome === a.nome && selecionado.tipo === 'arquivo' ? ' active' : ''));
                b.setAttribute('role', 'button'); b.tabIndex = 0; b.dataset.file = a.nome; b.style.paddingLeft = ((nivel + 1) * 16 + 7) + 'px'; b.draggable = !['template.json', 'index.html'].includes(a.nome);
                b.append(elemento('i', 'bi ' + (a.editavel ? 'bi-file-earmark-code' : 'bi-file-earmark')), elemento('span', '', a.nome.split('/').pop()), elemento('small'));
                b.ondragstart = e => { if (ocupado) { e.preventDefault(); return; } e.dataTransfer.setData('application/x-template-file', a.nome); e.dataTransfer.effectAllowed = 'move'; };
                b.onclick = () => executar(() => abrirArquivo(a.nome));
                b.onkeydown = e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); b.click(); } }; tree.append(b);
            });
        };
        desenhar('', 0); estadoEditor();
    };
    const atualizarCabecalho = () => { el('buildName').textContent = build.manifesto.nome; el('buildVersion').textContent = 'v' + build.manifesto.versao; };
    const manifestoAtual = () => JSON.parse(buffers.get('template.json')?.texto || JSON.stringify(build.manifesto));
    const atualizarManifesto = manifesto => {
        const buffer = buffers.get('template.json');
        buffer.texto = JSON.stringify(manifesto, null, 4) + '\n';
        if (atual === 'template.json') { editor.value = buffer.texto; cores(); }
        estadoEditor();
    };
    const variaveis = async () => {
        await carregarBuffer('template.json');
        const m = manifestoAtual(); m.variaveis ??= [];
        el('buildManifestName').value = m.nome || ''; el('buildDescription').value = m.descricao || '';
        const rows = el('buildVariableRows'); rows.replaceChildren();
        if (!Array.isArray(m.variaveis)) throw new Error('O campo variaveis do template.json deve ser uma lista.');
        m.variaveis.forEach((v, indice) => {
            const row = elemento('div', 'build-variable-row');
            for (const [campo, rotulo] of [['nome','Nome da variável'],['rotulo','Rótulo'],['tipo','Tipo'],['padrao','Valor padrão']]) {
                const group = elemento('div'); const label = elemento('label', 'form-label small', rotulo);
                const input = elemento(campo === 'tipo' ? 'select' : 'input', campo === 'tipo' ? 'form-select' : 'form-control'); input.id = `build-var-${indice}-${campo}`; label.htmlFor = input.id;
                if (campo === 'tipo') for (const tipo of ['text','textarea','url','color','checkbox']) input.add(new Option(tipo, tipo));
                input.value = v[campo] || (campo === 'tipo' ? 'text' : '');
                input.oninput = () => { if (ocupado) return; const novo = manifestoAtual(); novo.variaveis[indice][campo] = input.value; atualizarManifesto(novo); };
                group.append(label, input); row.append(group);
            }
            row.append(botaoIcone('bi-trash', 'Remover variável', () => executar(async () => { const novo = manifestoAtual(); novo.variaveis.splice(indice, 1); atualizarManifesto(novo); await variaveis(); })));
            rows.append(row);
        });
    };
    const adotar = async dados => {
        build = dados; buffers.clear(); pasta = ''; selecionado = null;
        el('buildEmpty').classList.add('d-none');
        el('buildActions').classList.remove('d-none');
        el('buildCard').classList.remove('d-none');
        uploads.forEach(item => { if (item.preview) URL.revokeObjectURL(item.preview); }); uploads = [];
        el('buildUploadList').replaceChildren();
        const endereco = new URL(location.href); endereco.searchParams.set('build', build.id); history.replaceState(null, '', endereco);
        atualizarCabecalho(); await carregarBuffer('template.json'); await abrirArquivo('index.html');
        if (el('buildVariables').classList.contains('active')) await variaveis();
    };
    const salvar = async (todos = true) => {
        if (!build) throw new Error('Abra ou crie um template.');
        const arquivos = todos ? pendentes() : (buffers.has(atual) ? {[atual]: buffers.get(atual).texto} : {});
        if (Object.keys(arquivos).length) {
            build = await request(url('arquivos'), 'PUT', {arquivos});
            Object.entries(arquivos).forEach(([nome, texto]) => buffers.get(nome).original = texto);
            atualizarCabecalho(); estadoEditor();
        }
        feedback(todos ? 'Template salvo. Todos os arquivos estão atualizados.' : 'Arquivo salvo.');
    };
    const podeTrocar = () => (!temPendentes() && !uploads.length) || confirm('Há edições ou uploads pendentes. Deseja descartá-los e trocar de template?');
    el('buildSaveAll').onclick = () => executar(() => salvar());
    el('buildSaveFile').onclick = () => executar(() => salvar(false));
    el('buildVersionSave').onclick = () => executar(async () => {
        if (!build) throw new Error('Abra ou crie um template.');
        await adotar(await request(url('versao'), 'POST', {arquivos: pendentes()}));
        feedback('Nova versão ' + build.manifesto.versao + ' salva. A versão anterior foi preservada.');
    });
    el('buildPreview').onclick = () => executar(async () => {
        await salvar(); el('buildPreviewFrame').src = url('visualizar') + '?v=' + Date.now(); modal('buildPreviewModal').show();
    });
    el('buildPreviewModal').addEventListener('hidden.bs.modal', () => el('buildPreviewFrame').src = 'about:blank');
    el('buildExport').onclick = () => executar(async () => { await salvar(); const a = elemento('a'); a.href = url('exportar'); a.download = ''; document.body.append(a); a.click(); a.remove(); feedback('Template salvo. Download do ZIP iniciado.'); });
    el('buildCreate').onclick = () => executar(async () => {
        if (!podeTrocar()) return;
        await adotar(await request(base + '/templates', 'POST', {nome: el('buildNewName').value.trim()}));
        modal('buildNewModal').hide(); feedback('Template criado com os quatro arquivos iniciais.');
    });
    $('#buildSelect').select2({theme: 'bootstrap-5', dropdownParent: $('#buildOpenModal'), placeholder: 'Digite o nome ou selecione um template', ajax: {url: base + '/templates', dataType: 'json', delay: 250, data: p => ({q: p.term || '', page: p.page || 1}), processResults: d => d}});
    el('buildOpen').onclick = () => executar(async () => {
        const id = $('#buildSelect').val(); if (!id) throw new Error('Selecione um template.');
        if (!podeTrocar()) return;
        await adotar(await request(base + '/templates/' + encodeURIComponent(id))); modal('buildOpenModal').hide(); feedback('Template carregado.');
    });
    editor.oninput = () => { if (!ocupado && buffers.has(atual)) { buffers.get(atual).texto = editor.value; cores(); estadoEditor(); } };
    editor.onscroll = () => { destaque.scrollTop = editor.scrollTop; destaque.scrollLeft = editor.scrollLeft; sincronizarLinhas(); };
    editor.onkeydown = e => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); executar(() => salvar(e.shiftKey)); }
        if (e.key === 'Tab' && !ocupado) { e.preventDefault(); editor.setRangeText('    ', editor.selectionStart, editor.selectionEnd, 'end'); editor.dispatchEvent(new Event('input')); }
    };
    el('buildValidatePaths').onclick = () => executar(async () => {
        if (!build || !atual || editor.disabled) return;
        build = await request(base + '/templates/' + encodeURIComponent(build.id));
        arvore();
        const avisos = TemplateAssetPaths.validar(editor.value, atual, build.arquivos.map(a => a.nome));
        validacao = {arquivo: atual, codigo: editor.value, avisos};
        numerarLinhas();
        const status = el('buildValidationStatus');
        status.className = 'small mt-2 ' + (avisos.length ? 'text-warning-emphasis' : 'text-success');
        status.textContent = avisos.length
            ? `${avisos.length} referência(s) não encontrada(s). Passe o mouse sobre os avisos nas linhas para ver os caminhos.`
            : 'Nenhum caminho inexistente encontrado nas referências locais estáticas deste arquivo.';
    });
    el('buildFullscreen').onclick = () => { const ativo = el('buildEditorPane').classList.toggle('is-fullscreen'); document.body.classList.toggle('source-fullscreen-lock', ativo); };
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { el('buildEditorPane').classList.remove('is-fullscreen'); document.body.classList.remove('source-fullscreen-lock'); } });
    const novoItem = async tipo => {
        if (!build) throw new Error('Abra ou crie um template.');
        const nome = prompt(tipo === 'arquivo' ? 'Nome do arquivo (ex.: pagina.html):' : 'Nome da pasta (sem espaços ou acentos):');
        if (!nome) return;
        const caminho = (pasta ? pasta + '/' : '') + nome.trim();
        build = await request(url('estrutura'), 'POST', {acao: 'criar-' + tipo, nome: caminho});
        if (tipo === 'arquivo') await abrirArquivo(caminho);
        else { pasta = caminho; selecionado = {nome: caminho, tipo: 'pasta'}; arvore(); }
        feedback(tipo === 'arquivo' ? 'Arquivo criado.' : 'Pasta criada.');
    };
    el('buildAddFile').onclick = () => executar(() => novoItem('arquivo'));
    el('buildAddFolder').onclick = () => executar(() => novoItem('pasta'));
    const remover = async tipo => {
        if (!selecionado || selecionado.tipo !== tipo || !selecionado.nome) throw new Error('Selecione ' + (tipo === 'arquivo' ? 'um arquivo.' : 'uma pasta vazia.'));
        const nome = selecionado.nome;
        if (!confirm(`Remover ${nome}?${buffers.get(nome)?.texto !== buffers.get(nome)?.original ? ' As edições não salvas serão descartadas.' : ''}`)) return;
        build = await request(url('estrutura'), 'POST', {acao: 'remover-' + tipo, nome});
        buffers.delete(nome); if (atual === nome) limparEditor(); pasta = ''; selecionado = null; arvore(); feedback('Item removido.');
    };
    el('buildDeleteFile').onclick = () => executar(() => remover('arquivo'));
    el('buildDeleteFolder').onclick = () => executar(() => remover('pasta'));
    el('buildRefreshTree').onclick = () => executar(async () => {
        if (!build) return;
        build = await request(base + '/templates/' + encodeURIComponent(build.id));
        if (pasta && !build.pastas.includes(pasta)) pasta = '';
        if (selecionado && !(selecionado.tipo === 'pasta'
            ? selecionado.nome === '' || build.pastas.includes(selecionado.nome)
            : build.arquivos.some(a => a.nome === selecionado.nome))) selecionado = null;
        arvore(); atualizarCabecalho();
        feedback('Lista de arquivos atualizada. As edições abertas foram preservadas.');
    });
    document.querySelector('[data-bs-target="#buildVariables"]').addEventListener('shown.bs.tab', () => executar(variaveis));
    for (const [id, campo] of [['buildManifestName','nome'], ['buildDescription','descricao']]) el(id).oninput = () => { if (!build || ocupado) return; const m = manifestoAtual(); m[campo] = el(id).value; atualizarManifesto(m); };
    el('buildAddVariable').onclick = () => executar(async () => {
        const m = manifestoAtual(); m.variaveis ||= []; let i = 1; while (m.variaveis.some(v => v.nome === 'variavel_' + i)) i++;
        m.variaveis.push({nome: 'variavel_' + i, rotulo: 'Nova variável', tipo: 'text', padrao: ''}); atualizarManifesto(m); await variaveis();
    });
    document.querySelectorAll('[data-copy-expression], .system-copy-example').forEach(b => b.onclick = () => executar(async () => {
        const expr = b.dataset.copyExpression;
        const texto = expr ? (b.classList.contains('system-token-collection') ? expr : '{{ ' + expr + ' }}') : b.closest('.system-example').querySelector('code').textContent;
        await navigator.clipboard.writeText(texto); feedback('Código copiado.');
    }));
    const opcoesPastas = (select, valor = '') => {
        select.replaceChildren(new Option('/ (raiz)', ''));
        (build?.pastas || []).forEach(p => select.add(new Option('/' + p, p)));
        select.value = build?.pastas.includes(valor) ? valor : '';
    };
    const aleatorio = ext => {
        const bytes = crypto.getRandomValues(new Uint8Array(4)); const hash = [...bytes].map(b => b.toString(16).padStart(2, '0')).join('').slice(0, 7);
        return (/^(png|jpe?g|gif|webp|svg|avif|ico)$/.test(ext) ? 'img' : ext) + '_' + hash;
    };
    const desenharUploads = () => {
        const list = el('buildUploadList'); list.replaceChildren();
        uploads.forEach(item => {
            const row = elemento('div', 'build-upload-row');
            if (item.preview) { const img = elemento('img'); img.src = item.preview; img.alt = item.file.name; row.append(img); }
            else row.append(elemento('i', 'bi bi-file-earmark fs-1 text-secondary'));
            const grupo = elemento('div'); const label = elemento('label', 'form-label small', 'Nome do arquivo');
            const input = elemento('input', 'form-control'); input.id = 'upload-' + item.id; label.htmlFor = input.id; input.value = item.nome;
            input.oninput = () => item.nome = input.value;
            const random = botaoIcone('bi-shuffle', 'Sugerir nome aleatório', () => { item.nome = aleatorio(item.ext) + '.' + item.ext; input.value = item.nome; });
            const nomes = elemento('div', 'd-flex gap-1'); nomes.append(input, random);
            grupo.append(label, nomes, elemento('small', 'text-secondary d-block', item.file.name + ' · ' + Math.ceil(item.file.size / 1024) + ' KB')); row.append(grupo);
            const groupFolder = elemento('div'); const folderLabel = elemento('label', 'form-label small', 'Pasta de destino'); const select = elemento('select', 'form-select'); select.id = 'upload-folder-' + item.id; folderLabel.htmlFor = select.id; opcoesPastas(select, item.pasta); item.pasta = select.value; select.onchange = () => item.pasta = select.value; groupFolder.append(folderLabel, select); row.append(groupFolder);
            row.append(botaoIcone('bi-x-lg', 'Remover da lista de envio', () => { if (item.preview) URL.revokeObjectURL(item.preview); uploads = uploads.filter(x => x !== item); desenharUploads(); })); list.append(row);
        });
    };
    const adicionarUploads = files => {
        if (ocupado || !build) return;
        const permitidas = JSON.parse(root.dataset.extensoes); const erros = [];
        for (const file of files) {
            const ext = file.name.split('.').pop().toLowerCase();
            if (!file.name.includes('.') || !permitidas.includes(ext)) { erros.push(file.name + ': extensão não permitida.'); continue; }
            const limite = ['html','css','js','json','svg','txt','md'].includes(ext) ? 2000000 : 20 * 1024 * 1024;
            if (file.size > limite) { erros.push(file.name + ': tamanho acima do limite.'); continue; }
            const slug = file.name.slice(0, -(ext.length + 1)).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 100);
            let nome = (slug || aleatorio(ext)) + '.' + ext; const destino = el('buildUploadFolder').value;
            const caminho = n => (destino ? destino + '/' : '') + n;
            if (build.arquivos.some(a => a.nome === caminho(nome)) || uploads.some(u => u.nome === nome && u.pasta === destino)) nome = aleatorio(ext) + '.' + ext;
            uploads.push({id: crypto.randomUUID(), file, ext, nome, pasta: destino, preview: /^image\//.test(file.type) ? URL.createObjectURL(file) : null});
        }
        el('buildUploadFeedback').textContent = erros.join(' '); desenharUploads();
    };
    el('buildUploadModal').addEventListener('show.bs.modal', e => { if (!build) { e.preventDefault(); feedback('Abra ou crie um template.', true); return; } opcoesPastas(el('buildUploadFolder'), pasta); desenharUploads(); });
    el('buildUploadInput').onchange = e => { adicionarUploads(e.target.files); e.target.value = ''; };
    const dropzone = el('buildDropzone');
    dropzone.onclick = e => { if (e.target === dropzone || e.target.tagName === 'P') dropzone.focus(); };
    dropzone.onpaste = e => { if (e.clipboardData.files.length) { e.preventDefault(); adicionarUploads(e.clipboardData.files); } };
    dropzone.ondragover = e => { e.preventDefault(); dropzone.classList.add('dragover'); };
    dropzone.ondragleave = () => dropzone.classList.remove('dragover');
    dropzone.ondrop = e => { e.preventDefault(); dropzone.classList.remove('dragover'); adicionarUploads(e.dataTransfer.files); };
    el('buildSendUploads').onclick = () => executar(async () => {
        if (!uploads.length) throw new Error('Escolha ou cole arquivos na área de envio.');
        while (uploads.length) {
            const item = uploads[0]; const dados = new FormData(); dados.append('arquivo', item.file); dados.append('nome', (item.pasta ? item.pasta + '/' : '') + item.nome);
            build = await request(url('upload'), 'POST', dados); uploads.shift(); if (item.preview) URL.revokeObjectURL(item.preview);
            desenharUploads(); ocupar(true); arvore();
        }
        el('buildUploadFeedback').textContent = 'Todos os arquivos foram enviados.'; feedback('Arquivos enviados.');
    });
    window.addEventListener('beforeunload', e => { if (temPendentes() || uploads.length || ocupado) { e.preventDefault(); e.returnValue = ''; } });
    executar(async () => {
        const id = new URL(location.href).searchParams.get('build');
        if (id) { await adotar(await request(base + '/templates/' + encodeURIComponent(id))); feedback('Template carregado.'); }
        else if (root.dataset.temTemplates === '1') {
            el('buildFeedback').classList.add('d-none');
            el('buildEmpty').classList.remove('d-none');
        }
        else { await adotar(await request(base + '/templates', 'POST', {nome: ('Template ' + root.dataset.evento).slice(0,150)})); feedback('Template inicial criado. Você pode editar os arquivos ou abrir outro template em construção.'); }
    });
});
