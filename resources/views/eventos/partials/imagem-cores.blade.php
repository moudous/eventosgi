<div class="card content-card mt-4" id="imagem-cores-evento">
    <div class="card-header"><h2 class="h5 fw-bold mb-0">Imagem e cores do evento</h2></div>
    <div class="card-body p-4">
        <div class="row g-4">
            <div class="col-lg-6">
                <label for="imagem_evento" class="form-label fw-semibold">Imagem do evento</label>
                <input type="file" id="imagem_evento" name="imagem_evento" class="form-control" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPG, PNG ou WebP, até 8 MB. Para recortar, arraste sobre a prévia e clique em Aplicar recorte.</div>
                @error('imagem_evento')<div class="text-danger">{{ $message }}</div>@enderror
                <canvas id="evento-previa" class="mt-3 border rounded" style="max-width:100%;height:auto;touch-action:none;cursor:crosshair" hidden aria-label="Prévia da imagem. Arraste para selecionar um recorte."></canvas>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="evento-recortar" disabled>Aplicar recorte</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="evento-restaurar" disabled>Restaurar original</button>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
                    <div><label for="evento-quantidade" class="form-label">Quantidade de cores</label>
                    <select id="evento-quantidade" class="form-select">@for($i = 1; $i <= 32; $i++)<option value="{{ $i }}" @selected($i === 5)>{{ $i }}</option>@endfor</select></div>
                    <button type="button" class="btn btn-outline-primary" id="evento-extrair" disabled>Extrair cores</button>
                </div>
                <p class="text-muted small">A extração agrupa tons semelhantes e ordena as cores da mais frequente para a menos frequente. Edite pelo código hexadecimal ou pelo seletor de cor.</p>
                <input type="hidden" name="cores" value="">
                <div id="evento-cores" class="d-grid gap-2" style="grid-template-columns:repeat(3,minmax(0,1fr))"></div>
                <button type="button" id="evento-adicionar-cor" class="btn btn-outline-secondary btn-sm mt-3">Adicionar cor</button>
                @foreach($errors->get('cores*') as $mensagens) @foreach($mensagens as $mensagem)<div class="text-danger">{{ $mensagem }}</div>@endforeach @endforeach
            </div>
        </div>
        <div id="evento-imagem-status" class="small mt-3" role="status" aria-live="polite"></div>
    </div>
</div>
@push('scripts')
<script>
window.eventoImagemCores = {
    cores: @json(old('cores', $evento?->cores ?? [])),
    imagem: @json($evento?->imagem ? route('eventos.personalizacao.imagem', ['arquivo' => $evento->imagem]) : null)
};
</script>
<script src="{{ asset('imagem-cores-evento.js') }}?v={{ filemtime(public_path('imagem-cores-evento.js')) }}"></script>
@endpush
