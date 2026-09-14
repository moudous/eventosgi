@extends('layouts.app')
@section('title', 'Alterar senha de submissão')
@section('content')
<div class="container py-5" style="max-width:680px"><div class="card content-card shadow-sm"><div class="card-body p-4 p-md-5">
    @if(!empty($sucesso))
        <h1 class="h3">Senha alterada com sucesso</h1>
        <p>Você já pode usar a nova senha nas suas submissões.</p>
        @if($atividadeAtualizada)<p class="alert alert-info">A senha de inscrição em atividade também foi alterada.</p>@endif
    @elseif(!$valido)
        <h1 class="h3">Link indisponível</h1>
        <p>Este link expirou ou já foi utilizado. Solicite uma nova senha temporária no formulário de submissão.</p>
    @else
        <h1 class="h3">Alterar senha de submissão</h1>
        <p>Defina a senha de <strong>{{ $email }}</strong>. Ela será usada nas suas submissões.</p>
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route('senha-submissao.atualizar', ['token' => $token]) }}">@csrf
            <div class="mb-3"><label class="form-label" for="senha">Nova senha</label><input type="password" id="senha" name="senha" class="form-control" minlength="8" required autocomplete="new-password"><div class="form-text">Use pelo menos 8 caracteres, com letras e números.</div></div>
            <div class="mb-3"><label class="form-label" for="senha_confirmation">Confirmar nova senha</label><input type="password" id="senha_confirmation" name="senha_confirmation" class="form-control" minlength="8" required autocomplete="new-password"></div>
            <input type="hidden" name="usar_na_atividade" value="0">
            <div class="form-check mb-4"><input type="checkbox" class="form-check-input" id="usar_na_atividade" name="usar_na_atividade" value="1" @checked(old('usar_na_atividade', true))><label class="form-check-label" for="usar_na_atividade">Alterar senha de inscrição em atividade, caso exista.</label></div>
            <button class="btn btn-primary w-100">Salvar nova senha</button>
        </form>
    @endif
</div></div></div>
@endsection
