@extends('layouts.app')

@section('title', 'Configuração da videoconferência')

@section('content')
<div class="mb-4"><h1 class="page-title">Configuração da videoconferência</h1><p class="page-description mb-0">Credenciais do servidor LiveKit usadas para criar as salas de câmera, áudio e compartilhamento de tela.</p></div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('transmissoes.configuracao.salvar') }}"><div class="card content-card" style="max-width:760px"><div class="card-body p-4">@csrf @method('PUT')
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="mb-3"><label class="form-label" for="url">URL WebSocket do LiveKit</label><input class="form-control" id="url" name="url" required maxlength="500" placeholder="ws://localhost:7880 ou wss://livekit.seudominio.com" value="{{ old('url', $configuracao?->url) }}"><div class="form-text">Use <code>ws://localhost:7880</code> para o servidor local e <code>wss://</code> em produção.</div></div>
    <div class="mb-3"><label class="form-label" for="api_key">API Key</label><input class="form-control" id="api_key" name="api_key" required maxlength="500" autocomplete="off" value="{{ old('api_key', $configuracao?->api_key) }}"></div>
    <div class="mb-4"><label class="form-label" for="api_secret">API Secret</label><input class="form-control" type="password" id="api_secret" name="api_secret" maxlength="500" autocomplete="new-password" @required(!$configuracao)><div class="form-text">{{ $configuracao ? 'Deixe em branco para manter o segredo já salvo.' : 'Obrigatório na primeira configuração.' }}</div></div>
    <div class="alert alert-info small">A chave secreta é armazenada criptografada no banco de dados e não é exibida novamente.</div>
    <div class="d-flex justify-content-end gap-2"><a class="btn btn-outline-secondary" href="{{ route('transmissoes.index') }}">Cancelar</a><button class="btn btn-primary"><i class="bi bi-floppy me-1"></i>Salvar configuração</button></div>
</div></div></form>
@endsection
