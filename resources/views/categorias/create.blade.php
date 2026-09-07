@extends('layouts.app')
@section('title', 'Cadastrar categoria')
@section('content')
<div class="mb-4"><h1 class="page-title">Cadastrar categoria</h1><p class="page-description mb-0">Informe os dados da nova categoria de atividades.</p></div>
<form method="POST" action="{{ route('categorias.store') }}">@csrf @include('categorias.partials.form', ['categoria' => null])</form>
@endsection
