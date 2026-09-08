@php($estiloImagem = old('personalizacao', ($atividade ?? new \App\Models\Atividade)->estiloImagem()))
@php($imagemAtividade = $atividade?->estiloImagem()['imagem'])
<div class="card content-card mt-4">
    <div class="card-header"><h2 class="h5 fw-bold mb-0">Personalização da atividade</h2></div>
    <div class="card-body p-4">
        <p class="text-muted">Configure a imagem pequena exibida ao lado das informações no formulário público.</p>
        <div class="row g-4 align-items-start">
            <div class="col-12 col-lg-7">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="imagem_atividade">Imagem</label>
                        <input class="form-control @error('imagem_atividade') is-invalid @enderror" type="file" id="imagem_atividade" name="imagem_atividade" accept="image/jpeg,image/png,image/webp" data-imagem-atividade>
                        @error('imagem_atividade')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">JPG, PNG ou WebP, até 8 MB.</div>
                        @if($imagemAtividade)<a class="small" href="{{ route('eventos.personalizacao.imagem', ['arquivo' => $imagemAtividade]) }}" target="_blank" rel="noopener">Abrir imagem salva</a>@endif
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="imagem_posicao">Posição da imagem</label>
                        <select class="form-select" id="imagem_posicao" name="personalizacao[posicao]" data-posicao-imagem>
                            <option value="esquerda" @selected($estiloImagem['posicao'] === 'esquerda')>À esquerda do texto</option>
                            <option value="direita" @selected($estiloImagem['posicao'] === 'direita')>À direita do texto</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <input type="hidden" name="personalizacao[borda]" value="0">
                        <div class="form-check form-switch mt-md-4 pt-md-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="imagem_borda" name="personalizacao[borda]" value="1" @checked((bool)$estiloImagem['borda']) data-borda-imagem>
                            <label class="form-check-label" for="imagem_borda">Exibir borda na imagem</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="imagem_cor_borda">Cor da borda</label>
                        <div class="input-group color-pareada">
                            <input type="color" class="form-control form-control-color" id="imagem_cor_borda" value="{{ $estiloImagem['cor_borda'] }}" data-color-picker aria-label="Selecionar cor da borda">
                            <input type="text" class="form-control font-monospace @error('personalizacao.cor_borda') is-invalid @enderror" name="personalizacao[cor_borda]" value="{{ $estiloImagem['cor_borda'] }}" maxlength="7" pattern="#[0-9A-Fa-f]{6}" data-color-text aria-label="Código da cor da borda">
                        </div>
                        @error('personalizacao.cor_borda')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="border rounded-3 p-3">
                    <div class="small text-muted mb-2">Prévia</div>
                    <div class="d-flex gap-3 align-items-center" data-preview-atividade>
                        <img src="{{ $imagemAtividade ? route('eventos.personalizacao.imagem', ['arquivo' => $imagemAtividade]) : '' }}" alt="Prévia da atividade" width="120" height="86" class="rounded object-fit-cover flex-shrink-0 {{ $imagemAtividade ? '' : 'd-none' }}" data-preview-imagem>
                        <div data-preview-texto><strong class="d-block">Nome da atividade</strong><span class="small">Evento · Início · Fim</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@push('scripts')<script src="{{ asset('personalizacao-atividade.js') }}" defer></script>@endpush
