<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>
@page { margin: 36px; } body { color:#22303f; font-family: DejaVu Sans, sans-serif; font-size:12px; } h1 { font-size:20px; margin:0 0 5px; } h2 { color:#5d6878; font-size:12px; margin:24px 0 8px; text-transform:uppercase; } .meta { color:#5d6878; margin-bottom:18px; } table { border-collapse:collapse; width:100%; } td { border:1px solid #dfe3e8; padding:8px; vertical-align:top; } .label { background:#f4f6f8; font-weight:bold; width:34%; } .rodape { color:#6c757d; font-size:10px; margin-top:24px; }
</style></head><body>
<h1>Comprovante de inscrição</h1><div class="meta"><strong>{{ $inscricao->atividade->nome }}</strong><br>Evento: {{ $inscricao->atividade->evento?->nome ?? 'Não informado' }}<br>Inscrição realizada em {{ $inscricao->created_at->format('d/m/Y \à\s H:i:s') }}</div>
<h2>Dados do participante</h2><table>@foreach($dadosParticipante as $dado)<tr><td class="label">{{ $dado['label'] }}</td><td>{{ $dado['valor'] }}</td></tr>@endforeach</table>
<h2>Respostas do formulário</h2><table>@forelse($respostas as $resposta)<tr><td class="label">{{ $resposta['label'] }}</td><td>{{ $resposta['valor'] }}</td></tr>@empty<tr><td>Este formulário não possui respostas adicionais.</td></tr>@endforelse</table>
<p class="rodape">Comprovante gerado em {{ now()->format('d/m/Y \à\s H:i:s') }}.</p></body></html>
