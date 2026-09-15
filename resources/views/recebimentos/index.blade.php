@extends('layouts.app')
@section('title', 'Recebimentos PIX')
@php
    $recebimentosDadosUrl = $atividadeSelecionada
        ? route('atividades.recebimentos.dados', $atividadeSelecionada, false)
        : route('recebimentos.dados', [], false);
    $recebimentosExportarUrl = $atividadeSelecionada
        ? route('atividades.recebimentos.exportar', $atividadeSelecionada)
        : route('recebimentos.exportar');
@endphp
@push('styles')
<link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">
@endpush
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><h1 class="page-title">Recebimentos PIX</h1><p class="page-description mb-0">{{ $atividadeSelecionada?->nome ?? 'Pagamentos confirmados de todas as atividades.' }}</p></div>
    <div class="d-flex gap-2">@if(app(\App\Services\GiPermissionService::class)->permite($atividadeSelecionada ? 'atividades.recebimentos.exportar' : 'recebimentos.exportar'))<button class="btn btn-success" id="exportarRecebimentos"><i class="bi bi-file-earmark-excel me-1"></i>Exportar pagamentos</button>@endif<a href="{{ route('atividades.index') }}" class="btn btn-outline-secondary">Voltar</a></div>
</div>

<div class="card content-card mb-4">
    <div class="card-header"><h2 class="h5 fw-bold mb-0"><i class="bi bi-funnel me-1"></i>Filtros</h2></div>
    <div class="card-body"><div class="row g-3 align-items-end">
        @if(!$atividadeSelecionada)
        <div class="col-md-3"><label class="form-label" for="filtro_evento">Evento</label><select class="form-select" id="filtro_evento"><option value="0">Todos os eventos</option>@foreach($eventos as $evento)<option value="{{ $evento->id }}">{{ $evento->nome }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label" for="filtro_atividade">Atividade</label><select class="form-select" id="filtro_atividade"><option value="0">Todas as atividades</option>@foreach($atividades as $atividade)<option value="{{ $atividade->id }}" data-evento="{{ $atividade->evento_id }}">{{ $atividade->nome }}</option>@endforeach</select></div>
        @endif
        <div class="col-md-2"><label class="form-label" for="valor_operador">Comparação do valor</label><select class="form-select" id="valor_operador"><option value="igual">Igual a</option><option value="maior">Maior que</option><option value="menor">Menor que</option><option value="entre">Entre</option></select></div>
        <div class="col-md-2"><label class="form-label" for="filtro_valor">Valor (R$)</label><input class="form-control" id="filtro_valor" inputmode="decimal" placeholder="0,00"></div>
        <div class="col-md-2 d-none" id="valorAteContainer"><label class="form-label" for="filtro_valor_ate">Até (R$)</label><input class="form-control" id="filtro_valor_ate" inputmode="decimal" placeholder="0,00"></div>
        <div class="col-md-2"><label class="form-label" for="filtro_data_inicial">Data inicial</label><input class="form-control" type="date" id="filtro_data_inicial"></div>
        <div class="col-md-2"><label class="form-label" for="filtro_data_final">Data final</label><input class="form-control" type="date" id="filtro_data_final"></div>
        <div class="col-md-4"><label class="form-label" for="filtro_pagador">Nome, CPF ou CNPJ do pagador</label><input class="form-control" id="filtro_pagador" maxlength="200" placeholder="Digite nome ou documento"></div>
        <div class="col-md-2 d-grid"><button class="btn btn-primary" id="aplicarFiltros"><i class="bi bi-search me-1"></i>Filtrar</button></div>
        <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary" id="limparFiltros">Limpar</button></div>
    </div></div>
</div>

<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Pagamentos confirmados</h2></div><div class="card-body p-0"><div class="table-responsive">
    <table id="recebimentosTable" class="table table-hover align-middle w-100 mb-0"><thead><tr><th>ID</th><th>Data e hora</th><th>Evento</th><th>Atividade</th><th>Pagador</th><th>CPF/CNPJ</th><th>E-mail</th><th>Valor</th><th>TXID</th><th data-dt-order="disable">Ações</th></tr></thead></table>
</div></div></div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const evento=document.getElementById('filtro_evento'),atividade=document.getElementById('filtro_atividade'),operador=document.getElementById('valor_operador'),valor=document.getElementById('filtro_valor'),valorAte=document.getElementById('filtro_valor_ate'),dataInicial=document.getElementById('filtro_data_inicial'),dataFinal=document.getElementById('filtro_data_final'),pagador=document.getElementById('filtro_pagador');
    const parametros=()=>({evento_id:evento?.value||0,atividade_id:atividade?.value||0,valor_operador:operador.value,valor:valor.value,valor_ate:valorAte.value,data_inicial:dataInicial.value,data_final:dataFinal.value,pagador:pagador.value});
    const table=new DataTable('#recebimentosTable',{processing:true,serverSide:true,order:[[1,'desc']],pageLength:10,ajax:{url:{{ Illuminate\Support\Js::from($recebimentosDadosUrl) }},data:d=>Object.assign(d,parametros())},columns:[{data:'id'},{data:'data_hora'},{data:'evento',orderable:false},{data:'atividade',orderable:false},{data:'pagador'},{data:'documento'},{data:'email',orderable:false},{data:'valor'},{data:'txid'},{data:'acoes',orderable:false,searchable:false}],language:{processing:'Carregando...',emptyTable:'Nenhum pagamento PIX confirmado.',info:'Exibindo _START_ a _END_ de _TOTAL_ pagamentos',infoEmpty:'Nenhum pagamento encontrado',lengthMenu:'Exibir _MENU_ registros',search:'Pesquisar:',zeroRecords:'Nenhum pagamento corresponde aos filtros.',paginate:{next:'Próxima',previous:'Anterior'}}});
    operador.addEventListener('change',()=>document.getElementById('valorAteContainer').classList.toggle('d-none',operador.value!=='entre'));
    evento?.addEventListener('change',()=>{[...atividade.options].forEach(opcao=>{if(!opcao.value)return;opcao.hidden=evento.value!=='0'&&opcao.dataset.evento!==evento.value});if(atividade.selectedOptions[0]?.hidden)atividade.value='0'});
    document.getElementById('aplicarFiltros').addEventListener('click',()=>table.ajax.reload(null,true));
    document.getElementById('limparFiltros').addEventListener('click',()=>{if(evento)evento.value='0';if(atividade){atividade.value='0';[...atividade.options].forEach(o=>o.hidden=false)}operador.value='igual';valor.value='';valorAte.value='';dataInicial.value='';dataFinal.value='';pagador.value='';document.getElementById('valorAteContainer').classList.add('d-none');table.search('').ajax.reload(null,true)});
    document.getElementById('exportarRecebimentos')?.addEventListener('click',()=>{const url=new URL({{ Illuminate\Support\Js::from($recebimentosExportarUrl) }},window.location.origin);Object.entries(parametros()).forEach(([chave,item])=>{if(String(item)!==''&&String(item)!=='0')url.searchParams.set(chave,item)});window.location.href=url.toString()});
});
</script>
@endpush
