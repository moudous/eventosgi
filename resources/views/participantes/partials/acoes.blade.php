@php($params=['id'=>$participante->id,'nome'=>$participante->nome])
<div class="d-inline-flex gap-1">
@if($permissoes->permite('participantes.visualizar'))<a href="{{route('participantes.show',$params)}}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar"><i class="bi bi-eye-fill"></i></a>@endif
@if($permissoes->permite('participantes.editar'))<a href="{{route('participantes.edit',$params)}}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar"><i class="bi bi-pencil-fill"></i></a>@endif
@if($permissoes->permite('participantes.excluir'))<button class="btn btn-sm btn-outline-danger listagem-acao" data-delete-url="{{route('participantes.destroy',$params)}}" title="Excluir"><i class="bi bi-trash-fill"></i></button>@endif
</div>
