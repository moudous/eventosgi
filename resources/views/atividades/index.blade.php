@extends('layouts.app')
@section('title', $apagados ? 'Atividades apagadas' : 'Atividades')
@push('styles')<link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet"><style>#atividadesTable_wrapper .dt-layout-end{display:flex;align-items:end;justify-content:flex-end;gap:1rem;flex-wrap:wrap}.filtro-evento-dt{min-width:260px}.filtro-evento-dt label{display:block;margin-bottom:.25rem;font-size:.875rem;font-weight:600}</style>@endpush
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
 <div><h1 class="page-title">{{ $apagados?'Atividades apagadas':'Atividades' }}</h1><p class="page-description mb-0">{{ $apagados?'Restaure ou exclua definitivamente as atividades apagadas.':'Cadastre e gerencie as atividades dos eventos.' }}</p></div>
 <div class="d-flex align-items-center gap-3">@if(!$apagados && app(\App\Services\GiPermissionService::class)->permite('atividades.validador_qr'))<a href="{{ route('atividades.validador-presenca') }}" target="_blank" rel="noopener" class="btn btn-outline-success"><i class="bi bi-qr-code-scan me-2"></i>Validar presença</a>@endif @if(app(\App\Services\GiPermissionService::class)->permite('biblioteca.listar'))<a href="{{ route('biblioteca.index') }}" class="btn btn-outline-primary"><i class="bi bi-images me-2"></i>Biblioteca</a>@endif @if(app(\App\Services\GiPermissionService::class)->permite('configuracao.visualizar'))<a href="{{ route('configuracao.index') }}" class="btn btn-outline-dark" title="Plugin do WordPress, shortcodes e faixas de IP liberadas"><i class="bi bi-gear me-2"></i>Configuração</a>@endif
 <a href="{{ $apagados ? route('atividades.index') : route('atividades.apagados') }}" class="btn btn-outline-secondary">{{ $apagados ? 'Visualizar ativas' : 'Visualizar apagadas' }}</a>
 @if(!$apagados && app(\App\Services\GiPermissionService::class)->permite('atividades.criar'))<a href="{{ route('atividades.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Nova atividade</a>@endif</div>
</div>
@if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{session('status')}}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
<div id="actionFeedback" class="alert alert-dismissible fade d-none"><span></span><button class="btn-close" data-bs-dismiss="alert"></button></div>
<div id="filtroEventoAtividades" class="filtro-evento-dt d-none"><label for="filtro_evento">Evento</label><select id="filtro_evento" class="form-select form-select-sm"><option value="0">Todos os eventos</option>@foreach($eventosFiltro as $evento)<option value="{{ $evento->id }}" @selected((int)($estadoTabela['filtro_evento']??0)===$evento->id)>{{ $evento->nome }}</option>@endforeach</select></div>
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">{{ $apagados?'Registros apagados':'Atividades cadastradas' }}</h2></div><div class="card-body p-0"><div class="table-responsive">
<table id="atividadesTable" class="table table-hover align-middle w-100 mb-0"><thead><tr><th>ID</th><th>Nome</th><th>Evento</th><th>Modalidade</th><th>Início</th><th>Fim</th><th>Nº inscrições</th><th>Status</th><th>Criado por</th><th>Criação</th><th>Alteração</th>@if($apagados)<th>Exclusão</th>@endif<th data-dt-order="disable">Ações</th></tr></thead></table>
</div></div></div>
@if(!$apagados)
<div class="modal fade" id="iframeWordPressModal" tabindex="-1" aria-labelledby="iframeWordPressTitulo" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
  <div class="modal-content">
   <div class="modal-header"><div><h2 class="modal-title fs-5" id="iframeWordPressTitulo"><i class="bi bi-wordpress me-2"></i>Incorporar no WordPress</h2><div class="small text-muted text-truncate mt-1" id="iframeAtividadeNome"></div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
   <div class="modal-body">
    <div class="row g-3 mb-3">
     <div class="col-6"><label class="form-label" for="iframeLargura">Largura</label><select class="form-select form-select-sm" id="iframeLargura"><option value="100%">100% (responsiva)</option><option value="custom">Personalizada</option></select></div>
     <div class="col-6 d-none" id="iframeLarguraPersonalizada"><label class="form-label" for="iframeLarguraValor">Largura personalizada</label><div class="input-group input-group-sm"><input class="form-control" id="iframeLarguraValor" type="number" min="1" value="800"><select class="form-select" id="iframeLarguraUnidade" style="max-width:75px"><option value="px">px</option><option value="%">%</option></select></div></div>
     <div class="col-6"><label class="form-label" for="iframeAltura">Altura</label><select class="form-select form-select-sm" id="iframeAltura"><option value="600">600 px</option><option value="900" selected>900 px</option><option value="1200">1200 px</option><option value="custom">Personalizada</option></select></div>
     <div class="col-6 d-none" id="iframeAlturaPersonalizada"><label class="form-label" for="iframeAlturaValor">Altura personalizada</label><div class="input-group input-group-sm"><input class="form-control" id="iframeAlturaValor" type="number" min="200" value="900"><span class="input-group-text">px</span></div></div>
     <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="iframeBorda"><label class="form-check-label" for="iframeBorda">Exibir borda</label></div></div>
     <div class="col-6 d-none" id="iframeCorBordaContainer"><label class="form-label" for="iframeCorBorda">Cor da borda</label><input class="form-control form-control-color" type="color" id="iframeCorBorda" value="#dee2e6" title="Cor da borda"></div>
     <div class="col-6"><label class="form-label" for="iframeRaio">Cantos</label><select class="form-select form-select-sm" id="iframeRaio"><option value="0">Retos</option><option value="8" selected>Levemente arredondados</option><option value="16">Arredondados</option></select></div>
     <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="iframeLazy" checked><label class="form-check-label" for="iframeLazy">Carregar somente ao aproximar da tela</label></div></div>
    </div>
    <label class="form-label fw-semibold" for="iframeCodigo">Código do iframe</label>
    <textarea class="form-control font-monospace small" id="iframeCodigo" rows="6" spellcheck="false" aria-describedby="iframeCodigoAjuda"></textarea>
    <div id="iframeCodigoAjuda" class="form-text">Os controles acima atualizam este código automaticamente.</div>
    <button type="button" class="btn btn-primary w-100 mt-3" id="copiarIframe"><i class="bi bi-copy me-2"></i><span>Copiar código do iframe</span></button>
    <div class="alert alert-success py-2 mt-2 mb-0 d-none" id="iframeCopiado"><i class="bi bi-check-circle me-1"></i>Código copiado.</div>
    <hr>
    <h3 class="h6 fw-bold">Como adicionar no WordPress</h3>
    <ol class="small text-muted ps-3 mb-2">
     <li class="mb-1">Abra a página ou publicação desejada no painel do WordPress.</li>
     <li class="mb-1">Adicione um bloco <strong>HTML personalizado</strong>. No editor clássico, use a aba <strong>Texto</strong>.</li>
     <li class="mb-1">Cole o código copiado, visualize o resultado e publique ou atualize a página.</li>
     <li>Se o WordPress remover o iframe, faça a edição com uma conta administradora autorizada a publicar HTML sem filtro.</li>
    </ol>
    <div class="small text-muted"><i class="bi bi-info-circle me-1"></i>O servidor também precisa permitir exibição em sites externos.</div>
   </div>
  </div>
 </div>
