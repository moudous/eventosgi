@extends('layouts.app')
@section('title', 'Auto confirmação de presença')
@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
<style>.contador-presenca{font-variant-numeric:tabular-nums;font-size:1.35rem;letter-spacing:.04em}.select2-container{width:100%!important}</style>
@endpush
@section('content')
@php($aberto = $link->estaAberto())
@php($errosIdentificacao = $errors->identificacao)
@php($presencaConfirmada = $inscricao && $inscricao->presente)
@php($dataPresenca = $presencaConfirmada ? $inscricao->data_presenca : null)
<div class="container py-4" style="max-width:760px">
    <div class="rounded-4 p-4 p-md-5 mb-4" style="background:{{ $link->atividade->fundoFormulario() }};color:{{ $link->atividade->estiloFormulario()['cor_fonte'] }}">
        <h1 class="h2 mb-3">Auto confirmação de presença</h1>
        <p class="mb-1"><strong>Atividade:</strong> {{ $link->atividade->nome }}</p>
        <p class="mb-0"><strong>Evento:</strong> {{ $link->atividade->evento?->nome ?? 'Não informado' }}</p>
    </div>

    @if($link->fim->isPast())
        <div class="alert alert-warning"><i class="bi bi-clock-history me-1"></i>O prazo para auto confirmação de presença já passou.</div>
    @elseif($link->inicio->isFuture())
        <div class="alert alert-info"><i class="bi bi-clock me-1"></i>O prazo ainda não começou. Aguarde a abertura.</div>
        <div class="text-center text-muted mb-4">Início em <strong>{{ $link->inicio->format('d/m/Y H:i') }}</strong> — <span class="contador-presenca" data-alvo="{{ $link->inicio->toIso8601String() }}" data-prefixo="Abre em "></span></div>
    @else
        <div class="alert alert-light border"><p class="mb-1">Faça sua auto confirmação de presença através desta página.</p><p class="mb-0 text-muted small">O prazo encerra em {{ $link->fim->format('d/m/Y H:i') }}.</p></div>
        <div class="text-center text-muted mb-4">Tempo restante: <strong class="contador-presenca" data-alvo="{{ $link->fim->toIso8601String() }}"></strong></div>

        @if(session('auto_presenca_confirmada'))
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-1"></i>Foi confirmada a presença na atividade <strong>{{ $link->atividade->nome }}</strong> do evento <strong>{{ $link->atividade->evento?->nome ?? 'Não informado' }}</strong>@if($dataPresenca), em <strong>{{ $dataPresenca->format('d/m/Y \à\s H:i:s') }}</strong>@endif.
            </div>
        @elseif($presencaConfirmada)
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-1"></i>Sua presença nesta atividade foi confirmada
                @if($dataPresenca)
                    em <strong>{{ $dataPresenca->format('d/m/Y \à\s H:i:s') }}</strong>
                @endif.
            </div>
        @endif

        @if($identificacao)
            <div class="alert alert-light border d-flex justify-content-between align-items-center gap-3"><span><i class="bi bi-person-check me-1"></i>Você entrou como <strong>{{ $identificacao['nome'] }}</strong><small class="d-block text-muted ms-4">{{ $identificacao['email'] }}</small></span></div>
        @endif

        @if($identificacao && !$inscricao)
            <div class="alert alert-danger">Você não possui uma inscrição ativa nesta atividade.</div>
        @elseif(!$presencaConfirmada)
            <form method="POST" action="{{ route('auto-presenca.confirmar', ['link' => $link->hash]) }}" class="card border-0 shadow-sm"><div class="card-body p-4">
                @csrf
                @if($errors->any() || $errosIdentificacao->any())<div class="alert alert-danger">{{ $errosIdentificacao->first() ?: $errors->first() }}</div>@endif
                @if(!$identificacao)
                    <div class="mb-3"><label class="form-label fw-semibold" for="email">Nome ou e-mail</label><select id="email" name="email" class="form-select @error('email') is-invalid @enderror" required></select><div class="form-text">Pesquise e selecione seu nome.</div>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="mb-4"><label class="form-label fw-semibold" for="senha">Senha</label><input id="senha" name="senha" type="password" class="form-control @error('senha') is-invalid @enderror" autocomplete="current-password" required>@error('senha')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                @endif
                <button class="btn btn-success w-100"><i class="bi bi-check-circle-fill me-1"></i>Confirmar presença</button>
            </div></form>
        @endif
    @endif
</div>
@endsection
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
document.querySelectorAll('.contador-presenca').forEach(elemento => {
    const alvo = new Date(elemento.dataset.alvo).getTime(), prefixo = elemento.dataset.prefixo || '';
    const atualizar = () => { const segundos = Math.max(0, Math.ceil((alvo - Date.now()) / 1000)); const h = Math.floor(segundos / 3600), m = Math.floor((segundos % 3600) / 60), s = segundos % 60; elemento.textContent = prefixo + [h, m, s].map(v => String(v).padStart(2, '0')).join(':'); if (!segundos) window.location.reload(); };
    atualizar(); setInterval(atualizar, 1000);
});
const campoEmail = $('#email');
if (campoEmail.length) campoEmail.select2({theme:'bootstrap-5',width:'100%',placeholder:'Selecione seu nome ou e-mail',minimumInputLength:0,language:{searching:()=> 'Buscando participantes...',noResults:()=> 'Nenhuma inscrição encontrada'},ajax:{url:@json(route('auto-presenca.participantes', ['link' => $link->hash])),dataType:'json',delay:150,data:params=>({q:params.term || ''}),processResults:dados=>dados},templateResult:item=>{if(!item.id)return item.text;return $('<span>').append($('<strong>').text(item.text), $('<small class="d-block text-secondary">').text(item.email));},templateSelection:item=>item.text});
</script>
@endpush
