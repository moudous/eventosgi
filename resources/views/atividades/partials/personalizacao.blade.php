@php
    $atividadeBase = $atividade ?? new \App\Models\Atividade;
    $estiloEvento = $atividade?->evento?->estiloFormulario('atividade') ?? (new \App\Models\Evento)->estiloFormulario('atividade');
    $personalizacaoSalva = $atividade?->personalizacao ?? [];
    $padraoAtividade = array_replace($atividadeBase->estiloImagem(), [
        'tipo' => $personalizacaoSalva['tipo'] ?? $estiloEvento['tipo'],
        'degrade_inicio' => $personalizacaoSalva['degrade_inicio'] ?? $estiloEvento['degrade_inicio'],
        'degrade_fim' => $personalizacaoSalva['degrade_fim'] ?? $estiloEvento['degrade_fim'],
        'cor_solida' => $personalizacaoSalva['cor_solida'] ?? $estiloEvento['cor_solida'],
        'cor_fonte' => $personalizacaoSalva['cor_fonte'] ?? $estiloEvento['cor_fonte'],
        'cor_borda_card' => $personalizacaoSalva['cor_borda_card'] ?? '#ffffff',
    ]);
    $estilo = array_replace($padraoAtividade, old('personalizacao', []));
    $usarEvento = (bool) old('personalizacao.usar_formatacao_evento', array_key_exists('usar_formatacao_evento', $personalizacaoSalva) ? $personalizacaoSalva['usar_formatacao_evento'] : true);
    $alterarPagina = (bool) ($estilo['alterar_cor_fundo_pagina'] ?? false);
    $imagemAtividade = $personalizacaoSalva['imagem'] ?? null;
    $imagemFundoCard = $personalizacaoSalva['imagem_fundo_card'] ?? null;
    $imagemFundoPagina = $personalizacaoSalva['imagem_fundo_pagina'] ?? null;
    $urlImagem = fn ($arquivo) => $arquivo ? route('eventos.personalizacao.imagem', ['arquivo' => $arquivo]) : '';
    $estilosEventos = collect($eventos ?? [])->mapWithKeys(function ($eventoItem) use ($urlImagem) {
        $visual = $eventoItem->estiloFormulario('atividade');
        return [(string) $eventoItem->id => ['estilo' => $visual, 'imagem' => $urlImagem($visual['imagem']), 'fundo_pagina' => $eventoItem->corFundoPagina('atividade')]];
    });
@endphp
<script type="application/json" id="estilosEventosAtividade">@json($estilosEventos)</script>

<div class="card content-card mt-4" data-fundo-pagina-card data-imagem-fundo-pagina="{{ $urlImagem($imagemFundoPagina) }}" data-fundo-pagina-evento="{{ $atividade?->evento?->corFundoPagina('atividade') ?? \App\Models\Evento::COR_FUNDO_PAGINA_ATIVIDADE }}">
    <div class="card-header"><h2 class="h5 fw-bold mb-0">Fundo da página do formulário</h2></div>
    <div class="card-body p-4">
        <p class="text-muted">Altera a área externa ao card de título e ao formulário. A cor e a imagem ficam salvas para você alternar entre elas.</p>
        <input type="hidden" name="personalizacao[alterar_cor_fundo_pagina]" value="0">
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="alterar_cor_fundo_pagina" name="personalizacao[alterar_cor_fundo_pagina]" value="1" @checked($alterarPagina) data-alterar-fundo-pagina><label class="form-check-label fw-semibold" for="alterar_cor_fundo_pagina">Alterar cor de fundo da página</label></div>
        <div data-cor-fundo-pagina-container @if(!$alterarPagina) hidden @endif>
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4"><label class="form-label" for="fundo_pagina_tipo">Fundo ativo da página</label><select class="form-select" id="fundo_pagina_tipo" name="personalizacao[fundo_pagina_tipo]" data-fundo-pagina-tipo><option value="cor" @selected(($estilo['fundo_pagina_tipo'] ?? 'cor') === 'cor')>Cor sólida</option><option value="imagem" @selected(($estilo['fundo_pagina_tipo'] ?? 'cor') === 'imagem')>Imagem</option></select></div>
                <div class="col-12 col-md-4"><label class="form-label" for="cor_fundo_pagina_atividade">Cor de fundo da página</label><div class="input-group color-pareada"><input type="color" class="form-control form-control-color" id="cor_fundo_pagina_atividade" value="{{ $estilo['cor_fundo_pagina'] }}" data-fundo-color-picker><input type="text" class="form-control font-monospace @error('personalizacao.cor_fundo_pagina') is-invalid @enderror" name="personalizacao[cor_fundo_pagina]" value="{{ $estilo['cor_fundo_pagina'] }}" maxlength="7" pattern="#[0-9A-Fa-f]{6}" data-fundo-color-text></div>@error('personalizacao.cor_fundo_pagina')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                <div class="col-12 col-md-4"><label class="form-label" for="imagem_fundo_pagina_atividade">Imagem de fundo da página</label><input type="hidden" name="remover_imagem_fundo_pagina_atividade" value="0" data-remove-background-input><input class="form-control @error('imagem_fundo_pagina_atividade') is-invalid @enderror" type="file" id="imagem_fundo_pagina_atividade" name="imagem_fundo_pagina_atividade" accept="image/jpeg,image/png,image/webp" data-background-upload="pagina">@error('imagem_fundo_pagina_atividade')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="d-flex gap-2 align-items-center mt-2">@if($imagemFundoPagina)<a class="small" href="{{ $urlImagem($imagemFundoPagina) }}" target="_blank" rel="noopener" data-background-link>Abrir imagem salva</a>@endif<button type="button" class="btn btn-sm btn-outline-danger {{ $imagemFundoPagina ? '' : 'd-none' }}" data-remove-background><i class="bi bi-trash me-1"></i>Remover</button></div></div>
            </div>
        </div>
        <div class="form-text mt-2">Quando desativado, o fundo da página segue a configuração do evento.</div>
    </div>
