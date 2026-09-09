<div class="mb-4">
    <h3 class="h6 text-uppercase text-muted">Dados do participante</h3>
    <div class="row g-3">
        @foreach($dadosParticipante as $dado)
            <div class="col-12 col-md-6"><label class="form-label">{{ $dado['label'] }}</label><div class="form-control bg-light h-auto text-break" aria-readonly="true">{{ $dado['valor'] }}</div></div>
        @endforeach
    </div>
</div>
<div>
    <h3 class="h6 text-uppercase text-muted">Respostas do formulário</h3>
    <div class="row g-3">
        @forelse($respostas as $resposta)
            <div class="col-12 col-md-6"><label class="form-label">{{ $resposta['label'] }}</label><div class="form-control bg-light h-auto text-break" aria-readonly="true">{{ $resposta['valor'] }}</div></div>
        @empty
            <div class="col-12"><p class="text-muted mb-0">Este formulário não possui respostas adicionais.</p></div>
        @endforelse
    </div>
</div>
