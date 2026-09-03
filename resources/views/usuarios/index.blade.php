@extends('layouts.app')
@section('title', 'Usuários')
@push('styles')
<link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">
@endpush
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><h1 class="page-title">Usuários</h1><p class="page-description mb-0">Consulte os usuários sincronizados com o GI.</p></div>
    @if(app(\App\Services\GiPermissionService::class)->permite('usuarios.importar'))
        <button id="importUsers" type="button" class="btn btn-primary"><i class="bi bi-cloud-arrow-down-fill me-2"></i><span>Importar usuários</span></button>
    @endif
</div>

<div id="importFeedback" class="alert alert-dismissible fade d-none" role="alert">
    <span></span><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
</div>

<div class="card content-card">
    <div class="card-header"><h2 class="h5 fw-bold mb-0">Usuários cadastrados</h2></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table id="usuariosTable" class="table table-hover align-middle w-100 mb-0">
            <thead><tr><th>ID GI</th><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th><th>Último acesso</th><th class="text-center text-nowrap" data-dt-order="disable">Ações</th></tr></thead>
            <tbody>
            @foreach($usuarios as $usuario)
                <tr>
                    <td class="text-nowrap">{{ $usuario->id }}</td>
                    <td class="fw-semibold">{{ $usuario->nome }}</td>
                    <td>{{ $usuario->email ?: '—' }}</td>
                    <td>{{ $usuario->perfil ?: '—' }}</td>
                    <td><span class="badge {{ $usuario->ativo ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $usuario->ativo ? 'Ativo' : 'Inativo' }}</span></td>
                    <td class="text-nowrap" data-order="{{ $usuario->ultimo_acesso?->timestamp ?? 0 }}">{{ $usuario->ultimo_acesso?->format('d/m/Y H:i') ?: 'Nunca acessou' }}</td>
                    <td class="text-center text-nowrap"><a href="{{ route('usuarios.show', $usuario) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar usuário" aria-label="Visualizar {{ $usuario->nome }}"><i class="bi bi-eye-fill"></i></a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
</div>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = new DataTable('#usuariosTable', {
        order: [[0, 'desc']],
        pageLength: @json($estadoTabela['por_pagina']),
        displayStart: @json(($estadoTabela['page'] - 1) * $estadoTabela['por_pagina']),
        search: {search: @json($estadoTabela['pesquisar'])},
        language: {
            emptyTable: 'Nenhum usuário cadastrado.', info: 'Exibindo _START_ a _END_ de _TOTAL_ usuários',
            infoEmpty: 'Nenhum usuário encontrado', lengthMenu: 'Exibir _MENU_ registros', search: 'Pesquisar:',
            zeroRecords: 'Nenhum usuário encontrado.', paginate: {first: 'Primeira', last: 'Última', next: 'Próxima', previous: 'Anterior'}
        }
    });

    let salvarEstadoTimer;
    table.on('draw', function () {
        window.clearTimeout(salvarEstadoTimer);
        salvarEstadoTimer = window.setTimeout(function () {
            const pagina = table.page.info();
            fetch(@json(route('usuarios.estado-tabela', [], false)), {
                method: 'POST', credentials: 'same-origin', keepalive: true,
                headers: {
                    'Accept': 'application/json', 'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': @json(csrf_token())
                },
                body: JSON.stringify({page: pagina.page + 1, pesquisar: table.search(), por_pagina: pagina.length})
            });
        }, 150);
    });

    const button = document.getElementById('importUsers');
    if (!button) return;
    const buttonLabel = button.querySelector('span');
    const feedback = document.getElementById('importFeedback');

    button.addEventListener('click', async function () {
        button.disabled = true;
        buttonLabel.textContent = 'Importando...';
        feedback.classList.add('d-none');
        try {
            const response = await fetch(@json(route('usuarios.import', [], false)), {
                method: 'POST', credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': @json(csrf_token())}
            });
            const payload = await response.json().catch(() => ({message: 'O servidor retornou uma resposta inválida.'}));
            if (!response.ok) throw new Error(payload.message || `Falha na importação (HTTP ${response.status}).`);
            window.location.reload();
        } catch (error) {
            feedback.querySelector('span').textContent = error.message || 'Falha na importação.';
            feedback.classList.remove('d-none', 'alert-success');
            feedback.classList.add('show', 'alert-danger');
        } finally {
            button.disabled = false;
            buttonLabel.textContent = 'Importar usuários';
        }
    });
});
</script>
@endpush
