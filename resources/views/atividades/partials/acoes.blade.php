<div class="d-inline-flex gap-1">
@if($permissoes->permite('atividades.visualizar'))<button type="button" class="btn btn-sm btn-outline-secondary listagem-acao" title="Histórico" data-history-url="{{ route('atividades.historico',$atividade->id) }}" data-history-name="{{ $atividade->nome }}"><i class="bi bi-clock-history"></i></button>@endif
@if($apagados)
 @if($permissoes->permite('atividades.restaurar'))<button type="button" class="btn btn-sm btn-outline-success text-nowrap" data-action="restore" data-method="PATCH" data-action-url="{{ route('atividades.restore',$atividade->id) }}"><i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar</button>@endif
 @if($permissoes->permite('atividades.excluir_definitivamente'))<button type="button" class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir definitivamente" data-action="force-delete" data-method="DELETE" data-action-url="{{ route('atividades.force-destroy',$atividade->id) }}"><i class="bi bi-trash3-fill"></i></button>@endif
@else
 @if($permissoes->permite('atividades.editar'))<a href="{{ route('atividades.formulario',$atividade) }}" class="btn btn-sm btn-outline-success listagem-acao" title="Formulário"><i class="bi bi-ui-checks-grid"></i></a>@endif
 @if($permissoes->permite('atividades.visualizar'))<a href="{{ route('atividades.formulario.visualizar',$atividade) }}" class="btn btn-sm btn-outline-info listagem-acao" title="Visualizar formulário"><i class="bi bi-card-text"></i></a>@endif
 @if($permissoes->permite('atividades.visualizar'))<a href="{{ route('atividades.inscricoes',$atividade) }}" class="btn btn-sm btn-outline-warning listagem-acao" title="Visualizar inscrições"><i class="bi bi-person-lines-fill"></i></a>@endif
 @if($permissoes->permite('atividades.visualizar'))<a href="{{ route('atividades.show',$atividade) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar"><i class="bi bi-eye-fill"></i></a>@endif
 @if($permissoes->permite('atividades.editar'))<a href="{{ route('atividades.edit',$atividade) }}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar"><i class="bi bi-pencil-fill"></i></a>@endif
 @if($permissoes->permite('atividades.excluir'))<button type="button" class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir" data-action="delete" data-method="DELETE" data-action-url="{{ route('atividades.destroy',$atividade) }}"><i class="bi bi-trash-fill"></i></button>@endif
@endif
</div>
