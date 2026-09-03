@extends('layouts.app')
@section('title', 'Editar evento')
@section('content')
<div class="mb-4"><h1 class="page-title">Editar evento</h1><p class="page-description mb-0">Atualize os dados do evento.</p></div>
<form method="POST" action="{{ route('eventos.update', $evento) }}">@csrf @method('PUT') @include('eventos.partials.form')</form>
@endsection
