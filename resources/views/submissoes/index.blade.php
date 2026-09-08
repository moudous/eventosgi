@extends('layouts.app')
@section('title', 'Submissões de trabalhos')
@push('styles')<link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet"><style>#submissoesTable_wrapper .dt-layout-end{display:flex;align-items:end;justify-content:flex-end;gap:1rem;flex-wrap:wrap}.filtro-evento-dt{min-width:260px}.filtro-evento-dt label{display:block;margin-bottom:.25rem;font-size:.875rem;font-weight:600}</style>@endpush
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><h1 class="page-title">Submissões de trabalhos</h1><p class="page-description mb-0">Configure os períodos e acompanhe os trabalhos enviados.</p></div>
    @if(app(\App\Services\GiPermissionService::class)->permite('submissoes.criar'))<a href="{{ route('submissoes.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Adicionar submissão</a>@endif
</div>
@if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
<div id="actionFeedback" class="alert alert-dismissible fade d-none"><span></span><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<div id="filtroEventoSubmissoes" class="filtro-evento-dt mb-3">
    <label for="filtro_evento">Evento</label>
    <select id="filtro_evento" class="form-select form-select-sm">
        <option value="0">Todos os eventos</option>
        @foreach($eventosFiltro as $evento)
            <option value="{{ $evento->id }}" @selected((int) ($estadoTabela['filtro_evento'] ?? 0) === (int) $evento->id)>{{ $evento->nome }}</option>
        @endforeach
    </select>
</div>
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Períodos cadastrados</h2></div><div class="card-body p-0"><div class="table-responsive">
<table id="submissoesTable" class="table table-hover align-middle w-100 mb-0"><thead><tr><th>ID</th><th>Evento</th><th>Título</th><th>Início</th><th>Fim</th><th>Status</th><th>Trabalhos</th><th>Criação</th><th data-dt-order="disable">Ações</th></tr></thead></table>
</div></div></div>
@endsection
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
const filtroEvento=document.getElementById('filtroEventoSubmissoes');
const seletorEvento=document.getElementById('filtro_evento');
const table=new DataTable('#submissoesTable',{
    processing:true,
    serverSide:true,
    order:[[0,'desc']],
    pageLength:@json($estadoTabela['por_pagina']),
    displayStart:@json(($estadoTabela['page']-1)*$estadoTabela['por_pagina']),
    search:{search:@json($estadoTabela['pesquisar'])},
    ajax:{
        url:@json(route('submissoes.dados',[],false)),
        data:function(dados){dados.filtro_evento=seletorEvento.value},
        error:function(xhr){
            const mensagem=(xhr.responseJSON&&xhr.responseJSON.message)||'Não foi possível carregar a listagem de submissões.';
            const feedback=document.getElementById('actionFeedback');
            feedback.querySelector('span').textContent=mensagem;
            feedback.classList.remove('d-none','alert-success');
            feedback.classList.add('show','alert-danger');
        }
    },
    columns:[{data:'id'},{data:'evento'},{data:'titulo'},{data:'data_inicio'},{data:'data_fim'},{data:'ativo'},{data:'inscricoes_count'},{data:'created_at'},{data:'acoes',orderable:false,searchable:false}],
    language:{processing:'Carregando...',emptyTable:'Nenhuma submissão cadastrada.',info:'Exibindo _START_ a _END_ de _TOTAL_ submissões',infoEmpty:'Nenhuma submissão encontrada',lengthMenu:'Exibir _MENU_ registros',search:'Pesquisar:',zeroRecords:'Nenhuma submissão encontrada.',paginate:{next:'Próxima',previous:'Anterior'}},
    initComplete:function(){
        const areaPesquisa=document.querySelector('#submissoesTable_wrapper .dt-search');
        if(areaPesquisa){
            areaPesquisa.parentElement.insertBefore(filtroEvento,areaPesquisa);
            filtroEvento.classList.remove('mb-3');
        }
    }
});
seletorEvento.addEventListener('change',function(){table.ajax.reload(null,true)});
const feedback=document.getElementById('actionFeedback');
document.getElementById('submissoesTable').onclick=async e=>{const b=e.target.closest('[data-action-url]');if(!b)return;const pergunta=b.dataset.action==='delete'?'Excluir esta submissão e todos os trabalhos vinculados? Esta ação não pode ser desfeita.':'Alterar o status desta submissão?';if(!confirm(pergunta))return;b.disabled=true;try{const r=await fetch(b.dataset.actionUrl,{method:b.dataset.method,credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':@json(csrf_token())}});const p=await r.json();if(!r.ok)throw new Error(p.message);feedback.querySelector('span').textContent=p.message;feedback.classList.remove('d-none','alert-danger');feedback.classList.add('show','alert-success');table.ajax.reload(null,false)}catch(x){feedback.querySelector('span').textContent=x.message||'Falha na operação.';feedback.classList.remove('d-none','alert-success');feedback.classList.add('show','alert-danger');b.disabled=false}};
});
</script>
@endpush
