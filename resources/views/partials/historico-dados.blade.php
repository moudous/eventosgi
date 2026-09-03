@php
    $formatarValor = static function (mixed $valor): string {
        if ($valor === null || $valor === '') return '—';
        if (is_bool($valor)) return $valor ? 'Sim' : 'Não';
        if (is_array($valor)) return json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return (string) $valor;
    };
@endphp

@if(empty($dados))
    <span class="text-secondary">—</span>
@else
    <div class="history-data-list">
        @foreach($dados as $campo => $valor)
            @php($alteracao = is_array($valor) && array_key_exists('antes', $valor) && array_key_exists('depois', $valor))
            <div class="history-data-item">
                <span class="history-data-field">{{ ucfirst(str_replace('_', ' ', $campo)) }}</span>
                <span class="history-data-values">
                    @if($alteracao)
                        <span class="history-data-value old">{{ $formatarValor($valor['antes']) }}</span>
                        <i class="bi bi-arrow-right history-data-arrow" aria-hidden="true"></i>
                        <span class="history-data-value new">{{ $formatarValor($valor['depois']) }}</span>
                    @else
                        <span class="history-data-value new">{{ $formatarValor($valor) }}</span>
                    @endif
                </span>
            </div>
        @endforeach
    </div>
@endif
