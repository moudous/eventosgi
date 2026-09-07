@extends('layouts.app')
@section('title', 'Editar categoria')
@section('content')
<div class="mb-4"><h1 class="page-title">Editar categoria</h1><p class="page-description mb-0">Atualize os dados da categoria.</p></div>
<form method="POST" action="{{ route('categorias.update', $categoria) }}">@csrf @method('PUT') @include('categorias.partials.form')</form>
@endsection
