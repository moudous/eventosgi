<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Comprovante PIX #{{ $cobranca->id }}</title>
    <style>
        @page { margin: 34px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        h1 { margin: 0 0 5px; font-size: 24px; color: #166534; }
        .subtitulo { color: #6b7280; margin-bottom: 28px; }
        .valor { background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 18px; margin-bottom: 24px; }
        .valor span { display: block; color: #4b5563; font-size: 11px; }
        .valor strong { display: block; color: #166534; font-size: 28px; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 9px 7px; text-align: left; vertical-align: top; }
        th { width: 31%; color: #4b5563; }
        .codigo { font-family: DejaVu Sans Mono, monospace; word-break: break-all; }
        .rodape { margin-top: 28px; color: #6b7280; font-size: 10px; line-height: 1.5; }
    </style>
</head>
<body>
@php
    $inscricao = $cobranca->inscricao;
    $atividade = $inscricao?->atividade;
    $participante = $inscricao?->participante;
    $documento = \App\Services\RecebimentosPixService::formatarDocumento($cobranca->pagador_documento ?: $participante?->cpf);
@endphp
<h1>Comprovante de recebimento PIX</h1>
<div class="subtitulo">Pagamento confirmado pela API PIX do Sicoob</div>
<div class="valor"><span>Valor recebido</span><strong>R$ {{ number_format((float) $cobranca->valor, 2, ',', '.') }}</strong></div>
<table>
    <tr><th>Data e hora</th><td>{{ $cobranca->pago_em?->format('d/m/Y H:i:s') ?? 'Não informada' }}</td></tr>
    <tr><th>Pagador</th><td>{{ $cobranca->pagador_nome ?: $participante?->nome ?: 'Não informado' }}</td></tr>
    <tr><th>CPF/CNPJ</th><td>{{ $documento ?: 'Não informado' }}</td></tr>
    <tr><th>Evento</th><td>{{ $atividade?->evento?->nome ?? 'Não informado' }}</td></tr>
    <tr><th>Atividade</th><td>{{ $atividade?->nome ?? 'Não informada' }}</td></tr>
    <tr><th>E-mail da inscrição</th><td>{{ $inscricao?->participante_email ?? 'Não informado' }}</td></tr>
    <tr><th>TXID</th><td class="codigo">{{ $cobranca->txid }}</td></tr>
    <tr><th>EndToEndId</th><td class="codigo">{{ $cobranca->end_to_end_id ?: 'Não informado' }}</td></tr>
    <tr><th>Status</th><td>{{ $cobranca->status }}</td></tr>
</table>
<div class="rodape">Documento emitido em {{ now()->format('d/m/Y H:i:s') }} com base nos dados da cobrança confirmada pelo Sicoob. Identificador interno: {{ $cobranca->id }}.</div>
</body>
</html>
