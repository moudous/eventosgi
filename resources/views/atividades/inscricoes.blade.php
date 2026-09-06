@extends('layouts.app')
@section('title','Inscrições da atividade')
@section('content')<div class="mb-4 d-flex flex-wrap gap-3 justify-content-between"><div><h1 class="page-title">Inscrições</h1><p class="page-description mb-0">{{ $atividade->nome }}</p></div><div class="d-flex gap-2 align-items-start"><div class="dropdown"><button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-download me-1"></i>Exportar respostas</button><ul class="dropdown-menu">@foreach(['ods' => 'Planilha (.ods)', 'csv' => 'CSV (.csv)', 'xls' => 'Excel (.xls)', 'xlsx' => 'Excel (.xlsx)'] as $formato => $rotulo)<li><a class="dropdown-item exportar-respostas" href="{{ route('atividades.inscricoes.exportar', [$atividade, $formato]) }}">{{ $rotulo }}</a></li>@endforeach</ul></div><a href="{{ route('atividades.index') }}" class="btn btn-outline-secondary">Voltar</a></div></div><div class="card content-card"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>ID</th><th>Data</th><th>Respostas</th></tr></thead><tbody>@forelse($inscricoes as $inscricao)<tr><td>{{ $inscricao->id }}</td><td>{{ $inscricao->created_at?->format('d/m/Y H:i') }}</td><td><pre class="mb-0 small">{{ json_encode($inscricao->resposta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></td></tr>@empty<tr><td colspan="3" class="text-center">Nenhuma inscrição encontrada.</td></tr>@endforelse</tbody></table></div></div><div class="card-footer">{{ $inscricoes->links() }}</div></div>@endsection

@push('scripts')
<script>
document.querySelectorAll('.exportar-respostas').forEach(link => link.addEventListener('click', async event => {
    event.preventDefault();
    const button = document.querySelector('.dropdown-toggle');
    if (button.disabled) return;
    button.disabled = true;
    try {
        const response = await fetch(link.href, {credentials: 'same-origin', headers: {'Accept': 'application/octet-stream'}});
        if (!response.ok) throw new Error('Não foi possível exportar as respostas. Verifique sua sessão e tente novamente.');
        const disposition = response.headers.get('Content-Disposition') || '';
        if (!disposition.includes('attachment')) throw new Error('A sessão expirou. Reabra a aplicação pelo sistema GI e tente novamente.');
        const utf8 = disposition.match(/filename\*=utf-8''([^;]+)/i);
        const fallback = disposition.match(/filename="([^"]+)"|filename=([^;]+)/i);
        const filename = utf8 ? decodeURIComponent(utf8[1]) : (fallback?.[1] || fallback?.[2] || 'respostas');
        const url = URL.createObjectURL(await response.blob());
        const download = document.createElement('a');
        download.href = url;
        download.download = filename;
        document.body.appendChild(download);
        download.click();
        download.remove();
        setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (error) {
        alert(error.message || 'Não foi possível baixar o arquivo. Tente novamente.');
    } finally {
        button.disabled = false;
    }
}));
</script>
@endpush
