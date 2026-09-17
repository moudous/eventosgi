@extends('layouts.app')
@section('title','Construtor de formulário')
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3"><div><h1 class="page-title">Formulário de inscrições</h1><p class="page-description mb-0">{{ $atividade->nome }}</p></div><a href="{{ route('atividades.index') }}" class="btn btn-outline-secondary">Voltar</a></div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@error('formulario')<div class="alert alert-danger">{{ $message }}</div>@enderror
@php
    $usarUrl = (bool) old('usar_url', filled($atividade->url));
    $slugSugerido = \Illuminate\Support\Str::slug($atividade->formulario['titulo'] ?? $atividade->nome) ?: 'atividade-'.$atividade->id;
    $urlAtividade = old('url', $atividade->url ?? $slugSugerido);
@endphp
<form method="POST" action="{{ route('atividades.formulario.salvar',$atividade) }}" id="formBuilder">@csrf<input type="hidden" name="formulario" id="formularioJson">
<div class="card content-card mb-4"><div class="card-header"><h2 class="h5 fw-bold mb-0">Configuração</h2></div><div class="card-body"><div class="row g-3">
<div class="col-md-6"><label class="form-label">Título</label><input id="titulo" class="form-control"></div><div class="col-md-6"><label class="form-label">Subtítulo</label><input id="subtitulo" class="form-control"></div>
<div class="col-12"><div class="row g-3 align-items-end">
    <div class="col-12 col-lg-10"><label class="form-label" for="url_atividade">URL da atividade</label><input type="hidden" name="usar_url" value="0"><div class="input-group has-validation"><div class="input-group-text"><input class="form-check-input mt-0 me-2" type="checkbox" name="usar_url" value="1" id="usar_url" @checked($usarUrl)><label class="mb-0 text-nowrap" for="usar_url">Usar esta URL</label></div><span class="input-group-text">{{ url('/a') }}/</span><input class="form-control @error('url') is-invalid @enderror" id="url_atividade" name="url" value="{{ $urlAtividade }}" maxlength="180" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" @disabled(!$usarUrl) aria-describedby="url_atividade_ajuda"><button class="btn btn-outline-secondary" type="button" id="copiar_url_atividade" title="Copiar URL" aria-label="Copiar URL" @disabled(!$usarUrl)><i class="bi bi-clipboard"></i></button>@error('url')<div class="invalid-feedback">{{ $message }}</div>@enderror</div><div class="form-text" id="url_atividade_ajuda">O trecho final é sugerido pelo título. Ele deve ser único e pode ser personalizado.</div></div>
    <div class="col-12 col-lg-2"><label class="form-label" for="abrirRastreios">Origem/rastreio</label><button class="btn btn-outline-primary w-100 text-nowrap" type="button" id="abrirRastreios" data-bs-toggle="modal" data-bs-target="#rastreiosModal"><i class="bi bi-signpost-split me-1"></i><span id="totalRastreios">00 rastreios</span></button><div class="form-text">Crie URLs por canal.</div></div>
