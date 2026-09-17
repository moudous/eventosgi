@extends('layouts.app')
@section('title','Editar avaliador')
@section('content')<div class="mb-4"><h1 class="page-title">Editar avaliador</h1><p class="page-description mb-0">Atualize os dados e o usuário vinculado.</p></div><form method="POST" action="{{ route('avaliadores.update',$avaliador) }}">@csrf @method('PUT') @include('avaliadores.partials.form')</form>@endsection
