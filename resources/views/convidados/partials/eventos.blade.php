<div class="d-flex flex-wrap gap-1">
@forelse($eventos as $evento)
    <span class="badge bg-white text-dark border border-secondary-subtle fw-normal">{{ $evento->nome }}</span>
@empty
    <span class="text-muted">Sem evento</span>
@endforelse
</div>
