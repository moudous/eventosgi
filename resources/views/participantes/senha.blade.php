@extends('layouts.app')
@section('title', 'Definir senha de inscrição')
@section('content')
<div class="container py-5" style="max-width:680px">
    <div class="card content-card shadow-sm"><div class="card-body p-4 p-md-5">
        @if(!empty($sucesso))
            <div class="text-center">
                <i class="bi bi-check-circle-fill text-success fs-1"></i>
                <h1 class="h3 mt-3">Senha definida com sucesso</h1>
                <p class="text-muted mb-0">{{ $nome }}, sua nova senha já pode ser usada para se identificar nas inscrições de outros eventos e atividades.</p>
                @if($submissoesAtualizadas > 0)<p class="alert alert-info mt-4 mb-0">A senha também foi atualizada em {{ $submissoesAtualizadas }} {{ $submissoesAtualizadas === 1 ? 'cadastro de submissão' : 'cadastros de submissão' }}.</p>@endif
            </div>
        @elseif(!$valido)
            <div class="text-center">
                <i class="bi bi-link-45deg text-warning fs-1"></i>
                <h1 class="h3 mt-3">Link indisponível</h1>
                <p class="text-muted mb-0">Este link expirou ou já foi utilizado. Solicite um novo código em um formulário de inscrição para receber outro link.</p>
            </div>
        @else
            <h1 class="h3">Definir senha de inscrição</h1>
            <p class="text-muted">Olá, <strong>{{ $nome }}</strong>. Defina uma senha para <strong>{{ $email }}</strong>. Ela poderá ser usada para se inscrever em outros eventos e atividades.</p>
            @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif
            <form method="POST" action="{{ route('senha-participante.atualizar', ['token' => $token]) }}">
                @csrf
                <div class="mb-3"><label class="form-label fw-semibold" for="senha">Nova senha</label><input class="form-control" type="password" id="senha" name="senha" minlength="8" required autocomplete="new-password"><div class="form-text">Use pelo menos 8 caracteres, com letras e números.</div></div>
                <div class="mb-4"><label class="form-label fw-semibold" for="senha_confirmation">Confirmar nova senha</label><input class="form-control" type="password" id="senha_confirmation" name="senha_confirmation" minlength="8" required autocomplete="new-password"></div>
                <input type="hidden" name="usar_na_submissao" value="0">
                <div class="form-check mb-4"><input class="form-check-input" type="checkbox" id="usar_na_submissao" name="usar_na_submissao" value="1" @checked(old('usar_na_submissao'))><label class="form-check-label" for="usar_na_submissao">Usar esta senha também nas submissões vinculadas a este e-mail</label></div>
                <button class="btn btn-primary w-100" type="submit"><i class="bi bi-key me-2"></i>Salvar nova senha</button>
            </form>
        @endif
    </div></div>
</div>
@endsection