</div></div>
<div class="col-md-3"><label class="form-label">Abertura (horário de São Paulo)</label><input id="abertura" type="datetime-local" class="form-control"></div><div class="col-md-3"><label class="form-label">Fechamento (horário de São Paulo)</label><input id="fechamento" type="datetime-local" class="form-control"></div><div class="col-md-6"><label class="form-label">Mensagem antes de abrir</label><input id="mensagem_antes" class="form-control"></div>
<div class="col-md-6"><label class="form-label">Mensagem quando fechado</label><input id="mensagem_fechado" class="form-control"></div><div class="col-md-6"><label class="form-label">Mensagem após inscrição</label><input id="mensagem_sucesso" class="form-control" value="Inscrição realizada com sucesso."></div>
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="editor_exibir"><label class="form-check-label" for="editor_exibir">Exibir editor</label></div><div class="form-text">Quando marcado, o conteúdo aparece logo abaixo do cabeçalho do formulário público.</div></div>
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="registrar_presenca_qrcode"><label class="form-check-label" for="registrar_presenca_qrcode">Registrar presença via QR Code</label></div><div class="form-text">Gera um código individual no comprovante de cada participante para validação da presença.</div></div>
<div class="col-12"><label class="form-label fw-semibold">Conteúdo do editor</label><div class="editor-wrapper"><div id="editorConteudo"></div></div><div class="form-text">As imagens são enviadas para uma pasta pública própria deste formulário.</div></div>
<div class="col-12"><label class="form-label" for="mensagem_ja_inscrito">Mensagem para quem já se inscreveu</label><textarea id="mensagem_ja_inscrito" maxlength="2000" class="form-control" rows="2"></textarea><div class="form-text">Cada participante pode se inscrever uma única vez nesta atividade.</div></div>
<div class="col-12"><label class="form-label" for="mensagem_identificacao">Mensagem da etapa de identificação</label><textarea id="mensagem_identificacao" maxlength="2000" class="form-control" rows="2"></textarea><div class="form-text">Texto de apoio exibido antes de o visitante informar o e-mail e entrar ou solicitar uma senha temporária.</div></div>
</div></div></div>
<div class="card content-card mb-4"><div class="card-header"><h2 class="h5 fw-bold mb-0">Limitação de vagas</h2></div><div class="card-body"><div class="row g-3">
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="limitar_inscricoes"><label class="form-check-label" for="limitar_inscricoes">Limitar inscrições</label></div></div>
<div class="col-md-3" id="limiteContainer"><label class="form-label" for="limite_inscricoes">Quantidade total de vagas</label><div class="input-group"><input id="limite_inscricoes" type="number" min="1" step="1" class="form-control"><span class="input-group-text" id="contadorVagas" title="Inscrições utilizadas de vagas disponíveis">0/0</span></div><div class="form-text">Limite total, incluindo as inscrições já realizadas.</div></div>
<div class="col-md-4" id="aposEncerrarVagasContainer"><label class="form-label" for="apos_encerrar_vagas">Após encerrar as vagas</label><select class="form-select" id="apos_encerrar_vagas"><option value="encerrar">Encerrar inscrição e mostrar a mensagem de vagas esgotadas</option><option value="lista_reserva">Permitir inscrições além do limite</option></select></div>
<div class="col-md-5" id="listaReservaContainer"><label class="form-label" for="limite_lista_reserva">Quantidade de inscrições além do limite</label><div class="input-group"><input id="limite_lista_reserva" type="number" min="1" step="1" class="form-control"><div class="input-group-text"><input class="form-check-input mt-0 me-2" type="checkbox" id="lista_reserva_sem_limite"><label class="mb-0 text-nowrap" for="lista_reserva_sem_limite">Sem limite</label></div></div><div class="form-text">Essas inscrições ficarão identificadas como lista de reserva.</div></div>
<div class="col-md-9" id="mensagemVagasContainer"><label class="form-label" for="mensagem_vagas_esgotadas">Mensagem quando as vagas acabarem</label><textarea id="mensagem_vagas_esgotadas" maxlength="2000" class="form-control" rows="2"></textarea></div>
<div class="col-12" id="mostrarVagasContainer"><div class="form-check"><input class="form-check-input" type="checkbox" id="mostrar_vagas_restantes"><label class="form-check-label" for="mostrar_vagas_restantes">Mostrar vagas restantes no formulário</label></div><div class="form-text">Exibe no formulário público o total e a disponibilidade das opções usadas como critérios de vagas.</div></div>
<div class="col-12" id="criteriosVagasContainer"><hr><h3 class="h6">Ordem dos critérios de distribuição</h3><p class="small text-muted">Os percentuais do segundo critério são aplicados sobre as cotas do primeiro, e assim sucessivamente. Cada critério deve ser selecionado uma única vez.</p><div class="row g-3" id="criteriosVagas"></div><div class="text-muted small" id="criteriosVagasVazio">Habilite “% de vaga” em um campo de lista para criar os critérios.</div></div>
</div></div></div>
@if($permissoes->permite('atividades.formulario.estrutura'))
<div class="card content-card mb-4"><div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3"><div><h2 class="h5 fw-bold mb-1">Estrutura dos campos</h2><p class="small text-muted mb-0">Escolha quantos campos aparecem por linha e use as setas para alterar a ordem.</p></div><button type="button" class="btn btn-primary" id="addField"><i class="bi bi-plus-lg me-1"></i>Adicionar campo</button></div><div class="card-body bg-light bg-opacity-50"><div id="builderEmpty" class="text-center text-muted py-5">Nenhum campo adicionado. Clique em “Adicionar campo” para começar.</div><div class="row g-3" id="builderRows"></div></div></div>
<style>
#builderRows .form-label { width:100%; }
#builderRows .field { min-width:0; width:100%; }
#builderRows [hidden] { display:none !important; }
@media (min-width:992px) {
    #builderRows .field { flex:0 0 var(--largura-campo, 100%); max-width:var(--largura-campo, 100%); }
}
</style>
@else
<div class="alert alert-light border"><i class="bi bi-lock me-1"></i>Os campos deste formulário são mantidos por quem tem a permissão <code>atividades.formulario.estrutura</code>. Salvar aqui altera apenas a configuração acima.</div>
@endif
<div class="d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Salvar formulário</button>@if($permissoes->permite('atividades.visualizar_formulario'))@if($atividade->url)<div class="dropdown"><button type="button" class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-play-circle me-1"></i>Testar formulário</button><ul class="dropdown-menu"><li><button type="button" class="dropdown-item" data-test-form-url="{{ $atividade->urlPublica() }}"><i class="bi bi-link-45deg me-2"></i>URL personalizada</button></li><li><button type="button" class="dropdown-item" data-test-form-url="{{ route('inscricoes.publica', ['atividade' => $atividade->hash_publica]) }}"><i class="bi bi-shield-lock me-2"></i>URL com hash</button></li></ul></div>@else<button type="button" class="btn btn-outline-info" id="testForm"><i class="bi bi-play-circle me-1"></i>Testar formulário</button>@endif @endif
@if($permissoes->permite('atividades.inscritos'))<a href="{{ route('atividades.inscricoes',$atividade) }}" class="btn btn-outline-warning"><i class="bi bi-person-lines-fill me-1"></i>Inscrições</a>@endif</div>
</form>

