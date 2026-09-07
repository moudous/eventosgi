@extends('layouts.app')
@section('title', 'Categorias')
@push('styles')<link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">@endpush
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><h1 class="page-title">Categorias</h1><p class="page-description mb-0">Categorias usadas para classificar as atividades.</p></div>
    @if(app(\App\Services\GiPermissionService::class)->permite('categorias.criar'))<a href="{{ route('categorias.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Nova categoria</a>@endif
</div>
@if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
<div id="actionFeedback" class="alert alert-dismissible fade d-none"><span></span><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Categorias cadastradas</h2></div><div class="card-body p-0"><div class="table-responsive">
<table id="categoriasTable" class="table table-hover align-middle w-100 mb-0"><thead><tr><th>ID</th><th>Nome</th><th>Atividades</th><th>Status</th><th>Criado em</th><th>Alterado em</th><th data-dt-order="disable">Ações</th></tr></thead></table>
</div></div></div>
@endsection
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
const table=new DataTable('#categoriasTable',{processing:true,serverSide:true,order:[[0,'desc']],pageLength:@json($estadoTabela['por_pagina']),displayStart:@json(($estadoTabela['page']-1)*$estadoTabela['por_pagina']),search:{search:@json($estadoTabela['pesquisar'])},ajax:@json(route('categorias.dados',[],false)),columns:[{data:'id'},{data:'nome'},{data:'atividades_count'},{data:'ativo'},{data:'created_at'},{data:'updated_at'},{data:'acoes',orderable:false,searchable:false}],language:{processing:'Carregando...',emptyTable:'Nenhuma categoria cadastrada.',info:'Exibindo _START_ a _END_ de _TOTAL_ categorias',infoEmpty:'Nenhuma categoria encontrada',lengthMenu:'Exibir _MENU_ registros',search:'Pesquisar:',zeroRecords:'Nenhuma categoria encontrada.',paginate:{next:'Próxima',previous:'Anterior'}}});
const feedback=document.getElementById('actionFeedback');
document.getElementById('categoriasTable').onclick=async e=>{
    const b=e.target.closest('[data-action-url]');if(!b)return;
    const pergunta=b.dataset.action==='delete'?'Excluir esta categoria?':'Alterar o status desta categoria?';
    if(!confirm(pergunta))return;
    b.disabled=true;
    try{
        const r=await fetch(b.dataset.actionUrl,{method:b.dataset.method,credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':@json(csrf_token())}});
        const p=await r.json();
        if(!r.ok)throw new Error(p.message);
        feedback.querySelector('span').textContent=p.message;
        feedback.classList.remove('d-none','alert-danger');feedback.classList.add('show','alert-success');
        table.ajax.reload(null,false);
    }catch(x){
        feedback.querySelector('span').textContent=x.message||'Falha na operação.';
        feedback.classList.remove('d-none','alert-success');feedback.classList.add('show','alert-danger');
        b.disabled=false;
    }
};
});
</script>
@endpush
