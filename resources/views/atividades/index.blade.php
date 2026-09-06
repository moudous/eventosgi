@extends('layouts.app')
@section('title', $apagados ? 'Atividades apagadas' : 'Atividades')
@push('styles')<link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">@endpush
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
 <div><h1 class="page-title">{{ $apagados?'Atividades apagadas':'Atividades' }}</h1><p class="page-description mb-0">{{ $apagados?'Restaure ou exclua definitivamente as atividades apagadas.':'Cadastre e gerencie as atividades dos eventos.' }}</p></div>
 <div class="d-flex align-items-center gap-3"><a href="{{ route('atividades.plugin-wordpress') }}" class="btn btn-outline-dark" title="Baixar o plugin que exibe os formulários das atividades em um site WordPress"><i class="bi bi-wordpress me-2"></i>Plugin WordPress</a>
 <a href="{{ $apagados ? route('atividades.index') : route('atividades.apagados') }}" class="btn btn-outline-secondary">{{ $apagados ? 'Visualizar ativas' : 'Visualizar apagadas' }}</a>
 @if(!$apagados && app(\App\Services\GiPermissionService::class)->permite('atividades.criar'))<a href="{{ route('atividades.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Nova atividade</a>@endif</div>
</div>
@if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{session('status')}}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
<div id="actionFeedback" class="alert alert-dismissible fade d-none"><span></span><button class="btn-close" data-bs-dismiss="alert"></button></div>
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">{{ $apagados?'Registros apagados':'Atividades cadastradas' }}</h2></div><div class="card-body p-0"><div class="table-responsive">
<table id="atividadesTable" class="table table-hover align-middle w-100 mb-0"><thead><tr><th>ID</th><th>Nome</th><th>Evento</th><th>Modalidade</th><th>Início</th><th>Fim</th><th>Nº inscrições</th><th>Status</th><th>Criado por</th><th>Criação</th><th>Alteração</th>@if($apagados)<th>Exclusão</th>@endif<th data-dt-order="disable">Ações</th></tr></thead></table>
</div></div></div>
@include('partials.historico-modal')
@endsection
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{const apagados=@json($apagados);
const cols=[{data:'id'},{data:'nome'},{data:'evento'},{data:'modalidade'},{data:'data_inicio'},{data:'data_fim'},{data:'inscricoes_count'},{data:'ativo'},{data:'criado_por'},{data:'created_at'},{data:'updated_at'},...(apagados?[{data:'deleted_at'}]:[]),{data:'acoes',orderable:false,searchable:false}];
const table=new DataTable('#atividadesTable',{processing:true,serverSide:true,order:[[0,'desc']],pageLength:@json($estadoTabela['por_pagina']),displayStart:@json(($estadoTabela['page']-1)*$estadoTabela['por_pagina']),search:{search:@json($estadoTabela['pesquisar'])},ajax:{url:@json(route('atividades.dados',[],false)),data:d=>d.apagados=apagados?1:0},columns:cols,language:{processing:'Carregando...',emptyTable:'Nenhuma atividade cadastrada.',info:'Exibindo _START_ a _END_ de _TOTAL_ atividades',infoEmpty:'Nenhuma atividade encontrada',lengthMenu:'Exibir _MENU_ registros',search:'Pesquisar:',zeroRecords:'Nenhuma atividade encontrada.',paginate:{next:'Próxima',previous:'Anterior'}}});
const feedback=document.getElementById('actionFeedback');let historyTable=null;const modal=new bootstrap.Modal('#historicoModal');
document.getElementById('atividadesTable').onclick=async e=>{const history=e.target.closest('[data-history-url]');if(history){document.getElementById('historicoRegistro').textContent=history.dataset.historyName;if(historyTable)historyTable.destroy();historyTable=new DataTable('#historicoTable',{processing:true,serverSide:true,searching:false,ordering:false,ajax:history.dataset.historyUrl,columns:[{data:'numero'},{data:'historico'},{data:'usuario'},{data:'dados'},{data:'data_hora'}],language:{processing:'Carregando...',info:'Exibindo _START_ a _END_ de _TOTAL_ alterações',infoEmpty:'Nenhuma alteração',lengthMenu:'Exibir _MENU_',paginate:{next:'Próxima',previous:'Anterior'}}});modal.show();return}const b=e.target.closest('[data-action-url]');if(!b)return;const q=b.dataset.action==='force-delete'?'Excluir definitivamente? Esta ação não pode ser desfeita.':b.dataset.action==='restore'?'Restaurar esta atividade?':'Excluir esta atividade?';if(!confirm(q))return;b.disabled=true;try{const r=await fetch(b.dataset.actionUrl,{method:b.dataset.method,credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':@json(csrf_token())}});const p=await r.json();if(!r.ok)throw new Error(p.message);feedback.querySelector('span').textContent=p.message;feedback.classList.remove('d-none','alert-danger');feedback.classList.add('show','alert-success');table.ajax.reload(null,false)}catch(x){feedback.querySelector('span').textContent=x.message||'Falha na operação.';feedback.classList.remove('d-none','alert-success');feedback.classList.add('show','alert-danger');b.disabled=false}};
});
</script>@endpush
