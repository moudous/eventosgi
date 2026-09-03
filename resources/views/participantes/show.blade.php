@extends('layouts.app')
@section('title','Visualizar participante')
@section('content')<div class="mb-4 d-flex justify-content-between"><div><h1 class="page-title">Visualizar participante</h1><p class="page-description mb-0">Dados do participante no sistema de certificados.</p></div><a href="{{route('participantes.index')}}" class="btn btn-outline-secondary">Voltar</a></div>
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados do participante</h2></div><div class="card-body p-4"><div class="row g-4">
@foreach(['id'=>'ID','nome'=>'Nome','email'=>'E-mail','email2'=>'E-mail 2','email_institucional'=>'E-mail institucional','instituicao_ensino'=>'Instituição de ensino','cpf'=>'CPF','sexo'=>'Sexo','grupo'=>'Grupo'] as $campo=>$rotulo)<div class="col-12 col-md-4"><div class="small fw-bold text-secondary">{{$rotulo}}</div>{{$participante->{$campo}?:'—'}}</div>@endforeach
<div class="col-md-4"><div class="small fw-bold text-secondary">Status</div>{{$participante->ativo?'Ativo':'Inativo'}}</div><div class="col-md-4"><div class="small fw-bold text-secondary">Criado em</div>{{$participante->criado_em?->format('d/m/Y H:i')??'—'}}</div><div class="col-md-4"><div class="small fw-bold text-secondary">Atualizado em</div>{{$participante->atualizado_em?->format('d/m/Y H:i')??'—'}}</div>
</div></div></div>@endsection
