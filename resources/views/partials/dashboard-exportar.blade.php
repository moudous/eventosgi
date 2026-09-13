<div class="d-flex gap-1 flex-shrink-0" data-export-actions role="group" aria-label="Exportar {{ $titulo }}">
    @foreach(['html' => ['bi-filetype-html', 'HTML'], 'pdf' => ['bi-filetype-pdf', 'PDF'], 'imagem' => ['bi-file-earmark-image', 'imagem PNG']] as $tipo => [$icone, $rotulo])
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('dashboard.exportar-link', ['card' => $card, 'formato' => $tipo, ...($filtros ?? [])]) }}" title="Baixar em {{ $rotulo }}" aria-label="Baixar {{ $titulo }} em {{ $rotulo }}"><i class="bi {{ $icone }}" aria-hidden="true"></i></a>
    @endforeach
</div>
