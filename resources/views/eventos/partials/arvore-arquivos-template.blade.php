<ul class="source-file-tree">
@foreach($nos as $no)
    @if($no['caminho'] === null)
        <li class="source-folder">
            <span><i class="bi bi-folder2" aria-hidden="true"></i>{{ $no['nome'] }}</span>
            @include('eventos.partials.arvore-arquivos-template', ['nos' => $no['filhos']])
        </li>
    @else
        @php($extensao = strtolower(pathinfo($no['caminho'], PATHINFO_EXTENSION)))
        <li>
            <button type="button" class="source-file source-file-{{ $extensao }} {{ $no['editavel'] ? '' : 'source-file-readonly' }}" @if($no['editavel']) data-arquivo="{{ $no['caminho'] }}" @else disabled title="Arquivo não textual: apenas visualização na árvore." @endif>
                <i class="bi {{ match($extensao) {'html','htm' => 'bi-filetype-html', 'css' => 'bi-filetype-css', 'js' => 'bi-filetype-js', 'json' => 'bi-braces', 'svg' => 'bi-filetype-svg', default => 'bi-file-earmark'} }}" aria-hidden="true"></i><span>{{ $no['nome'] }}</span><small>{{ strtoupper($extensao ?: 'ARQ') }}</small>
            </button>
        </li>
    @endif
@endforeach
</ul>
