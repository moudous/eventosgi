<div class="d-inline-flex gap-1">
@php($possuiInscritos = ($atividade->inscricoes_count ?? 0) > 0)
@if($permissoes->permite('atividades.historico'))<button type="button" class="btn btn-sm btn-outline-secondary listagem-acao" title="Histórico" data-history-url="{{ route('atividades.historico',$atividade->id) }}" data-history-name="{{ $atividade->nome }}"><i class="bi bi-clock-history"></i></button>@endif
@if($apagados)
 @if($permissoes->permite('atividades.restaurar'))<button type="button" class="btn btn-sm btn-outline-success text-nowrap" data-action="restore" data-method="PATCH" data-action-url="{{ route('atividades.restore',$atividade->id) }}"><i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar</button>@endif
 @if($permissoes->permite('atividades.excluir_definitivamente') && ! $possuiInscritos)<button type="button" class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir definitivamente" data-action="force-delete" data-method="DELETE" data-action-url="{{ route('atividades.force-destroy',$atividade->id) }}"><i class="bi bi-trash3-fill"></i></button>@endif
@else
 @if($permissoes->permite('atividades.convidados.visualizar') || $permissoes->permite('atividades.convidados.editar'))<a href="{{ route('atividades.convidados.edit', $atividade) }}" class="btn btn-sm btn-outline-primary listagem-acao" title="Convidados / Palestrantes" aria-label="Convidados / Palestrantes"><i class="bi bi-people-fill" aria-hidden="true"></i></a>@endif
 @if($permissoes->permite('atividades.formulario'))<a href="{{ route('atividades.formulario',$atividade) }}" class="btn btn-sm btn-outline-success listagem-acao" title="Formulário"><i class="bi bi-ui-checks-grid"></i></a>@endif
 @if($permissoes->permite('atividades.visualizar_formulario'))<a href="{{ $atividade->urlPublica() }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info listagem-acao" title="Visualizar formulário público" aria-label="Visualizar o formulário público de {{ $atividade->nome }}"><i class="bi bi-card-text"></i></a>@endif
 @if($permissoes->permite('atividades.inscritos'))<a href="{{ route('atividades.inscricoes',$atividade) }}" class="btn btn-sm btn-outline-warning listagem-acao" title="Visualizar inscrições"><i class="bi bi-person-lines-fill"></i></a>@endif
 @if($permissoes->permite('atividades.wordpress'))<button type="button" class="btn btn-sm btn-outline-dark listagem-acao" title="Gerar iframe para o WordPress" aria-label="Gerar iframe da atividade {{ $atividade->nome }}" data-iframe-url="{{ $atividade->urlPublica() }}" data-iframe-title="{{ $atividade->nome }}"><i class="bi bi-wordpress"></i></button>@endif
 @if($permissoes->permite('atividades.visualizar'))<a href="{{ route('atividades.show',$atividade) }}" class="btn btn-sm btn-outline-dark listagem-acao" title="Visualizar"><i class="bi bi-eye-fill"></i></a>@endif
 @if($permissoes->permite('atividades.editar'))<a href="{{ route('atividades.edit',$atividade) }}" class="btn btn-sm btn-outline-primary listagem-acao" title="Editar"><i class="bi bi-pencil-fill"></i></a>@endif
 @if($permissoes->permite('atividades.excluir') && ! $possuiInscritos)<button type="button" class="btn btn-sm btn-outline-danger listagem-acao" title="Excluir" data-action="delete" data-method="DELETE" data-action-url="{{ route('atividades.destroy',$atividade) }}"><i class="bi bi-trash-fill"></i></button>@endif
@endif
</div>
