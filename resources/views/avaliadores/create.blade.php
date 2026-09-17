@extends('layouts.app')
@section('title','Cadastrar avaliador')
@section('content')<div class="mb-4"><h1 class="page-title">Cadastrar avaliador</h1><p class="page-description mb-0">Informe o nome completo. O vínculo com um usuário do sistema é opcional.</p></div><form method="POST" action="{{ route('avaliadores.store') }}">@csrf @include('avaliadores.partials.form')</form>@endsection
