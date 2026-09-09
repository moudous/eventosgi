@extends('layouts.app')
@section('title', 'Validador de presença')
@section('content')
<div class="container py-4" style="max-width:760px">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div><h1 class="page-title"><i class="bi bi-qr-code-scan me-2"></i>Validador de presença</h1><p class="page-description mb-0">Leia o QR Code apresentado pelo participante.</p></div>
        <a href="{{ route('atividades.index') }}" class="btn btn-outline-secondary">Voltar</a>
    </div>

    @if($errors->any())<div class="alert alert-danger"><i class="bi bi-x-circle me-1"></i>{{ $errors->first() }}</div>@endif
    @if(session('presenca_resultado'))
        @php $resultado = session('presenca_resultado'); @endphp
        <div class="alert {{ $resultado['status'] === 'validada' ? 'alert-success' : 'alert-warning' }}">
            <h2 class="h5"><i class="bi {{ $resultado['status'] === 'validada' ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }} me-1"></i>{{ $resultado['mensagem'] }}</h2>
            @include('atividades.partials.dados-validacao-presenca', ['resultado' => $resultado])
        </div>
    @endif

    @if(session('presenca_confirmacao'))
        @php $confirmacao = session('presenca_confirmacao'); @endphp
        <div class="card border-success mb-4">
            <div class="card-header bg-success-subtle"><h2 class="h5 mb-0"><i class="bi bi-person-check me-1"></i>Confirmar presença</h2></div>
            <div class="card-body">
                <p>{{ $confirmacao['mensagem'] }}</p>
                @include('atividades.partials.dados-validacao-presenca', ['resultado' => $confirmacao])
                <form method="POST" action="{{ route('atividades.validador-presenca.confirmar') }}" class="d-flex flex-wrap gap-2 mt-3">
                    @csrf
                    <input type="hidden" name="codigo_qr" value="{{ $confirmacao['codigo_qr'] }}">
                    <button class="btn btn-success" type="submit" name="decisao" value="validar"><i class="bi bi-check-circle me-1"></i>Validar presença</button>
                    <button class="btn btn-outline-danger" type="submit" name="decisao" value="cancelar"><i class="bi bi-x-circle me-1"></i>Não validar</button>
                </form>
            </div>
        </div>
    @endif

    <div class="card content-card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('atividades.validador-presenca.validar') }}" id="formValidarQr">
                @csrf
                <label class="form-label fw-semibold" for="codigo_qr">Código da inscrição</label>
                <div class="input-group input-group-lg">
                    <input class="form-control font-monospace @error('codigo_qr') is-invalid @enderror" id="codigo_qr" name="codigo_qr" maxlength="64" required autofocus autocomplete="off" value="{{ old('codigo_qr') }}" placeholder="EVGI-...">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Localizar</button>
                </div>
                <div class="form-text">Leitores USB normalmente digitam o código neste campo e confirmam automaticamente.</div>
            </form>

            <button class="btn btn-outline-primary mt-3" type="button" id="abrirCamera"><i class="bi bi-camera me-1"></i>Usar câmera</button>
            <div class="alert alert-danger mt-3 mb-0 d-none" id="cameraErro" role="alert"></div>
            <div class="mt-3 d-none" id="areaCamera">
                <video class="w-100 rounded border bg-dark" id="cameraQr" autoplay playsinline muted style="max-height:420px"></video>
                <div class="small text-muted mt-1" id="cameraStatus">Aponte a câmera para o QR Code.</div>
                <button class="btn btn-sm btn-outline-secondary mt-2" type="button" id="fecharCamera">Fechar câmera</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const botaoCamera=document.getElementById('abrirCamera'),areaCamera=document.getElementById('areaCamera'),video=document.getElementById('cameraQr'),statusCamera=document.getElementById('cameraStatus'),cameraErro=document.getElementById('cameraErro'),campoCodigo=document.getElementById('codigo_qr'),formularioQr=document.getElementById('formValidarQr');
let fluxoCamera=null,detectando=false,ultimoQuadro=0;
const pararCamera=()=>{detectando=false;if(fluxoCamera){fluxoCamera.getTracks().forEach(trilha=>trilha.stop());fluxoCamera=null}video.srcObject=null;areaCamera.classList.add('d-none');botaoCamera.classList.remove('d-none')};
async function detectar(tempo){if(!detectando)return;if(tempo-ultimoQuadro>250){ultimoQuadro=tempo;try{const codigos=await window.validadorQr.detect(video);const valor=codigos[0]?.rawValue?.trim();if(valor){campoCodigo.value=valor;pararCamera();formularioQr.requestSubmit();return}}catch(erro){statusCamera.textContent='Não foi possível ler esta imagem. Continue apontando para o código.'}}requestAnimationFrame(detectar)}
const exibirErroCamera=mensagem=>{cameraErro.textContent=mensagem;cameraErro.classList.remove('d-none')};
const mensagemErroCamera=erro=>{
    if(erro?.name==='NotAllowedError'||erro?.name==='SecurityError')return 'O acesso à câmera foi negado. No Chrome, toque no cadeado da barra de endereço, permita a câmera para este site e tente novamente.';
    if(erro?.name==='NotFoundError'||erro?.name==='DevicesNotFoundError')return 'Nenhuma câmera foi encontrada neste aparelho.';
    if(erro?.name==='NotReadableError'||erro?.name==='TrackStartError')return 'A câmera está sendo usada por outro aplicativo. Feche-o e tente novamente.';
    if(erro?.name==='OverconstrainedError')return 'A câmera traseira não pôde ser iniciada. Verifique as configurações de câmera do aparelho.';
    if(erro?.name==='AbortError')return 'O Chrome interrompeu a abertura da câmera. Feche outros aplicativos com câmera e tente novamente.';
    return 'Não foi possível iniciar a câmera neste aparelho. Atualize o Chrome e confira a permissão de câmera do site.';
};
botaoCamera.addEventListener('click',async()=>{
    cameraErro.classList.add('d-none');
    if(!window.isSecureContext||!navigator.mediaDevices?.getUserMedia){exibirErroCamera('A câmera só pode ser usada em uma conexão segura HTTPS. Abra esta página pelo endereço HTTPS oficial, e não por HTTP ou pelo endereço IP do servidor.');return}
    if(!('BarcodeDetector' in window)){exibirErroCamera('Esta versão do navegador não possui leitor de QR Code. Atualize o Chrome ou digite o código abaixo do QR Code.');return}
    try{
        if(BarcodeDetector.getSupportedFormats){const formatos=await BarcodeDetector.getSupportedFormats();if(!formatos.includes('qr_code'))throw new DOMException('QR Code não suportado','NotSupportedError')}
        window.validadorQr=new BarcodeDetector({formats:['qr_code']});
        // O vídeo precisa estar visível antes de play(); o Chrome móvel pode rejeitar
        // a reprodução quando o elemento ainda está dentro de display:none.
        areaCamera.classList.remove('d-none');botaoCamera.classList.add('d-none');statusCamera.textContent='Solicitando acesso à câmera…';
        fluxoCamera=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false});
        video.srcObject=fluxoCamera;
        await video.play();
        statusCamera.textContent='Aponte a câmera para o QR Code.';detectando=true;requestAnimationFrame(detectar);
    }catch(erro){pararCamera();exibirErroCamera(erro?.name==='NotSupportedError'?'Este navegador não oferece leitura de QR Code pela câmera. Atualize o Chrome ou digite o código manualmente.':mensagemErroCamera(erro))}
});
document.getElementById('fecharCamera').addEventListener('click',pararCamera);window.addEventListener('pagehide',pararCamera);
</script>
@endpush
