<div class="d-flex gap-1 flex-shrink-0" data-export-actions role="group" aria-label="Exportar {{ $titulo }}">
    @foreach(['html' => ['bi-filetype-html', 'HTML interativo'], 'pdf' => ['bi-filetype-pdf', 'PDF'], 'imagem' => ['bi-file-earmark-image', 'imagem PNG']] as $tipo => [$icone, $rotulo])
    <button type="button" class="btn btn-sm btn-outline-secondary" data-dashboard-export="{{ $tipo }}" title="Exportar para {{ $rotulo }}" aria-label="Exportar {{ $titulo }} para {{ $rotulo }}"><i class="bi {{ $icone }}" aria-hidden="true"></i></button>
    @endforeach
</div>
