<div style="font-family:Arial,Helvetica,sans-serif;color:#22303f;line-height:1.5">
    <h2 style="margin-bottom:4px">Comprovante de inscrição</h2>
    <p><strong>{{ $inscricao->atividade->nome }}</strong><br>Evento: {{ $inscricao->atividade->evento?->nome ?? 'Não informado' }}<br>Inscrição realizada em {{ $inscricao->created_at->format('d/m/Y \à\s H:i:s') }}</p>
    <h3>Dados do participante</h3>
    @foreach($dadosParticipante as $dado)<p><strong>{{ $dado['label'] }}:</strong> {{ $dado['valor'] }}</p>@endforeach
    <h3>Respostas</h3>
    @forelse($respostas as $resposta)<p><strong>{{ $resposta['label'] }}:</strong> {{ $resposta['valor'] }}</p>@empty<p>Este formulário não possui respostas adicionais.</p>@endforelse
    @if($qrPresenca)<div style="margin-top:24px;text-align:center;border-top:1px solid #dfe3e8;padding-top:16px"><h3>QR Code de presença</h3><p>Apresente este código no credenciamento da atividade.</p><img src="{{ $qrPresenca['imagem'] }}" width="220" height="220" alt="QR Code de presença"><div style="font-family:monospace;font-size:12px;word-break:break-all">{{ $qrPresenca['codigo'] }}</div></div>@endif
    <p style="margin-top:24px"><a href="{{ $urlPdf }}" style="background:#0d6efd;color:#fff;padding:10px 16px;text-decoration:none;border-radius:5px">Abrir comprovante em PDF</a></p>
</div>
