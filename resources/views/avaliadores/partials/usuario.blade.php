<div class="d-flex align-items-center gap-2">
@if($usuario?->foto_url)<img src="{{ $usuario->foto_url }}" alt="" width="36" height="36" class="rounded-circle flex-shrink-0" referrerpolicy="no-referrer" style="object-fit:cover">@else<div class="rounded-circle bg-secondary-subtle text-secondary d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px"><i class="bi bi-person-fill"></i></div>@endif
<div><div class="fw-semibold">{{ $usuario?->nome ?? 'Sem usuário associado' }}</div><div class="small text-secondary">{{ $usuario?->email ?: ($usuario ? 'Sem e-mail' : 'Vínculo opcional') }}</div></div>
</div>
