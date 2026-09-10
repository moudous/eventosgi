@extends('layouts.app')
@section('title', 'Dashboard')
@push('styles')
<style>
.dashboard-indicador{border:0;box-shadow:0 6px 22px rgba(31,45,61,.08)}
.dashboard-icone{width:48px;height:48px;display:grid;place-items:center;border-radius:14px;font-size:1.35rem}
.dashboard-grafico{position:relative;min-height:320px;max-height:430px}
.dashboard-legenda-cor{width:12px;height:12px;border-radius:3px;display:inline-block;flex:0 0 auto}
</style>
@endpush
@section('content')
<div class="mb-4">
    <h1 class="page-title">Dashboard</h1>
    <p class="page-description mb-0">Visão geral dos eventos, inscrições e trabalhos submetidos.</p>
</div>

<div class="row g-4 mb-4">
    @foreach($indicadores as $indicador)
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card dashboard-indicador h-100"><div class="card-body p-4 d-flex align-items-center gap-3">
            <div class="dashboard-icone text-bg-{{ $indicador['cor'] }} bg-opacity-{{ $indicador['cor'] === 'warning' ? '25' : '10' }} text-{{ $indicador['cor'] }}"><i class="bi {{ $indicador['icone'] }}"></i></div>
            <div><div class="text-muted small">{{ $indicador['rotulo'] }}</div><div class="fs-2 fw-bold lh-sm">{{ number_format($indicador['valor'], 0, ',', '.') }}</div></div>
        </div></div>
    </div>
    @endforeach
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-lg-6">
        <div class="card content-card h-100">
            <div class="card-header"><h2 class="h5 fw-bold mb-0">Inscritos por opção</h2></div>
            <div class="card-body p-4">
                <form method="GET" action="{{ route('dashboard') }}" class="row g-3 mb-4">
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="dashboard_evento">Evento</label>
                        <select class="form-select" id="dashboard_evento" name="evento" onchange="this.form.submit()">
                            @forelse($eventos as $evento)<option value="{{ $evento->id }}" @selected($eventoId === $evento->id)>{{ $evento->nome }}</option>@empty<option value="">Nenhum evento</option>@endforelse
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold" for="dashboard_atividade">Atividade</label>
                        <select class="form-select" id="dashboard_atividade" name="atividade" onchange="this.form.submit()" @disabled($atividades->isEmpty())>
                            @forelse($atividades as $atividade)<option value="{{ $atividade->id }}" @selected($atividadeId === $atividade->id)>{{ $atividade->nome }}</option>@empty<option value="">Nenhuma atividade</option>@endforelse
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold" for="dashboard_campo">Campo combo</label>
                        <select class="form-select" id="dashboard_campo" name="campo" onchange="this.form.submit()" @disabled($campos === [])>
                            @forelse($campos as $campo)<option value="{{ $campo['nome'] }}" @selected($campoNome === $campo['nome'])>{{ $campo['label'] }}</option>@empty<option value="">Nenhum campo combo</option>@endforelse
                        </select>
                    </div>
                </form>

                @if($grafico['total'] > 0)
                    <div class="dashboard-grafico"><canvas id="graficoInscritos" aria-label="Percentual de inscritos por opção" role="img"></canvas></div>
                    <div class="small text-muted text-center mt-2">Base: {{ $grafico['total'] }} inscrição(ões)</div>
                    <div class="row g-2 mt-2" id="legendaGrafico"></div>
                @elseif($atividadeId && $campoNome)
                    <div class="alert alert-light border mb-0">Ainda não existem inscrições para esta seleção.</div>
                @else
                    <div class="alert alert-light border mb-0">Selecione uma atividade que possua um campo do tipo combo.</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card content-card">
    <div class="card-header"><h2 class="h5 fw-bold mb-0">Últimas 10 inscrições</h2></div>
    <div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Participante</th><th>Evento</th><th>Atividade</th><th>Data e hora</th></tr></thead>
            <tbody>
                @forelse($ultimasInscricoes as $inscricao)
                <tr>
                    <td><strong class="d-block">{{ $inscricao->participante?->nome ?? 'Participante não identificado' }}</strong><span class="small text-muted">{{ $inscricao->participante?->email ?? $inscricao->participante_email ?? 'E-mail não informado' }}</span></td>
                    <td>{{ $inscricao->atividade?->evento?->nome ?? '—' }}</td>
                    <td>{{ $inscricao->atividade?->nome ?? '—' }}</td>
                    <td class="text-nowrap">{{ $inscricao->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
                @empty<tr><td colspan="4" class="text-center text-muted py-5">Nenhuma inscrição encontrada.</td></tr>@endforelse
            </tbody>
        </table>
    </div></div>
</div>
@endsection

@if($grafico['total'] > 0)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const itens={{ Illuminate\Support\Js::from($grafico['itens']) }};
    const cores=['#0d6efd','#198754','#ffc107','#dc3545','#6f42c1','#0dcaf0','#fd7e14','#20c997','#6610f2','#d63384','#6c757d','#adb5bd'];
    const contexto=document.getElementById('graficoInscritos');
    new Chart(contexto,{type:'pie',data:{labels:itens.map(item=>`${item.rotulo} — ${item.percentual}%`),datasets:[{data:itens.map(item=>item.quantidade),backgroundColor:itens.map((_,indice)=>cores[indice%cores.length]),borderColor:'#fff',borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{padding:18,usePointStyle:true}},tooltip:{callbacks:{label:context=>`${itens[context.dataIndex].rotulo}: ${context.raw} inscrito(s) (${itens[context.dataIndex].percentual}%)`}}}}});
    const legenda=document.getElementById('legendaGrafico');
    itens.forEach((item,indice)=>{
        const coluna=document.createElement('div');
        coluna.className='col-12 col-md-6';
        const linha=document.createElement('div');
        linha.className='d-flex align-items-center gap-2';
        const cor=document.createElement('span');
        cor.className='dashboard-legenda-cor';
        cor.style.backgroundColor=cores[indice%cores.length];
        const texto=document.createElement('span');
        texto.textContent=`${item.rotulo}: ${item.percentual}% (${item.quantidade})`;
        linha.append(cor,texto);
        coluna.appendChild(linha);
        legenda.appendChild(coluna);
    });
});
</script>
@endpush
@endif