<div class="modal fade" id="rastreiosModal" tabindex="-1" aria-labelledby="rastreiosModalTitulo" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h2 class="modal-title fs-5" id="rastreiosModalTitulo">Origens e rastreios</h2><p class="small text-muted mb-0">Marque os canais que deseja usar. Todos levam ao mesmo formulário com um código <code>utm</code> diferente.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body">
            <div id="rastreiosFeedback" class="alert alert-danger d-none" role="alert"></div>
            <div class="table-responsive"><table class="table table-hover align-middle w-100" id="rastreiosTable"><thead><tr><th>Usar</th><th>Título</th><th>Código UTM</th><th>URL rastreável</th><th>Ações</th></tr></thead><tbody></tbody></table></div>
            <div class="mt-3">
                <button class="btn btn-outline-primary" type="button" id="mostrarNovoRastreio" aria-expanded="false" aria-controls="novoRastreio"><i class="bi bi-plus-lg me-1"></i>Adicionar origem personalizada</button>
                <div class="border rounded-3 p-3 mt-3 d-none" id="novoRastreio">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-5"><label class="form-label" for="novoRastreioTitulo">Título</label><input class="form-control" id="novoRastreioTitulo" maxlength="80" placeholder="Ex.: Rádio local"></div>
                        <div class="col-12 col-md-4"><label class="form-label" for="novoRastreioCodigo">Código UTM</label><input class="form-control" id="novoRastreioCodigo" minlength="5" maxlength="15" pattern="[a-z0-9][a-z0-9-]*[a-z0-9]" placeholder="radio-local"><div class="form-text">De 5 a 15 caracteres.</div></div>
                        <div class="col-12 col-md-3"><button class="btn btn-primary w-100" type="button" id="adicionarRastreio"><i class="bi bi-plus-lg me-1"></i>Adicionar à lista</button></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer"><span class="small text-muted me-auto">As alterações serão gravadas ao salvar o formulário.</span><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Concluir</button></div>
    </div></div>
