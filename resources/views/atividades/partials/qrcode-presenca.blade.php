@if($qrPresenca || $presenca)
<div class="qr-presenca mt-4 pt-3 border-top">
    <div class="row g-4 align-items-center justify-content-center">
        @if($qrPresenca)
            <div class="col-12 {{ $presenca ? 'col-md-6' : '' }} text-center">
                <h3 class="h6 text-uppercase text-muted">QR Code de presença</h3>
                <p class="small mb-2">Apresente este código no credenciamento da atividade.</p>
                <img src="{{ $qrPresenca['imagem'] }}" width="220" height="220" alt="QR Code individual para registro de presença">
                <div class="font-monospace small text-break mt-1">{{ $qrPresenca['codigo'] }}</div>
            </div>
        @endif
        @if($presenca)
            <div class="col-12 {{ $qrPresenca ? 'col-md-6' : '' }}">
                <div class="alert alert-success mb-0">
                    <h3 class="h6 fw-bold"><i class="bi bi-person-check-fill me-1"></i>Presença validada</h3>
                    <dl class="row small mb-0">
                        <dt class="col-sm-5">Data e hora</dt><dd class="col-sm-7">{{ $presenca['data'] }}</dd>
                        <dt class="col-sm-5">Validada por</dt><dd class="col-sm-7 mb-0">{{ $presenca['usuario'] }}</dd>
                    </dl>
                </div>
            </div>
        @endif
    </div>
</div>
@endif
