<div class="d-inline-flex gap-1">
@if($permissoes->permite('convidados.visualizar'))<a href="{{route('convidados.show',$convidado)}}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar"><i class="bi bi-eye-fill"></i></a>@endif
@if($permissoes->permite('convidados.editar'))<a href="{{route('convidados.edit',$convidado)}}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar"><i class="bi bi-pencil-fill"></i></a>@endif
@if($permissoes->permite('convidados.excluir'))<button class="btn btn-sm btn-outline-danger listagem-acao" data-delete-url="{{route('convidados.destroy',$convidado)}}" title="Excluir"><i class="bi bi-trash-fill"></i></button>@endif
</div>