</div>
@endsection
@push('styles')
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<style>.editor-wrapper{background:#fff;border-radius:.5rem;overflow:visible}.editor-wrapper .ql-toolbar{border-radius:.5rem .5rem 0 0}.editor-wrapper .ql-container{height:auto;overflow:visible;border-radius:0 0 .5rem .5rem}.editor-wrapper .ql-editor{min-height:280px;overflow:visible}.editor-wrapper .ql-editor img{max-width:100%;height:auto}.editor-wrapper .ql-editor pre{white-space:pre-wrap;overflow:visible}.editor-wrapper .ql-toolbar button[class*="ql-table"]{width:auto!important;padding-inline:7px!important;font-size:12px}.editor-wrapper .ql-editor table{width:100%;border-collapse:collapse}.editor-wrapper .ql-editor td{min-width:70px;padding:8px;border:1px solid #adb5bd}.rastreio-url{min-width:280px}.rastreio-check{width:1.15rem;height:1.15rem}</style>
@endpush
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>
<script>
const initial={{ Illuminate\Support\Js::from(json_decode(old('formulario', 'null'), true) ?? $atividade->formulario ?? []) }};const groups=initial.grupos||[],fieldsets=initial.fieldsets||[];let editorQuill=null;
document.getElementById('limitar_inscricoes').checked=!!initial.limitar_inscricoes;
document.getElementById('limite_inscricoes').value=initial.limite_inscricoes??'';
document.getElementById('mostrar_vagas_restantes').checked=!!initial.mostrar_vagas_restantes;
document.getElementById('apos_encerrar_vagas').value=initial.apos_encerrar_vagas==='lista_reserva'?'lista_reserva':'encerrar';
document.getElementById('limite_lista_reserva').value=initial.limite_lista_reserva??'';
document.getElementById('lista_reserva_sem_limite').checked=!!initial.lista_reserva_sem_limite;
document.getElementById('registrar_presenca_qrcode').checked=!!initial.registrar_presenca_qrcode;
document.getElementById('mensagem_vagas_esgotadas').value=initial.mensagem_vagas_esgotadas||@json(\App\Models\Atividade::MENSAGEM_VAGAS_ESGOTADAS);
document.getElementById('mensagem_ja_inscrito').value=initial.mensagem_ja_inscrito||@json(\App\Models\Atividade::MENSAGEM_JA_INSCRITO);
document.getElementById('mensagem_identificacao').value=@json($atividade->mensagemIdentificacao());
function atualizarLimite(){const limitar=document.getElementById('limitar_inscricoes').checked,total=Number(document.getElementById('limite_inscricoes').value)||0,usadas=Number(initial.distribuicao_vagas?.total?.usadas)||0,reserva=limitar&&document.getElementById('apos_encerrar_vagas').value==='lista_reserva',semLimite=document.getElementById('lista_reserva_sem_limite').checked,limiteReserva=document.getElementById('limite_lista_reserva');document.getElementById('limite_inscricoes').required=limitar;document.getElementById('limite_inscricoes').disabled=!limitar;document.getElementById('limiteContainer').hidden=!limitar;document.getElementById('aposEncerrarVagasContainer').hidden=!limitar;document.getElementById('listaReservaContainer').hidden=!reserva;document.getElementById('mensagemVagasContainer').hidden=!limitar||reserva;document.getElementById('mostrarVagasContainer').hidden=!limitar;document.getElementById('criteriosVagasContainer').hidden=!limitar;limiteReserva.disabled=!reserva||semLimite;limiteReserva.required=reserva&&!semLimite;document.getElementById('contadorVagas').textContent=`${usadas}/${total}`;document.getElementById('contadorVagas').title=`${usadas} vaga(s) utilizada(s) de ${total}; restam ${Math.max(0,total-usadas)}`;window.refreshBuilderQuota?.()}
['limitar_inscricoes','apos_encerrar_vagas','lista_reserva_sem_limite'].forEach(id=>document.getElementById(id).addEventListener('change',atualizarLimite));document.getElementById('limite_inscricoes').addEventListener('input',atualizarLimite);atualizarLimite();
function build(){const campos=window.readBuilderFields?.() ?? initial.campos ?? [];const conteudo=editorQuill?.root.innerHTML||'';const limitar=document.getElementById('limitar_inscricoes').checked,reserva=limitar&&document.getElementById('apos_encerrar_vagas').value==='lista_reserva',semLimite=reserva&&document.getElementById('lista_reserva_sem_limite').checked;return {limitar_inscricoes:limitar,limite_inscricoes:limitar?(Number(document.getElementById('limite_inscricoes').value)||null):null,apos_encerrar_vagas:reserva?'lista_reserva':'encerrar',limite_lista_reserva:reserva&&!semLimite?(Number(document.getElementById('limite_lista_reserva').value)||null):null,lista_reserva_sem_limite:semLimite,mostrar_vagas_restantes:limitar&&document.getElementById('mostrar_vagas_restantes').checked,registrar_presenca_qrcode:document.getElementById('registrar_presenca_qrcode').checked,mensagem_vagas_esgotadas:document.getElementById('mensagem_vagas_esgotadas').value,mensagem_ja_inscrito:document.getElementById('mensagem_ja_inscrito').value,mensagem_identificacao:document.getElementById('mensagem_identificacao').value,titulo:document.getElementById('titulo').value,subtitulo:document.getElementById('subtitulo').value,abertura:document.getElementById('abertura').value,fechamento:document.getElementById('fechamento').value,mensagem_antes:document.getElementById('mensagem_antes').value,mensagem_fechado:document.getElementById('mensagem_fechado').value,mensagem_sucesso:document.getElementById('mensagem_sucesso').value,editor:{exibir:document.getElementById('editor_exibir').checked,conteudo:conteudo==='<p><br></p>'?'':conteudo},grupos:groups,fieldsets,campos,criterios_vagas:window.readBuilderCriteria?.()??initial.criterios_vagas??[],rastreios:lerRastreios(),rows:[]}}
document.getElementById('formBuilder').addEventListener('submit',event=>{try{document.getElementById('formularioJson').value=JSON.stringify(build())}catch(error){event.preventDefault();console.error(error);alert('Não foi possível preparar o formulário para salvar. Tente novamente.')}});const abrirTeste=url=>window.open(url,'_blank','noopener');document.querySelectorAll('[data-test-form-url]').forEach(opcao=>opcao.addEventListener('click',()=>abrirTeste(opcao.dataset.testFormUrl)));const botaoTeste=document.getElementById('testForm');if(botaoTeste)botaoTeste.onclick=async()=>{const response=await fetch(@json(route('atividades.formulario.preview-link',$atividade)));const data=await response.json();abrirTeste(data.url)};
const mensagensPadrao={mensagem_antes:'As inscrições ainda não estão abertas. Confira a data de abertura e volte em breve para participar!',mensagem_fechado:'As inscrições para esta atividade estão encerradas. Agradecemos seu interesse e esperamos você nas próximas oportunidades!',mensagem_sucesso:'Inscrição realizada com sucesso! Recebemos suas respostas. Agradecemos sua participação!'};
['titulo','subtitulo','abertura','fechamento','mensagem_antes','mensagem_fechado','mensagem_sucesso'].forEach(id=>document.getElementById(id).value=(mensagensPadrao[id]&&!String(initial[id]??'').trim()?mensagensPadrao[id]:initial[id]??''));document.getElementById('editor_exibir').checked=initial.editor?.exibir??!!initial.editor?.conteudo;
const usarUrl=document.getElementById('usar_url'),urlAtividade=document.getElementById('url_atividade'),tituloFormulario=document.getElementById('titulo'),copiarUrl=document.getElementById('copiar_url_atividade');let urlPersonalizada=@json((bool) $atividade->url);const gerarSlug=valor=>String(valor||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,180);const atualizarEstadoUrl=(preencher=false)=>{urlAtividade.disabled=!usarUrl.checked;if(preencher&&usarUrl.checked&&!urlAtividade.value)urlAtividade.value=gerarSlug(tituloFormulario.value)||@json('atividade-'.$atividade->id);copiarUrl.disabled=!usarUrl.checked||!urlAtividade.value};usarUrl.addEventListener('change',()=>atualizarEstadoUrl(true));urlAtividade.addEventListener('input',()=>{urlPersonalizada=urlAtividade.value!=='';atualizarEstadoUrl()});urlAtividade.addEventListener('change',()=>{urlAtividade.value=gerarSlug(urlAtividade.value);atualizarEstadoUrl()});tituloFormulario.addEventListener('input',()=>{if(!urlPersonalizada)urlAtividade.value=gerarSlug(tituloFormulario.value)||@json('atividade-'.$atividade->id);atualizarEstadoUrl()});copiarUrl.addEventListener('click',async()=>{const endereco=@json(url('/a').'/')+urlAtividade.value;try{await navigator.clipboard.writeText(endereco)}catch(error){const temporario=document.createElement('textarea');temporario.value=endereco;temporario.style.position='fixed';temporario.style.opacity='0';document.body.append(temporario);temporario.select();document.execCommand('copy');temporario.remove()}const icone=copiarUrl.querySelector('i');icone.className='bi bi-check-lg';setTimeout(()=>{icone.className='bi bi-clipboard'},1200)});atualizarEstadoUrl(true);

const rastreiosPadrao = [
    {titulo:'Instagram',codigo:'instagram'}, {titulo:'Facebook',codigo:'facebook'},
    {titulo:'YouTube',codigo:'youtube'}, {titulo:'WhatsApp',codigo:'whatsapp'},
    {titulo:'LinkedIn',codigo:'linkedin'}, {titulo:'TikTok',codigo:'tiktok'},
    {titulo:'E-mail',codigo:'email'}, {titulo:'Google Ads',codigo:'google-ads'},
    {titulo:'Site institucional',codigo:'website'}, {titulo:'QR Code',codigo:'qrcode'},
    {titulo:'Parceiros',codigo:'parceiros'}, {titulo:'Imprensa',codigo:'imprensa'},
];
const rastreiosSalvos = Array.isArray(initial.rastreios) ? initial.rastreios : [];
const codigosPadrao = new Set(rastreiosPadrao.map(item => item.codigo));
const rastreiosIniciais = [
    ...rastreiosPadrao.map(item => {
        const salvo = rastreiosSalvos.find(rastreio => rastreio.codigo === item.codigo);
        return {...item, ativo: !!salvo?.ativo, predefinido: true};
    }),
    ...rastreiosSalvos.filter(item => !codigosPadrao.has(item.codigo)).map(item => ({...item, ativo: !!item.ativo, predefinido: false})),
];
const escaparHtml = valor => String(valor ?? '').replace(/[&<>"']/g, caractere => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[caractere]));
const urlBaseRastreio = () => usarUrl.checked && urlAtividade.value
    ? @json(url('/a').'/') + urlAtividade.value
    : @json(route('inscricoes.publica', ['atividade' => $atividade->hash_publica]));
const urlRastreavel = codigo => {
    const endereco = new URL(urlBaseRastreio(), window.location.origin);
    endereco.searchParams.set('utm', codigo);
    return endereco.toString();
};
let tabelaRastreios;
const lerRastreios = () => tabelaRastreios
    ? tabelaRastreios.rows().data().toArray().map(item => ({titulo:item.titulo,codigo:item.codigo,ativo:!!item.ativo,predefinido:!!item.predefinido}))
    : rastreiosIniciais;
const atualizarTotalRastreios = () => {
    const total = lerRastreios().filter(item => item.ativo).length;
    document.getElementById('totalRastreios').textContent = `${String(total).padStart(2, '0')} ${total === 1 ? 'rastreio' : 'rastreios'}`;
};
const atualizarUrlsRastreio = () => tabelaRastreios?.rows().invalidate().draw(false);

tabelaRastreios = new DataTable('#rastreiosTable', {
    data: rastreiosIniciais,
    pageLength: 10,
    order: [[1, 'asc']],
    columns: [
        {data:'ativo', orderable:false, searchable:false, className:'text-center', render:(valor, tipo, item) => tipo === 'display' ? `<input class="form-check-input rastreio-check" type="checkbox" aria-label="Usar rastreio ${escaparHtml(item.titulo)}" ${valor ? 'checked' : ''}>` : (valor ? 1 : 0)},
        {data:'titulo', render:(valor, tipo) => tipo === 'display' ? escaparHtml(valor) : valor},
        {data:'codigo', render:(valor, tipo) => tipo === 'display' ? `<code>${escaparHtml(valor)}</code>` : valor},
        {data:'codigo', orderable:false, searchable:false, render:(valor, tipo) => tipo === 'display' ? `<div class="input-group input-group-sm rastreio-url"><input class="form-control" value="${escaparHtml(urlRastreavel(valor))}" readonly aria-label="URL rastreável"><button class="btn btn-outline-secondary copiar-rastreio" type="button" title="Copiar URL" aria-label="Copiar URL"><i class="bi bi-clipboard"></i></button></div>` : valor},
        {data:null, orderable:false, searchable:false, className:'text-center', render:(valor, tipo, item) => tipo === 'display' && !item.predefinido ? '<button class="btn btn-sm btn-outline-danger excluir-rastreio" type="button" title="Excluir origem" aria-label="Excluir origem"><i class="bi bi-trash"></i></button>' : ''},
    ],
    language:{emptyTable:'Nenhuma origem cadastrada.',info:'Exibindo _START_ a _END_ de _TOTAL_ origens',infoEmpty:'Nenhuma origem cadastrada',lengthMenu:'Exibir _MENU_',search:'Pesquisar:',zeroRecords:'Nenhuma origem encontrada.',paginate:{next:'Próxima',previous:'Anterior'}},
});
atualizarTotalRastreios();

document.getElementById('rastreiosTable').addEventListener('change', evento => {
    if (!evento.target.matches('.rastreio-check')) return;
    const linha = tabelaRastreios.row(evento.target.closest('tr'));
    const dados = linha.data();
    dados.ativo = evento.target.checked;
    linha.data(dados).invalidate();
    atualizarTotalRastreios();
});
document.getElementById('rastreiosTable').addEventListener('click', async evento => {
    const copiar = evento.target.closest('.copiar-rastreio');
    if (copiar) {
        const campo = copiar.parentElement.querySelector('input');
        try { await navigator.clipboard.writeText(campo.value); } catch (_) { campo.select(); document.execCommand('copy'); }
        copiar.innerHTML = '<i class="bi bi-check-lg"></i>';
        setTimeout(() => { copiar.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 1200);
        return;
    }
    const excluir = evento.target.closest('.excluir-rastreio');
    if (excluir) {
        tabelaRastreios.row(excluir.closest('tr')).remove().draw(false);
        atualizarTotalRastreios();
    }
});

const blocoNovoRastreio = document.getElementById('novoRastreio');
const tituloNovoRastreio = document.getElementById('novoRastreioTitulo');
const codigoNovoRastreio = document.getElementById('novoRastreioCodigo');
const feedbackRastreios = document.getElementById('rastreiosFeedback');
let codigoRastreioEditado = false;
const normalizarCodigoRastreio = valor => {
    let codigo = gerarSlug(valor).slice(0, 15);
    if (codigo.length < 5) codigo = codigo.padEnd(5, '0');
    return codigo.replace(/-+$/g, '0');
};
const codigoRastreioDisponivel = base => {
    const existentes = new Set(lerRastreios().map(item => item.codigo));
    if (!existentes.has(base)) return base;
    for (let numero = 2; numero < 1000; numero++) {
        const sufixo = String(numero);
        const candidato = `${base.slice(0, 15 - sufixo.length)}${sufixo}`;
        if (!existentes.has(candidato)) return candidato;
    }
    return '';
};
document.getElementById('mostrarNovoRastreio').addEventListener('click', evento => {
    const abrir = blocoNovoRastreio.classList.contains('d-none');
    blocoNovoRastreio.classList.toggle('d-none', !abrir);
    evento.currentTarget.setAttribute('aria-expanded', abrir ? 'true' : 'false');
    if (abrir) tituloNovoRastreio.focus();
});
tituloNovoRastreio.addEventListener('input', () => {
    if (!codigoRastreioEditado) codigoNovoRastreio.value = normalizarCodigoRastreio(tituloNovoRastreio.value);
});
codigoNovoRastreio.addEventListener('input', () => {
    codigoRastreioEditado = true;
    codigoNovoRastreio.value = gerarSlug(codigoNovoRastreio.value).slice(0, 15);
});
document.getElementById('adicionarRastreio').addEventListener('click', () => {
    const titulo = tituloNovoRastreio.value.trim();
    const codigoInformado = normalizarCodigoRastreio(codigoNovoRastreio.value || titulo);
    const codigo = codigoRastreioDisponivel(codigoInformado);
    if (!titulo || !/^[a-z0-9][a-z0-9-]{3,13}[a-z0-9]$/.test(codigo)) {
        feedbackRastreios.textContent = 'Informe um título e um código UTM válido, com 5 a 15 caracteres.';
        feedbackRastreios.classList.remove('d-none');
        return;
    }
    feedbackRastreios.classList.add('d-none');
    tabelaRastreios.row.add({titulo,codigo,ativo:true,predefinido:false}).draw(false);
    tituloNovoRastreio.value = '';
    codigoNovoRastreio.value = '';
    codigoRastreioEditado = false;
    atualizarTotalRastreios();
});
[usarUrl, urlAtividade, tituloFormulario].forEach(campo => {
    campo.addEventListener('input', atualizarUrlsRastreio);
    campo.addEventListener('change', atualizarUrlsRastreio);
});
const seletorImagem=()=>{const input=document.createElement('input');input.type='file';input.accept='image/jpeg,image/png,image/gif,image/webp';input.onchange=async()=>{if(!input.files?.[0])return;const dados=new FormData();dados.append('imagem',input.files[0]);try{const resposta=await fetch(@json(route('atividades.formulario.editor-imagem',$atividade)),{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('#formBuilder input[name=_token]').value},body:dados});const retorno=await resposta.json();if(!resposta.ok)throw new Error(retorno.message||Object.values(retorno.errors||{})[0]?.[0]);const selecao=editorQuill.getSelection(true);editorQuill.insertEmbed(selecao.index,'image',retorno.url,'user');editorQuill.setSelection(selecao.index+1,0,'silent')}catch(erro){alert(erro.message||'Não foi possível enviar a imagem.')}};input.click()};
const inserirTabela=function(){const selecao=this.quill.getSelection(true);const linhas=Number.parseInt(prompt('Quantidade de linhas da tabela (1 a 20):','2'),10);if(!Number.isInteger(linhas)||linhas<1||linhas>20)return;const colunas=Number.parseInt(prompt('Quantidade de colunas da tabela (1 a 10):','2'),10);if(!Number.isInteger(colunas)||colunas<1||colunas>10)return;this.quill.setSelection(selecao.index,selecao.length,'silent');this.quill.getModule('table').insertTable(linhas,colunas)};
const acaoTabela=metodo=>function(){const modulo=this.quill.getModule('table');this.quill.getSelection(true);if(!modulo.getTable()[0]){alert('Posicione o cursor dentro de uma célula da tabela.');return}modulo[metodo]()};
const excluirTabela=function(){const modulo=this.quill.getModule('table');this.quill.getSelection(true);if(!modulo.getTable()[0]){alert('Posicione o cursor dentro de uma célula da tabela.');return}if(confirm('Excluir a tabela inteira?'))modulo.deleteTable()};
editorQuill=new Quill('#editorConteudo',{theme:'snow',modules:{table:true,toolbar:{container:[[{header:[1,2,3,false]}],[{font:[]}],[{size:['small',false,'large','huge']}],['bold','italic','underline','strike'],[{color:[]},{background:[]}],[{align:[]}],[{list:'ordered'},{list:'bullet'}],['blockquote','link','image'],['table','table-row-add','table-row-remove','table-column-add','table-column-remove','table-delete'],['clean']],handlers:{image:seletorImagem,table:inserirTabela,'table-row-add':acaoTabela('insertRowBelow'),'table-row-remove':acaoTabela('deleteRow'),'table-column-add':acaoTabela('insertColumnRight'),'table-column-remove':acaoTabela('deleteColumn'),'table-delete':excluirTabela}}}});
const controlesTabela={'table':['Tabela','Inserir tabela'],'table-row-add':['+ Linha','Adicionar linha abaixo'],'table-row-remove':['− Linha','Remover linha atual'],'table-column-add':['+ Coluna','Adicionar coluna à direita'],'table-column-remove':['− Coluna','Remover coluna atual'],'table-delete':['Excluir tabela','Excluir tabela inteira']};Object.entries(controlesTabela).forEach(([acao,[texto,titulo]])=>{const botao=document.querySelector(`.editor-wrapper .ql-${acao}`);if(!botao)return;botao.textContent=texto;botao.title=titulo;botao.setAttribute('aria-label',titulo)});editorQuill.clipboard.dangerouslyPasteHTML(initial.editor?.conteudo||'');
</script>
<script>window.formularioPodeInserirPix = @json($permissoes->permite('atividades.pix'));</script>
<script src="{{ asset('js/formulario-estrutura.js') }}?v={{ filemtime(public_path('js/formulario-estrutura.js')) }}"></script>
@endpush
