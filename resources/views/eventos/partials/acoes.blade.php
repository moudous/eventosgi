<div class="d-inline-flex gap-1">
@if($permissoes->permite('eventos.visualizar'))
    <button type="button" class="btn btn-sm btn-outline-secondary listagem-acao" title="Histórico" aria-label="Histórico de {{ $evento->nome }}" data-history-url="{{ route('eventos.historico', $evento->id) }}" data-history-name="{{ $evento->nome }}"><i class="bi bi-clock-history"></i></button>
@endif
@if($apagados)
    @if($permissoes->permite('eventos.restaurar'))
        <button type="button" class="btn btn-sm btn-outline-success text-nowrap" title="Restaurar evento" aria-label="Restaurar {{ $evento->nome }}" data-action="restore" data-method="PATCH" data-action-url="{{ route('eventos.restore', $evento->id) }}"><i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar</button>
    @endif
    @if($permissoes->permite('eventos.excluir_definitivamente'))
        <button type="button" class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir definitivamente" aria-label="Excluir definitivamente {{ $evento->nome }}" data-action="force-delete" data-method="DELETE" data-action-url="{{ route('eventos.force-destroy', $evento->id) }}"><i class="bi bi-trash3-fill"></i></button>
    @endif
@else
    @if($permissoes->permite('eventos.visualizar'))
        <a href="{{ route('eventos.show', $evento) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar evento" aria-label="Visualizar {{ $evento->nome }}"><i class="bi bi-eye-fill"></i></a>
    @endif
    @if($permissoes->permite('eventos.editar'))
        <a href="{{ route('eventos.edit', $evento) }}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar evento" aria-label="Editar {{ $evento->nome }}"><i class="bi bi-pencil-fill"></i></a>
    @endif
    @if($permissoes->permite('eventos.excluir'))
        <button type="button" class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir evento" aria-label="Excluir {{ $evento->nome }}" data-action="delete" data-method="DELETE" data-action-url="{{ route('eventos.destroy', $evento) }}"><i class="bi bi-trash-fill"></i></button>
    @endif
@endif
</div>
