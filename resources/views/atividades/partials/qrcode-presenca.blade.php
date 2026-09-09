@if($qrPresenca)
<div class="qr-presenca text-center mt-4 pt-3 border-top">
    <h3 class="h6 text-uppercase text-muted">QR Code de presença</h3>
    <p class="small mb-2">Apresente este código no credenciamento da atividade.</p>
    <img src="{{ $qrPresenca['imagem'] }}" width="220" height="220" alt="QR Code individual para registro de presença">
    <div class="font-monospace small text-break mt-1">{{ $qrPresenca['codigo'] }}</div>
</div>
@endif
