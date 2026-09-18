@extends('layouts.app')
@section('title', 'Visualizar trabalho')
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between gap-3">
    <div>
        <h1 class="page-title">Visualizar trabalho</h1>
        <p class="page-description mb-0">{{ $submissao->titulo }} — {{ $submissao->evento?->nome }}</p>
    </div>
    @if(app(\App\Services\GiPermissionService::class)->permite('submissoes.inscritos'))<a href="{{ route('submissoes.inscritos', $submissao) }}" class="btn btn-outline-secondary align-self-start">Voltar</a>@endif
</div>

<div class="card content-card">
    <div class="card-body p-4">
        @if($trabalho->trashed())
            <div class="alert alert-danger">Este trabalho foi apagado.</div>
        @endif
        <dl class="row mb-0">
            <dt class="col-sm-3">Título do trabalho</dt>
            <dd class="col-sm-9">{{ $trabalho->titulo_trabalho ?: '—' }}</dd>

            @if($podeVerAutores)
                <dt class="col-sm-3">E-mail</dt>
                <dd class="col-sm-9">{{ $trabalho->inscricao?->email ?: '—' }}</dd>
                <dt class="col-sm-3">Autores</dt>
                <dd class="col-sm-9">
                    @if($trabalho->autores->isEmpty())
                        —
                    @else
                        <div class="border rounded bg-light p-3">
                            <div class="mb-3">
                                @foreach($trabalho->autores as $autor)
                                    {{ !$loop->first ? '; ' : '' }}{{ $autor->nome }}<sup>{{ $autor->numero ?: $autor->ordem }}</sup>
                                @endforeach
                            </div>
                            @foreach($trabalho->autores as $autor)
                                <div class="small mb-1"><sup>{{ $autor->numero ?: $autor->ordem }}</sup> {{ $autor->afiliacao ?: 'Filiação não informada' }}</div>
                            @endforeach
                            @php($coautoresComEmail = $trabalho->autores->where('principal', false)->filter(fn ($autor) => filled($autor->email)))
                            @if($coautoresComEmail->isNotEmpty())
                                <hr class="my-3">
                                <div class="small fw-semibold mb-2">E-mails dos coautores</div>
                                @foreach($coautoresComEmail as $autor)
                                    <div class="small mb-1"><sup>{{ $autor->numero ?: $autor->ordem }}</sup> <a href="mailto:{{ $autor->email }}">{{ $autor->email }}</a></div>
                                @endforeach
                            @endif
                        </div>
                    @endif
                </dd>
            @endif

            <dt class="col-sm-3">Categoria</dt>
            <dd class="col-sm-9">{{ $trabalho->categoriaTrabalhoRotulo() ?: '—' }}</dd>
            <dt class="col-sm-3">Palavras-chave</dt>
            <dd class="col-sm-9">{{ $trabalho->palavras_chave ?: '—' }}</dd>
            @if(filled($trabalho->apresentacao))
                <dt class="col-sm-3">Apresentação</dt>
                <dd class="col-sm-9">{{ ucfirst($trabalho->apresentacao) }}</dd>
            @endif
            <dt class="col-sm-3">Apoio financeiro</dt>
            <dd class="col-sm-9">{{ $trabalho->tem_apoio_financeiro ? 'Sim: '.($trabalho->apoiador ?: '—') : 'Não' }}</dd>
            <dt class="col-sm-3">Comitê de Ética</dt>
            <dd class="col-sm-9">{{ $trabalho->aprovacao_comite_etica ? 'Sim: '.($trabalho->protocolo_comite_etica ?: '—') : 'Não' }}</dd>
            <dt class="col-sm-3">Status</dt>
            <dd class="col-sm-9">{{ ucfirst($trabalho->status ?: '—') }}</dd>
            <dt class="col-sm-3">Situação</dt>
            <dd class="col-sm-9">{{ $trabalho->status === 'avaliado' ? ($trabalho->situacao ?: '—') : '—' }}</dd>
            @if(filled($trabalho->nota))
                <dt class="col-sm-3">Nota</dt>
                <dd class="col-sm-9">{{ $trabalho->nota }}</dd>
            @endif
            @if($trabalho->status === 'avaliado')
                <dt class="col-sm-3">E-pôster</dt>
                <dd class="col-sm-9">
                    @if($trabalho->situacao === 'reprovado')
                        <span class="badge text-bg-danger">Trabalho reprovado — não apresentado</span>
                    @elseif($trabalho->temEposter())
                        <a class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener" href="{{ route('submissoes.inscritos.e-poster', [$submissao, $trabalho]) }}"><i class="bi bi-file-earmark-pdf me-1"></i>Visualizar e-pôster</a>
                        @if($trabalho->eposter_enviado_em)<span class="text-secondary ms-2">Enviado em {{ $trabalho->eposter_enviado_em->format('d/m/Y H:i') }}</span>@endif
                    @elseif(!$submissao->periodoEposterConfigurado())
                        <span class="text-secondary">Período de envio não cadastrado.</span>
                    @elseif($submissao->periodoEposterAindaNaoAbriu())
                        <span class="badge text-bg-secondary">Fora do período de envio</span> <span class="text-secondary">O envio começa em {{ $submissao->eposter_data_inicio->format('d/m/Y H:i') }}.</span>
                    @elseif($submissao->periodoEposterAberto())
                        <span class="badge text-bg-warning">Aguardando envio do e-pôster</span>
                    @else
                        <span class="badge text-bg-danger">O autor perdeu o prazo de envio do e-pôster</span>
                    @endif
                </dd>
            @endif
            <dt class="col-sm-3">Resumo</dt>
            <dd class="col-sm-9">{!! $trabalho->conteudo ?: '—' !!}</dd>
        </dl>
    </div>
</div>
@endsection
