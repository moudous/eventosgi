<div class="card content-card mt-4">
    <div class="card-header"><h2 class="h5 fw-bold mb-0">Personalização dos formulários</h2></div>
    <div class="card-body p-4">
        <p class="text-muted">Escolha o fundo do card de cada formulário. O degradê, a cor sólida e a imagem ficam salvos, mesmo quando outro fundo está ativo.</p>
        @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif
        <div class="row g-4">
        @foreach(['submissao' => 'Submissão', 'atividade' => 'Atividade'] as $tipo => $rotulo)
            @php($estilo = old("personalizacao.$tipo", ($evento ?? new \App\Models\Evento)->estiloFormulario($tipo)))
            @php($imagem = $evento?->estiloFormulario($tipo)['imagem'])
            <div class="col-12 col-lg-6">
                <fieldset class="border rounded-3 p-3 h-100 personalizacao-editor" data-imagem="{{ $imagem ? route('eventos.personalizacao.imagem', ['arquivo' => $imagem]) : '' }}">
                    <legend class="float-none w-auto px-2 h5">{{ $rotulo }}</legend>
                    <label class="form-label" for="fundo_{{ $tipo }}">Fundo ativo</label>
                    <select class="form-select mb-3" id="fundo_{{ $tipo }}" name="personalizacao[{{ $tipo }}][tipo]" data-campo="tipo">
                        @foreach(['degrade' => 'Degradê', 'solida' => 'Cor sólida', 'imagem' => 'Imagem'] as $valor => $texto)
                        <option value="{{ $valor }}" @selected($estilo['tipo'] === $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                    <div class="row g-3 mb-3">
                    @foreach(['degrade_inicio' => 'Degradê: cor inicial', 'degrade_fim' => 'Degradê: cor final', 'cor_solida' => 'Cor sólida', 'cor_fonte' => 'Cor da fonte'] as $campo => $texto)
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="{{ $tipo }}_{{ $campo }}">{{ $texto }}</label>
                            <div class="input-group color-pareada">
                                <input type="color" class="form-control form-control-color" id="{{ $tipo }}_{{ $campo }}" value="{{ $estilo[$campo] }}" data-color-picker aria-label="Selecionar {{ strtolower($texto) }}">
                                <input type="text" class="form-control font-monospace" name="personalizacao[{{ $tipo }}][{{ $campo }}]" value="{{ $estilo[$campo] }}" maxlength="7" pattern="#[0-9A-Fa-f]{6}" data-color-text data-campo="{{ $campo }}" aria-label="Código de {{ strtolower($texto) }}">
                            </div>
                        </div>
                    @endforeach
                    </div>
                    <label class="form-label" for="imagem_{{ $tipo }}">Imagem de fundo</label>
                    <input class="form-control" type="file" id="imagem_{{ $tipo }}" name="imagem_{{ $tipo }}" accept="image/jpeg,image/png,image/webp" data-upload>
                    <div class="form-text">JPG, PNG ou WebP, até 8 MB. Após escolher, ajuste o recorte. A imagem salva terá acesso público.</div>
                    <p class="small text-danger mt-2" data-erro role="alert"></p>
                    @if($imagem)<a class="small" href="{{ route('eventos.personalizacao.imagem', ['arquivo' => $imagem]) }}" target="_blank" rel="noopener">Abrir imagem salva</a>@endif
                    <div class="rounded-3 p-4 mt-3" data-preview><strong class="d-block fs-5">{{ $rotulo }}</strong><span>Prévia das cores e do fundo do formulário</span></div>
                </fieldset>
            </div>
        @endforeach
        </div>
    </div>
</div>
<div class="modal fade" id="recorteFundo" tabindex="-1" aria-labelledby="recorteTitulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="recorteTitulo">Recortar imagem de fundo</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body">
            <canvas id="recorteCanvas" width="1200" height="360" class="w-100 rounded border"></canvas>
            <p class="form-text">Ajuste a ampliação e a posição para escolher a área do card. Em telas menores, as bordas da imagem podem ficar ocultas.</p>
            <label for="recorteZoom" class="form-label">Ampliação</label><input id="recorteZoom" type="range" class="form-range" min="1" max="4" step="0.01" value="1">
            <label for="recorteX" class="form-label">Posição horizontal</label><input id="recorteX" type="range" class="form-range" min="0" max="1" step="0.01" value="0.5">
            <label for="recorteY" class="form-label">Posição vertical</label><input id="recorteY" type="range" class="form-range" min="0" max="1" step="0.01" value="0.5">
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="aplicarRecorte">Usar recorte</button></div>
    </div></div>
</div>
@push('scripts')<script src="{{ asset('personalizacao-evento.js') }}" defer></script>@endpush
