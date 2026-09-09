@php
    $quantidadeItens = count($dadosParticipante) + count($respostas);
    $quantidadeCaracteres = collect([...$dadosParticipante, ...$respostas])
        ->sum(fn ($item) => mb_strlen((string) ($item['label'] ?? '') . (string) ($item['valor'] ?? '')));
    $densidade = $quantidadeItens > 22 || $quantidadeCaracteres > 2600
        ? 'ultracompacto'
        : ($quantidadeItens > 13 || $quantidadeCaracteres > 1500 ? 'compacto' : 'normal');
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
    @page { size: A4 portrait; margin: 20px 24px; }
    body { margin:0; color:#22303f; font-family:DejaVu Sans,sans-serif; font-size:9px; line-height:1.18; }
    h1 { margin:0 0 3px; font-size:17px; }
    h2 { margin:9px 0 4px; color:#5d6878; font-size:9px; line-height:1; text-transform:uppercase; }
    .cabecalho, .itens { width:100%; border-collapse:collapse; table-layout:fixed; }
    .cabecalho > tbody > tr > td { padding:0; vertical-align:top; }
    .cabecalho .principal { padding-right:12px; }
    .meta { margin-bottom:7px; color:#5d6878; }
    .itens td { width:50%; padding:3px 5px; border:1px solid #dfe3e8; vertical-align:top; page-break-inside:avoid; overflow-wrap:anywhere; }
    .itens .vazio { border:0; }
    .label { display:block; margin-bottom:1px; color:#5d6878; font-size:7.5px; font-weight:bold; text-transform:uppercase; }
    .qr { width:174px; padding-left:8px !important; border-left:1px solid #e2e6ea; text-align:center; page-break-inside:avoid; }
    .qr h2 { margin-top:0; }
    .qr p { margin:3px 0; font-size:8px; }
    .qr img { width:158px; height:158px; }
    .codigo { font-family:DejaVu Sans Mono,monospace; font-size:6.5px; overflow-wrap:anywhere; }
    .rodape { margin:6px 0 0; color:#6c757d; font-size:7px; }
    body.compacto { font-size:8px; line-height:1.08; }
    body.compacto h2 { margin-top:6px; }
    body.compacto .itens td { padding:2px 4px; }
    body.compacto .label { font-size:6.8px; }
    body.ultracompacto { font-size:6.8px; line-height:1; }
    body.ultracompacto h1 { font-size:14px; }
    body.ultracompacto h2 { margin:4px 0 2px; font-size:7px; }
    body.ultracompacto .meta { margin-bottom:3px; }
    body.ultracompacto .itens td { padding:1.5px 3px; }
    body.ultracompacto .label { font-size:5.8px; }
</style>
</head>
<body class="{{ $densidade }}">
<table class="cabecalho"><tr>
    <td class="principal">
        <h1>Comprovante de inscrição</h1>
        <div class="meta"><strong>{{ $inscricao->atividade->nome }}</strong><br>Evento: {{ $inscricao->atividade->evento?->nome ?? 'Não informado' }}<br>Inscrição realizada em {{ $inscricao->created_at->format('d/m/Y \à\s H:i:s') }}</div>
        <h2>Dados do participante</h2>
        <table class="itens">
            @foreach(array_chunk($dadosParticipante, 2) as $linha)
                <tr>@foreach($linha as $dado)<td><span class="label">{{ $dado['label'] }}</span>{{ $dado['valor'] }}</td>@endforeach @if(count($linha) === 1)<td class="vazio"></td>@endif</tr>
            @endforeach
        </table>
    </td>
    @if($qrPresenca)
        <td class="qr"><h2>QR Code de presença</h2><p>Apresente este código no credenciamento.</p><img src="{{ $qrPresenca['imagem'] }}" alt="QR Code de presença"><div class="codigo">{{ $qrPresenca['codigo'] }}</div></td>
    @endif
</tr></table>
<h2>Respostas do formulário</h2>
<table class="itens">
    @forelse(array_chunk($respostas, 2) as $linha)
        <tr>@foreach($linha as $resposta)<td><span class="label">{{ $resposta['label'] }}</span>{{ $resposta['valor'] }}</td>@endforeach @if(count($linha) === 1)<td class="vazio"></td>@endif</tr>
    @empty
        <tr><td>Este formulário não possui respostas adicionais.</td><td class="vazio"></td></tr>
    @endforelse
</table>
<p class="rodape">Comprovante gerado em {{ now()->format('d/m/Y \à\s H:i:s') }}.</p>
</body>
</html>
