@php($vinculada = ($categoria->atividades_count ?? 0) > 0)
<div class="d-inline-flex gap-1">
@if($permissoes->permite('categorias.ativar_desativar'))
    {{-- Categoria em uso não é desativada: some o botão de desligar, fica o de ligar. --}}
    @if(! ($categoria->ativo && $vinculada))
        <button type="button" class="btn btn-sm {{ $categoria->ativo ? 'btn-outline-secondary' : 'btn-outline-success' }} listagem-acao" title="{{ $categoria->ativo ? 'Desativar' : 'Ativar' }}" data-action="toggle" data-method="PATCH" data-action-url="{{ route('categorias.alternar', $categoria) }}"><i class="bi {{ $categoria->ativo ? 'bi-pause-fill' : 'bi-play-fill' }}"></i></button>
    @endif
@endif
@if($permissoes->permite('categorias.visualizar'))<a href="{{ route('categorias.show', $categoria) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar"><i class="bi bi-eye-fill"></i></a>@endif
@if($permissoes->permite('categorias.editar'))<a href="{{ route('categorias.edit', $categoria) }}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar"><i class="bi bi-pencil-fill"></i></a>@endif
{{-- Categoria com atividade vinculada não é excluída; o controller recusa mesmo assim. --}}
@if($permissoes->permite('categorias.excluir') && ! $vinculada)<button type="button" class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir" data-action="delete" data-method="DELETE" data-action-url="{{ route('categorias.destroy', $categoria) }}"><i class="bi bi-trash-fill"></i></button>@endif
</div>
