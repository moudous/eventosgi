@extends('layouts.app')
@section('title', $apagados ? 'Eventos apagados' : 'Eventos')
@push('styles')
<link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">
@endpush
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h1 class="page-title">{{ $apagados ? 'Eventos apagados' : 'Eventos' }}</h1>
        <p class="page-description mb-0">{{ $apagados ? 'Consulte, restaure ou exclua definitivamente os eventos apagados.' : 'Cadastre e gerencie os eventos do sistema.' }}</p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-3">
        <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="visualizarApagados" @checked($apagados)>
            <label class="form-check-label" for="visualizarApagados">Visualizar apagados</label>
        </div>
        @if(!$apagados && app(\App\Services\GiPermissionService::class)->permite('eventos.criar'))
            <a href="{{ route('eventos.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Novo evento</a>
        @endif
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button></div>
@endif
<div id="actionFeedback" class="alert alert-dismissible fade d-none" role="alert"><span></span><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button></div>

<div class="card content-card">
    <div class="card-header"><h2 class="h5 fw-bold mb-0">{{ $apagados ? 'Registros apagados' : 'Eventos cadastrados' }}</h2></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table id="eventosTable" class="table table-hover align-middle w-100 mb-0">
            <thead><tr><th>ID</th><th>Nome</th><th>Status</th><th>Criação</th><th>Alteração</th>@if($apagados)<th>Exclusão</th>@endif<th class="text-center text-nowrap" data-dt-order="disable">Ações</th></tr></thead>
        </table>
    </div></div>
</div>
@include('partials.historico-modal')
@endsection
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const apagados = @json($apagados);
    document.getElementById('visualizarApagados').addEventListener('change', function () {
        window.location.href = this.checked ? @json(route('eventos.apagados', [], false)) : @json(route('eventos.index', [], false));
    });
    const columns = [
        {data: 'id'}, {data: 'nome'}, {data: 'ativo'}, {data: 'created_at'}, {data: 'updated_at'},
        ...(apagados ? [{data: 'deleted_at'}] : []),
        {data: 'acoes', orderable: false, searchable: false}
    ];
    const table = new DataTable('#eventosTable', {
        processing: true, serverSide: true, order: [[0, 'desc']],
        pageLength: @json($estadoTabela['por_pagina']),
        displayStart: @json(($estadoTabela['page'] - 1) * $estadoTabela['por_pagina']),
        search: {search: @json($estadoTabela['pesquisar'])},
        ajax: {url: @json(route('eventos.dados', [], false)), data: data => { data.apagados = apagados ? 1 : 0; }},
        columns,
        language: {
            processing: 'Carregando...', emptyTable: 'Nenhum evento cadastrado.', info: 'Exibindo _START_ a _END_ de _TOTAL_ eventos',
            infoEmpty: 'Nenhum evento encontrado', lengthMenu: 'Exibir _MENU_ registros', search: 'Pesquisar:',
            zeroRecords: 'Nenhum evento encontrado.', paginate: {first: 'Primeira', last: 'Última', next: 'Próxima', previous: 'Anterior'}
        }
    });
    const feedback = document.getElementById('actionFeedback');
    let historyTable = null;
    const historyModal = new bootstrap.Modal('#historicoModal');
    document.getElementById('eventosTable').addEventListener('click', async function (event) {
        const historyButton = event.target.closest('[data-history-url]');
        if (historyButton) {
            document.getElementById('historicoRegistro').textContent = historyButton.dataset.historyName;
            if (historyTable) historyTable.destroy();
            historyTable = new DataTable('#historicoTable', {
                processing: true, serverSide: true, searching: false, ordering: false,
                ajax: historyButton.dataset.historyUrl,
                columns: [{data: 'numero'}, {data: 'historico'}, {data: 'usuario'}, {data: 'dados'}, {data: 'data_hora'}],
                language: {processing: 'Carregando...', info: 'Exibindo _START_ a _END_ de _TOTAL_ alterações', infoEmpty: 'Nenhuma alteração', lengthMenu: 'Exibir _MENU_', paginate: {next: 'Próxima', previous: 'Anterior'}}
            });
            historyModal.show();
            return;
        }
        const button = event.target.closest('[data-action-url]');
        if (!button) return;
        const permanent = button.dataset.action === 'force-delete';
        const question = permanent ? 'Excluir este evento definitivamente? Esta ação não pode ser desfeita.' : (button.dataset.action === 'restore' ? 'Restaurar este evento?' : 'Excluir este evento?');
        if (!window.confirm(question)) return;
        button.disabled = true;
        try {
            const response = await fetch(button.dataset.actionUrl, {
                method: button.dataset.method, credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': @json(csrf_token())}
            });
            const payload = await response.json().catch(() => ({message: 'O servidor retornou uma resposta inválida.'}));
            if (!response.ok) throw new Error(payload.message || `Falha na operação (HTTP ${response.status}).`);
            feedback.querySelector('span').textContent = payload.message;
            feedback.classList.remove('d-none', 'alert-danger');
            feedback.classList.add('show', 'alert-success');
            table.ajax.reload(null, false);
        } catch (error) {
            feedback.querySelector('span').textContent = error.message || 'Não foi possível concluir a operação.';
            feedback.classList.remove('d-none', 'alert-success');
            feedback.classList.add('show', 'alert-danger');
            button.disabled = false;
        }
    });
});
</script>
@endpush
