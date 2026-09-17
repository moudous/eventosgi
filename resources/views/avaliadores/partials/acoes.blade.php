<div class="d-inline-flex gap-1">
@if($permissoes->permite('avaliadores.visualizar'))<a href="{{ route('avaliadores.show',$avaliador) }}" class="btn btn-sm btn-outline-dark" title="Visualizar"><i class="bi bi-eye-fill"></i></a>@endif
@if($permissoes->permite('avaliadores.editar'))<a href="{{ route('avaliadores.edit',$avaliador) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil-fill"></i></a>@endif
@if($permissoes->permite('avaliadores.excluir'))<button type="button" class="btn btn-sm btn-outline-danger" title="Excluir" data-delete-url="{{ route('avaliadores.destroy',$avaliador) }}"><i class="bi bi-trash-fill"></i></button>@endif
</div>
