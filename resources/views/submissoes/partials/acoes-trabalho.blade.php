<div class="d-inline-flex flex-wrap gap-1">
@if(!$trabalho->trashed() && $permissoes->permite('submissoes.inscritos.trabalhos'))
<a href="{{ route('submissoes.inscritos.visualizar-trabalho', [$submissao, $trabalho->id]) }}" class="btn btn-sm btn-outline-info" title="Visualizar trabalho"><i class="bi bi-eye-fill"></i></a>
@endif
@if(!$trabalho->trashed() && $permissoes->permiteAlguma(['submissoes.avaliar', 'submissoes.trabalhos.alterar_status']))
<button type="button" class="btn btn-sm btn-outline-success" title="Aprovar trabalho" data-action="evaluate" data-decisao="aprovado" data-method="PATCH" data-action-url="{{ route('submissoes.inscritos.avaliar', [$submissao, $trabalho->id]) }}"><i class="bi bi-check-lg"></i></button>
<button type="button" class="btn btn-sm btn-outline-danger" title="Reprovar trabalho" data-action="evaluate" data-decisao="reprovado" data-method="PATCH" data-action-url="{{ route('submissoes.inscritos.avaliar', [$submissao, $trabalho->id]) }}"><i class="bi bi-x-lg"></i></button>
@endif
@if($trabalho->trashed() && $permissoes->permite('submissoes.trabalhos.restaurar'))
<button type="button" class="btn btn-sm btn-outline-success" title="Restaurar trabalho" data-action="restore" data-method="PATCH" data-action-url="{{ route('submissoes.inscritos.restaurar', [$submissao, $trabalho->id]) }}"><i class="bi bi-arrow-counterclockwise"></i></button>
@endif
@if($trabalho->trashed() && $permissoes->permite('submissoes.trabalhos.excluir_definitivamente'))
<button type="button" class="btn btn-sm btn-outline-danger" title="Excluir definitivamente" data-action="force-delete" data-method="DELETE" data-action-url="{{ route('submissoes.inscritos.excluir-definitivamente', [$submissao, $trabalho->id]) }}"><i class="bi bi-trash3-fill"></i></button>
@endif
</div>
