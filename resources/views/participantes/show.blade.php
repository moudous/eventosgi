@extends('layouts.app')
@section('title','Visualizar participante')
@section('content')<div class="mb-4 d-flex justify-content-between"><div><h1 class="page-title">Visualizar participante</h1><p class="page-description mb-0">Dados do participante no sistema de certificados.</p></div><a href="{{route('participantes.index')}}" class="btn btn-outline-secondary">Voltar</a></div>
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados do participante</h2></div><div class="card-body p-4"><div class="row g-4">
@foreach(['id'=>'ID','nome'=>'Nome','email'=>'E-mail','email2'=>'E-mail 2','email_institucional'=>'E-mail institucional','instituicao_ensino'=>'Instituição de ensino','cpf'=>'CPF'] as $campo=>$rotulo)<div class="col-12 col-md-4"><div class="small fw-bold text-secondary">{{$rotulo}}</div>{{$participante->{$campo}?:'—'}}</div>@endforeach
{{-- Sexo e uma letra no banco, gravada ora em maiuscula ora em minuscula (a collation
     do MySQL nao distingue, entao o valor bruto varia). Sem a leitura por extenso a tela
     obriga quem consulta a saber de cor o que a letra significa. --}}
@php($sexo = strtoupper(trim((string) $participante->sexo)))
<div class="col-12 col-md-4"><div class="small fw-bold text-secondary">Sexo</div>{{ ['M'=>'Masculino','F'=>'Feminino'][$sexo] ?? ($sexo ?: '—') }}</div>
<div class="col-12 col-md-4"><div class="small fw-bold text-secondary">Grupo</div>{{$participante->grupo?:'—'}}</div>
<div class="col-12 col-md-4"><div class="small fw-bold text-secondary">E-mail fictício</div>{{$participante->email_ficticio?'Sim — endereço gerado pelo sistema':'Não'}}</div>
<div class="col-md-4"><div class="small fw-bold text-secondary">Status</div>{{$participante->ativo?'Ativo':'Inativo'}}</div>
<div class="col-md-4"><div class="small fw-bold text-secondary">Criado por</div>{{ $criador?->nome ?? '—' }}</div>
<div class="col-md-4"><div class="small fw-bold text-secondary">Criado em</div>{{$participante->criado_em?->format('d/m/Y H:i')??'—'}}</div>
<div class="col-md-4"><div class="small fw-bold text-secondary">Atualizado em</div>{{$participante->atualizado_em?->format('d/m/Y H:i')??'—'}}</div>
</div></div></div>

<div class="card content-card mt-4"><div class="card-header"><h2 class="h5 fw-bold mb-0">Certificados</h2></div><div class="card-body p-4"><div class="row g-4">
<div class="col-12 col-md-4"><div class="small fw-bold text-secondary">Antigos</div>{{ $certificados['antigos'] }}</div>
<div class="col-12 col-md-4"><div class="small fw-bold text-secondary">Novos</div>{{ $certificados['novos'] }}</div>
<div class="col-12 col-md-4"><div class="small fw-bold text-secondary">Total</div>{{ $certificados['total'] }}</div>
@if($certificados['total'] > 0)
<div class="col-12"><div class="alert alert-light border mb-0"><i class="bi bi-lock me-1"></i>Participante com certificado emitido não pode ser excluído.</div></div>
@endif
</div></div></div>
@endsection
