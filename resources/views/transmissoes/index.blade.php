@extends('layouts.app')

@section('title', 'Transmissões')

@push('styles')
<style>
    .transmissao-thumb { width: 128px; height: 72px; object-fit: cover; border-radius: .5rem; background: #eef2f6; }
    .transmissao-thumb-placeholder { width: 128px; height: 72px; border-radius: .5rem; background: #eef2f6; color: #6c757d; }
</style>
@endpush

@section('content')
<div class="mb-4 d-flex justify-content-between gap-3 align-items-start">
    <div>
        <h1 class="page-title">Transmissões</h1>
        <p class="page-description mb-0">Agende e acompanhe as transmissões associadas aos eventos.</p>
    </div>
    @if(app(\App\Services\GiPermissionService::class)->permite('transmissao.criar'))<div class="d-flex flex-wrap gap-2">
        @if($youtubeConexao)<form method="POST" action="{{ route('transmissoes.youtube.desconectar') }}">@csrf @method('DELETE')<button class="btn btn-outline-secondary" type="submit"><i class="bi bi-youtube me-1"></i>Desconectar YouTube</button></form>
        @else<a href="{{ route('transmissoes.youtube.conectar') }}" class="btn btn-outline-danger"><i class="bi bi-youtube me-1"></i>Conectar conta do YouTube</a>@endif
        @if($podeConfigurarLiveKit)<a href="{{ route('transmissoes.configuracao') }}" class="btn btn-outline-secondary"><i class="bi bi-gear me-1"></i>Configuração</a>@endif
        <a href="{{ route('transmissoes.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Criar transmissão</a>
    </div>@endif
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if(session('erro'))<div class="alert alert-danger">{{ session('erro') }}</div>@endif

<div class="alert {{ $youtubeConexao ? 'alert-success' : 'alert-warning' }} d-flex align-items-center gap-2" role="status"><i class="bi bi-youtube fs-5"></i><span>@if($youtubeConexao)<strong>Canal conectado:</strong> {{ $youtubeConexao->canal_titulo }}.@else Nenhuma conta do YouTube está conectada. Conecte uma conta antes de criar transmissões no canal.@endif</span></div>

<div class="card content-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('transmissoes.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label" for="evento_id">Evento</label>
                <select class="form-select" id="evento_id" name="evento_id">
                    <option value="0">Todos os eventos</option>
                    @foreach($eventosFiltro as $evento)
                        <option value="{{ $evento->id }}" @selected($eventoSelecionado === $evento->id)>{{ $evento->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos os status</option>
                    @foreach(['agendada' => 'Agendada', 'em_andamento' => 'Em andamento', 'finalizada' => 'Finalizada', 'cancelada' => 'Cancelada'] as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($statusSelecionado === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i>Filtrar</button></div>
            @if($statusSelecionado !== '' || $eventoSelecionado > 0)
                <div class="col-auto"><a href="{{ route('transmissoes.index') }}" class="btn btn-outline-secondary">Limpar</a></div>
            @endif
        </form>
    </div>
</div>

<div class="card content-card">
    <div class="card-header"><h2 class="h5 fw-bold mb-0">Transmissões cadastradas</h2></div>
    <div class="card-body p-0">
        @if($transmissoes->isEmpty())
            <div class="p-4 text-center text-secondary">Nenhuma transmissão encontrada.</div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Miniatura</th><th>Título</th><th>Evento</th><th>Status</th><th>Agendamento</th><th>Sala</th><th>YouTube</th>@if($podeExcluirTransmissao)<th class="text-end">Ações</th>@endif</tr></thead>
                    <tbody>
                    @foreach($transmissoes as $transmissao)
                        <tr>
                            <td>
                                @if($transmissao->miniatura_url)
                                    <img class="transmissao-thumb" src="{{ $transmissao->miniatura_url }}" alt="Miniatura de {{ $transmissao->titulo }}" loading="lazy">
                                @else
                                    <div class="transmissao-thumb-placeholder d-inline-flex align-items-center justify-content-center" title="Miniatura ainda não configurada"><i class="bi bi-camera-video fs-4"></i></div>
                                @endif
                            </td>
                            <td><div class="fw-semibold">{{ $transmissao->titulo }}</div>@if($transmissao->descricao)<div class="small text-secondary text-truncate" style="max-width:280px">{{ $transmissao->descricao }}</div>@endif</td>
                            <td>{{ $transmissao->evento?->nome ?? 'Evento removido' }}</td>
                            <td><span class="badge {{ $transmissao->statusClass() }}">{{ $transmissao->statusLabel() }}</span></td>
                            <td>{{ $transmissao->agendada_para?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td><a href="{{ route('transmissoes.sala', ['transmissao' => $transmissao->hash_publico]) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary"><i class="bi bi-camera-video me-1"></i>Abrir</a></td>
                            <td>@if($transmissao->youtube_url)<a href="{{ $transmissao->youtube_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-danger"><i class="bi bi-youtube me-1"></i>Abrir</a>@else<span class="text-secondary">Não conectado</span>@endif</td>
                            @if($podeExcluirTransmissao)<td class="text-end"><form method="POST" action="{{ route('transmissoes.destroy', $transmissao) }}" class="d-inline" onsubmit="return confirm('Excluir esta transmissão? Esta ação não pode ser desfeita.');">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Excluir</button></form></td>@endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $transmissoes->links() }}</div>
        @endif
    </div>
</div>
@endsection