</div>

<div class="card content-card mt-4" data-personalizacao-atividade data-imagem-fundo-card="{{ $urlImagem($imagemFundoCard) }}" data-imagem-evento="{{ $urlImagem($estiloEvento['imagem']) }}" data-evento-estilo='@json($estiloEvento)'>
    <div class="card-header"><h2 class="h5 fw-bold mb-0">Personalização da atividade</h2></div>
    <div class="card-body p-4">
        <input type="hidden" name="personalizacao[usar_formatacao_evento]" value="0">
        <div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" role="switch" id="usar_formatacao_evento" name="personalizacao[usar_formatacao_evento]" value="1" @checked($usarEvento) data-usar-formatacao-evento><label class="form-check-label fw-semibold" for="usar_formatacao_evento">Usar formatação do evento</label></div>
        <p class="form-text mb-4">As configurações próprias permanecem salvas quando você volta a usar a formatação do evento.</p>

        <div class="border rounded-3 p-3 mb-4" data-formatacao-propria @if($usarEvento) hidden @endif>
            <h3 class="h6 fw-bold">Fundo do card de título</h3><p class="small text-muted">Define o fundo atrás do nome, evento e período da atividade. Não altera o fundo geral da página.</p>
            <div class="row g-3">
                <div class="col-12 col-md-4"><label class="form-label" for="fundo_card_atividade">Fundo ativo do card</label><select class="form-select" id="fundo_card_atividade" name="personalizacao[tipo]" data-card-field="tipo"><option value="degrade" @selected($estilo['tipo']==='degrade')>Degradê</option><option value="solida" @selected($estilo['tipo']==='solida')>Cor sólida</option><option value="imagem" @selected($estilo['tipo']==='imagem')>Imagem</option><option value="transparente" @selected($estilo['tipo']==='transparente')>Transparente</option><option value="transparente_borda" @selected($estilo['tipo']==='transparente_borda')>Transparente com borda</option></select></div>
                @foreach(['degrade_inicio'=>'Degradê: cor inicial','degrade_fim'=>'Degradê: cor final','cor_solida'=>'Cor sólida do card','cor_fonte'=>'Cor do texto do card','cor_borda_card'=>'Cor da borda do card'] as $campo=>$rotulo)
                    <div class="col-12 col-md-4" @if($campo === 'cor_borda_card') data-cor-borda-card-container @if($estilo['tipo'] !== 'transparente_borda') hidden @endif @endif><label class="form-label" for="atividade_{{ $campo }}">{{ $rotulo }}</label><div class="input-group color-pareada"><input type="color" class="form-control form-control-color" id="atividade_{{ $campo }}" value="{{ $estilo[$campo] }}" data-card-color-picker><input type="text" class="form-control font-monospace @error('personalizacao.'.$campo) is-invalid @enderror" name="personalizacao[{{ $campo }}]" value="{{ $estilo[$campo] }}" maxlength="7" pattern="#[0-9A-Fa-f]{6}" data-card-field="{{ $campo }}"></div>@error('personalizacao.'.$campo)<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                @endforeach
                <div class="col-12 col-md-8"><label class="form-label" for="imagem_fundo_card_atividade">Imagem de fundo do card de título</label><input type="hidden" name="remover_imagem_fundo_card_atividade" value="0" data-remove-background-input><input class="form-control @error('imagem_fundo_card_atividade') is-invalid @enderror" type="file" id="imagem_fundo_card_atividade" name="imagem_fundo_card_atividade" accept="image/jpeg,image/png,image/webp" data-background-upload="card">@error('imagem_fundo_card_atividade')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="d-flex gap-2 align-items-center mt-2">@if($imagemFundoCard)<a class="small" href="{{ $urlImagem($imagemFundoCard) }}" target="_blank" rel="noopener" data-background-link>Abrir imagem salva</a>@endif<button type="button" class="btn btn-sm btn-outline-danger {{ $imagemFundoCard ? '' : 'd-none' }}" data-remove-background><i class="bi bi-trash me-1"></i>Remover</button></div></div>
            </div>
        </div>

        <div class="row g-4 align-items-start">
            <div class="col-12 col-lg-7">
                <h3 class="h6 fw-bold">Imagem da atividade no card de título</h3><p class="small text-muted">Imagem pequena exibida ao lado das informações. Ela é independente da imagem usada como fundo do card.</p>
                <div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label" for="imagem_atividade">Imagem do card</label><input type="hidden" name="remover_imagem_atividade" value="0" data-remover-imagem-atividade><input class="form-control @error('imagem_atividade') is-invalid @enderror" type="file" id="imagem_atividade" name="imagem_atividade" accept="image/jpeg,image/png,image/webp" data-imagem-atividade>@error('imagem_atividade')<div class="invalid-feedback">{{ $message }}</div>@enderror<div class="form-text" data-imagem-status>JPG, PNG ou WebP, até 8 MB. Após escolher, arraste sobre a imagem para definir o recorte.</div><div class="d-flex flex-wrap gap-2 mt-2 align-items-center">@if($imagemAtividade)<a class="small" href="{{ $urlImagem($imagemAtividade) }}" target="_blank" rel="noopener" data-imagem-salva-link>Abrir imagem salva</a>@endif<button type="button" class="btn btn-sm btn-outline-danger {{ $imagemAtividade ? '' : 'd-none' }}" data-remover-imagem><i class="bi bi-trash me-1"></i>Remover imagem</button></div></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="imagem_posicao">Posição da imagem</label><select class="form-select" id="imagem_posicao" name="personalizacao[posicao]" data-posicao-imagem><option value="esquerda" @selected($estilo['posicao']==='esquerda')>À esquerda do texto</option><option value="direita" @selected($estilo['posicao']==='direita')>À direita do texto</option></select></div>
                    <div class="col-12 col-md-6"><input type="hidden" name="personalizacao[borda]" value="0"><div class="form-check form-switch mt-md-4 pt-md-2"><input class="form-check-input" type="checkbox" role="switch" id="imagem_borda" name="personalizacao[borda]" value="1" @checked((bool)$estilo['borda']) data-borda-imagem><label class="form-check-label" for="imagem_borda">Exibir borda na imagem</label></div></div>
                    <div class="col-12 col-md-6"><label class="form-label" for="imagem_cor_borda">Cor da borda da imagem</label><div class="input-group color-pareada"><input type="color" class="form-control form-control-color" id="imagem_cor_borda" value="{{ $estilo['cor_borda'] }}" data-color-picker><input type="text" class="form-control font-monospace @error('personalizacao.cor_borda') is-invalid @enderror" name="personalizacao[cor_borda]" value="{{ $estilo['cor_borda'] }}" maxlength="7" pattern="#[0-9A-Fa-f]{6}" data-color-text></div>@error('personalizacao.cor_borda')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
                </div>
            </div>
            <div class="col-12 col-lg-5"><div class="rounded-3 p-3" data-preview-pagina><div class="small mb-2" data-preview-label>Prévia do fundo da página e do card de título</div><div class="rounded-3 p-4" data-preview-card><div class="d-flex gap-3 align-items-center" data-preview-atividade><img src="{{ $urlImagem($imagemAtividade) }}" alt="Prévia da atividade" width="120" height="86" class="rounded object-fit-cover flex-shrink-0 {{ $imagemAtividade ? '' : 'd-none' }}" data-preview-imagem><div data-preview-texto><strong class="d-block">{{ old('nome', $atividade?->nome ?? 'Nome da atividade') }}</strong><span class="small">Evento · Início · Fim</span></div></div></div></div></div>
        </div>
    </div>
</div>

<div class="modal fade" id="recorteImagemAtividade" tabindex="-1" aria-labelledby="recorteImagemAtividadeTitulo" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><div><h2 class="modal-title fs-5" id="recorteImagemAtividadeTitulo">Recortar imagem da atividade</h2><p class="small text-muted mb-0">Clique e arraste sobre a imagem para marcar a área que será enviada.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div><div class="modal-body text-center bg-body-tertiary"><canvas class="mw-100 bg-dark rounded shadow-sm" style="height:auto;cursor:crosshair;touch-action:none" data-recorte-canvas></canvas><div class="small text-muted mt-2" data-recorte-status>Arraste sobre a imagem para selecionar o recorte.</div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" data-aplicar-recorte disabled><i class="bi bi-crop me-1"></i>Usar área selecionada</button></div></div></div></div>

@push('scripts')<script src="{{ asset('personalizacao-atividade.js') }}?v={{ filemtime(public_path('personalizacao-atividade.js')) }}" defer></script><script src="{{ asset('cor-fundo-pagina.js') }}?v={{ filemtime(public_path('cor-fundo-pagina.js')) }}" defer></script>@endpush
