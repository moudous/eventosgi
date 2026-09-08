<div class="d-inline-flex flex-wrap gap-1">
@if($trabalho->trashed() && $permissoes->permite('submissoes.trabalhos.restaurar'))
<button type="button" class="btn btn-sm btn-outline-success" title="Restaurar trabalho" data-action="restore" data-method="PATCH" data-action-url="{{ route('submissoes.inscritos.restaurar', [$submissao, $trabalho->id]) }}"><i class="bi bi-arrow-counterclockwise"></i></button>
@endif
@if($trabalho->trashed() && $permissoes->permite('submissoes.trabalhos.excluir_definitivamente'))
<button type="button" class="btn btn-sm btn-outline-danger" title="Excluir definitivamente" data-action="force-delete" data-method="DELETE" data-action-url="{{ route('submissoes.inscritos.excluir-definitivamente', [$submissao, $trabalho->id]) }}"><i class="bi bi-trash3-fill"></i></button>
@endif
</div>