</div>
@endif
@include('partials.historico-modal')
@endsection
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{const apagados=@json($apagados);
const cols=[{data:'id'},{data:'nome'},{data:'evento'},{data:'modalidade'},{data:'data_inicio'},{data:'data_fim'},{data:'inscricoes_count'},{data:'ativo'},{data:'criado_por'},{data:'created_at'},{data:'updated_at'},...(apagados?[{data:'deleted_at'}]:[]),{data:'acoes',orderable:false,searchable:false}];
const table=new DataTable('#atividadesTable',{processing:true,serverSide:true,order:[[0,'desc']],pageLength:@json($estadoTabela['por_pagina']),displayStart:@json(($estadoTabela['page']-1)*$estadoTabela['por_pagina']),search:{search:@json($estadoTabela['pesquisar'])},ajax:{url:@json(route('atividades.dados',[],false)),data:d=>{d.apagados=apagados?1:0;d.filtro_evento=document.getElementById('filtro_evento').value}},columns:cols,language:{processing:'Carregando...',emptyTable:'Nenhuma atividade cadastrada.',info:'Exibindo _START_ a _END_ de _TOTAL_ atividades',infoEmpty:'Nenhuma atividade encontrada',lengthMenu:'Exibir _MENU_ registros',search:'Pesquisar:',zeroRecords:'Nenhuma atividade encontrada.',paginate:{next:'Próxima',previous:'Anterior'}}});
const filtroEvento=document.getElementById('filtroEventoAtividades'),areaPesquisa=document.querySelector('#atividadesTable_wrapper .dt-search');if(areaPesquisa){areaPesquisa.parentElement.insertBefore(filtroEvento,areaPesquisa);filtroEvento.classList.remove('d-none')}document.getElementById('filtro_evento').addEventListener('change',()=>table.ajax.reload(null,true));
const feedback=document.getElementById('actionFeedback');let historyTable=null;const modal=new bootstrap.Modal('#historicoModal');
// Dentro do iframe do GI a API de area de transferencia pode estar bloqueada, entao
// a reserva usa um textarea temporario com execCommand.
function copiar(texto){if(navigator.clipboard&&window.isSecureContext)return navigator.clipboard.writeText(texto);return new Promise((ok,falha)=>{const a=document.createElement('textarea');a.value=texto;a.setAttribute('readonly','');a.style.position='fixed';a.style.opacity='0';document.body.appendChild(a);a.select();const feito=document.execCommand('copy');document.body.removeChild(a);feito?ok():falha(new Error('Copie manualmente: '+texto))})}
const iframeModalElemento=document.getElementById('iframeWordPressModal'),iframeModal=iframeModalElemento?new bootstrap.Modal(iframeModalElemento):null;
const iframeControles=iframeModalElemento?[...iframeModalElemento.querySelectorAll('select,input')]:[];
let iframeUrl='',iframeTitulo='';
const escaparAtributo=valor=>String(valor).replaceAll('&','&amp;').replaceAll('"','&quot;').replaceAll('<','&lt;').replaceAll('>','&gt;');
function atualizarIframe(){if(!iframeModalElemento)return;const larguraSelecionada=document.getElementById('iframeLargura').value,alturaSelecionada=document.getElementById('iframeAltura').value;document.getElementById('iframeLarguraPersonalizada').classList.toggle('d-none',larguraSelecionada!=='custom');document.getElementById('iframeAlturaPersonalizada').classList.toggle('d-none',alturaSelecionada!=='custom');const comBorda=document.getElementById('iframeBorda').checked;document.getElementById('iframeCorBordaContainer').classList.toggle('d-none',!comBorda);const largura=larguraSelecionada==='custom'?`${Math.max(1,Number(document.getElementById('iframeLarguraValor').value)||800)}${document.getElementById('iframeLarguraUnidade').value}`:larguraSelecionada;const altura=alturaSelecionada==='custom'?Math.max(200,Number(document.getElementById('iframeAlturaValor').value)||900):Number(alturaSelecionada);const borda=comBorda?`1px solid ${document.getElementById('iframeCorBorda').value}`:'0';const raio=Number(document.getElementById('iframeRaio').value)||0;const lazy=document.getElementById('iframeLazy').checked?' loading="lazy"':'';document.getElementById('iframeCodigo').value=`<iframe src="${escaparAtributo(iframeUrl)}" title="Formulário de inscrição — ${escaparAtributo(iframeTitulo)}" allow="camera" style="width: ${largura}; height: ${altura}px; border: ${borda}; border-radius: ${raio}px;"${lazy}></iframe>`;document.getElementById('iframeCopiado').classList.add('d-none')}
iframeControles.forEach(controle=>{controle.addEventListener('input',atualizarIframe);controle.addEventListener('change',atualizarIframe)});
document.getElementById('copiarIframe')?.addEventListener('click',async()=>{const codigo=document.getElementById('iframeCodigo').value;try{await copiar(codigo);document.getElementById('iframeCopiado').classList.remove('d-none')}catch(erro){document.getElementById('iframeCodigo').focus();document.getElementById('iframeCodigo').select();feedback.querySelector('span').textContent=erro.message||'Não foi possível copiar o código.';feedback.classList.remove('d-none','alert-success');feedback.classList.add('show','alert-danger')}});
document.getElementById('atividadesTable').onclick=async e=>{const iframe=e.target.closest('[data-iframe-url]');if(iframe){iframeUrl=iframe.dataset.iframeUrl;iframeTitulo=iframe.dataset.iframeTitle;document.getElementById('iframeAtividadeNome').textContent=iframeTitulo;atualizarIframe();iframeModal.show();return}
const history=e.target.closest('[data-history-url]');if(history){document.getElementById('historicoRegistro').textContent=history.dataset.historyName;if(historyTable)historyTable.destroy();historyTable=new DataTable('#historicoTable',{processing:true,serverSide:true,searching:false,ordering:false,ajax:history.dataset.historyUrl,columns:[{data:'numero'},{data:'historico'},{data:'usuario'},{data:'dados'},{data:'data_hora'}],language:{processing:'Carregando...',info:'Exibindo _START_ a _END_ de _TOTAL_ alterações',infoEmpty:'Nenhuma alteração',lengthMenu:'Exibir _MENU_',paginate:{next:'Próxima',previous:'Anterior'}}});modal.show();return}const b=e.target.closest('[data-action-url]');if(!b)return;const q=b.dataset.action==='force-delete'?'Excluir definitivamente? Esta ação não pode ser desfeita.':b.dataset.action==='restore'?'Restaurar esta atividade?':'Excluir esta atividade?';if(!confirm(q))return;b.disabled=true;try{const r=await fetch(b.dataset.actionUrl,{method:b.dataset.method,credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':@json(csrf_token())}});const p=await r.json();if(!r.ok)throw new Error(p.message);feedback.querySelector('span').textContent=p.message;feedback.classList.remove('d-none','alert-danger');feedback.classList.add('show','alert-success');table.ajax.reload(null,false)}catch(x){feedback.querySelector('span').textContent=x.message||'Falha na operação.';feedback.classList.remove('d-none','alert-success');feedback.classList.add('show','alert-danger');b.disabled=false}};
});
</script>@endpush
