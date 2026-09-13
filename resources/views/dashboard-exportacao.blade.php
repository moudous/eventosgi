<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $relatorio['titulo'] }} — Eventos GI</title>
    <style>
        @page { margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px; background: #f4f6f9; color: #1f2d3d; font: 14px/1.45 DejaVu Sans, Arial, sans-serif; }
        main { max-width: 1400px; margin: auto; }
        header { margin-bottom: 22px; }
        h1 { margin: 0 0 5px; font-size: 26px; }
        h2 { margin: 0; font-size: 18px; }
        .meta { color: #586174; font-size: 12px; }
        .filtros { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
        .filtro { padding: 7px 10px; border: 1px solid #dce1e7; border-radius: 6px; background: #fff; }
        .secao { margin: 0 0 22px; border: 1px solid #dce1e7; border-radius: 10px; background: #fff; overflow: hidden; page-break-inside: avoid; }
        .secao-titulo { padding: 14px 18px; border-bottom: 1px solid #dce1e7; background: #f8fafc; }
        .conteudo { padding: 16px 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border-bottom: 1px solid #e8ebef; text-align: left; vertical-align: middle; overflow-wrap: anywhere; }
        th { color: #586174; font-size: 12px; }
        th:not(:first-child), td:not(:first-child) { text-align: right; width: 16%; }
        .barra-fundo { width: 100%; height: 10px; margin-top: 5px; border-radius: 8px; background: #dbeafe; overflow: hidden; }
        .barra { height: 100%; border-radius: 8px; background: #2563eb; }
        .vazio { padding: 18px; color: #586174; text-align: center; }
        @media print { body { padding: 0; background: #fff; } .secao { page-break-inside: auto; } tr { page-break-inside: avoid; } }
    </style>
</head>
<body>
<main>
    <header>
        <h1>{{ $relatorio['titulo'] }}</h1>
        <div class="meta">Eventos GI · gerado em {{ now()->format('d/m/Y H:i') }}</div>
        @if($relatorio['filtros'])<div class="filtros">@foreach($relatorio['filtros'] as $filtro)<span class="filtro">{{ $filtro }}</span>@endforeach</div>@endif
    </header>

    @foreach($relatorio['secoes'] as $secao)
        <section class="secao">
            <div class="secao-titulo"><h2>{{ $secao['titulo'] }}</h2></div>
            @if($secao['linhas'])
                @php($maior = max(1, ...array_column($secao['grafico'], 'valor')))
                <div class="conteudo"><table>
                    <thead><tr>@foreach($secao['colunas'] as $coluna)<th>{{ $coluna }}</th>@endforeach</tr></thead>
                    <tbody>@foreach($secao['linhas'] as $indice => $linha)<tr>
                        @foreach($linha as $colunaIndice => $valor)
                            <td>{{ $valor }}@if($colunaIndice === 0)<div class="barra-fundo"><div class="barra" style="width: {{ min(100, 100 * ($secao['grafico'][$indice]['valor'] ?? 0) / $maior) }}%"></div></div>@endif</td>
                        @endforeach
                    </tr>@endforeach</tbody>
                </table></div>
            @else
                <div class="vazio">Nenhum dado encontrado para os filtros selecionados.</div>
            @endif
        </section>
    @endforeach
</main>
</body>
</html>
