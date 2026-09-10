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
            <div class="col-12 col-md-6">
                <label class="form-label">{{ $resposta['label'] }}</label>
                <div class="form-control bg-light h-auto text-break" aria-readonly="true">
                    @if(!empty($resposta['arquivos']))
                        <div class="comprovante-anexos-visuais d-grid gap-2">
                            @foreach($resposta['arquivos'] as $arquivo)
                                <a href="{{ $arquivo['url'] }}" target="_blank" rel="noopener noreferrer" class="comprovante-anexo text-decoration-none text-body border rounded-3 p-2 d-flex align-items-center gap-2">
                                    @if($arquivo['imagem'])
                                        <img src="{{ $arquivo['url'] }}" alt="Miniatura de {{ $arquivo['nome'] }}" class="comprovante-anexo-miniatura" loading="lazy">
                                    @else
                                        <span class="comprovante-anexo-icone" aria-hidden="true"><i class="bi {{ $arquivo['icone'] }}"></i></span>
                                    @endif
                                    <span class="text-break">{{ $arquivo['nome'] }}</span>
                                </a>
                            @endforeach
                        </div>
                        <span class="comprovante-anexos-nomes">{{ collect($resposta['arquivos'])->pluck('nome')->join(', ') }}</span>
                    @else
                        {{ $resposta['valor'] }}
                    @endif
                </div>
            </div>
        @empty
            <div class="col-12"><p class="text-muted mb-0">Este formulário não possui respostas adicionais.</p></div>
        @endforelse
    </div>
</div>
