@extends('layouts.app')
@section('title', 'Cadastrar evento')
@section('content')
<div class="mb-4"><h1 class="page-title">Cadastrar evento</h1><p class="page-description mb-0">Informe os dados do novo evento.</p></div>
<form method="POST" action="{{ route('eventos.store') }}">@csrf @include('eventos.partials.form', ['evento' => null])</form>
@endsection
