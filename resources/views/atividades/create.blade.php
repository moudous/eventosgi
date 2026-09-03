@extends('layouts.app')
@section('title','Cadastrar atividade')
@push('styles')<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">@endpush
@section('content')<div class="mb-4"><h1 class="page-title">Cadastrar atividade</h1><p class="page-description mb-0">Informe os dados da atividade.</p></div><form method="POST" action="{{route('atividades.store')}}">@csrf @include('atividades.partials.form',['atividade'=>null])</form>@endsection
@push('scripts')<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script><script>$('#evento_id').select2({theme:'bootstrap-5',width:'100%',placeholder:'Pesquise um evento'});</script>@endpush
