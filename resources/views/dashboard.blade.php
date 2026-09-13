@extends('layouts.app')
@section('title', 'Dashboard')
@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
<style>
.dashboard-card-filtro{scroll-margin-top:90px}
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
    <div class="col-12 col-sm-6 col-lg-2">
        <div class="card dashboard-indicador h-100"><div class="card-body p-3 d-flex flex-column align-items-start gap-3">
            <div class="dashboard-icone text-bg-{{ $indicador['cor'] }} bg-opacity-{{ $indicador['cor'] === 'warning' ? '25' : '10' }} text-{{ $indicador['cor'] }}"><i class="bi {{ $indicador['icone'] }}"></i></div>
            <div><div class="text-muted small">{{ $indicador['rotulo'] }}</div><div class="fs-2 fw-bold lh-sm">{{ number_format($indicador['valor'], 0, ',', '.') }}</div></div>
        </div></div>
    </div>
    @endforeach
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card content-card" id="inscricoes-categoria">
            <div class="card-header d-flex justify-content-between align-items-center gap-3"><h2 class="h5 fw-bold mb-0">Inscrições por categoria</h2>@include('partials.dashboard-exportar', ['titulo' => 'Inscrições por categoria', 'card' => 'inscricoes-categoria', 'filtros' => $filtrosExportacao['inscricoes-categoria']])</div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-12 col-lg-9">
                        <div class="row g-3">
                            @forelse($contagensCategorias->where('inscricoes', '>', 0) as $categoria)
                            <div class="col-12 col-sm-6 col-xl-4">
                                <div class="d-flex align-items-center gap-3 border rounded p-3 h-100">
                                    <div class="dashboard-icone bg-primary bg-opacity-10 text-primary flex-shrink-0"><i class="bi {{ $categoria['icone'] }}" aria-hidden="true"></i></div>
                                    <div>
                                        <div class="text-muted small">{{ $categoria['nome'] }}</div>
                                        <div class="fs-3 fw-bold lh-sm">{{ number_format($categoria['inscricoes'], 0, ',', '.') }}</div>
                                        <div class="small text-muted">inscrições</div>
                                    </div>
                                </div>
                            </div>
                            @empty
                            <div class="col-12 text-muted">Nenhuma inscrição por categoria registrada.</div>
                            @endforelse
                        </div>
                    </div>
                    <aside class="col-12 col-lg-3" aria-labelledby="titulo-atividades-categoria">
                        <div class="bg-light rounded p-3 h-100">
                            <h3 class="h6 fw-bold mb-3" id="titulo-atividades-categoria">Atividades por categoria</h3>
                            <dl class="mb-0">
                                @forelse($contagensCategorias->where('atividades', '>', 0) as $categoria)
                                <div class="d-flex align-items-baseline justify-content-between gap-2 py-2 border-bottom">
                                    <dt class="small fw-normal"><i class="bi {{ $categoria['icone'] }} text-muted me-1" aria-hidden="true"></i>{{ $categoria['nome'] }}</dt>
                                    <dd class="fw-bold mb-0 flex-shrink-0">{{ number_format($categoria['atividades'], 0, ',', '.') }}</dd>
                                </div>
                                @empty
                                <div class="small text-muted">Nenhuma atividade por categoria registrada.</div>
                                @endforelse
                            </dl>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-lg-6">
        <div class="card content-card h-100 dashboard-card-filtro" id="inscritos-opcao">
            <div class="card-header d-flex justify-content-between align-items-center gap-3"><h2 class="h5 fw-bold mb-0">Inscritos por opção</h2>@include('partials.dashboard-exportar', ['titulo' => 'Inscritos por opção', 'card' => 'inscritos-opcao', 'filtros' => $filtrosExportacao['inscritos-opcao']])</div>
            <div class="card-body p-4">
                <form method="GET" action="{{ route('dashboard') }}#inscritos-opcao" class="row g-3 mb-4">
                    <input type="hidden" name="dispositivo_evento" value="{{ $dispositivoEventoId }}">
                    <input type="hidden" name="dispositivo_atividade" value="{{ $dispositivoAtividadeId }}">
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="dashboard_evento">Evento</label>
                        <select class="form-select dashboard-evento-pesquisa" id="dashboard_evento" name="evento" onchange="this.form.submit()">
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
    <div class="col-12 col-lg-6">
        <div class="card content-card h-100 dashboard-card-filtro" id="evolucao-inscricoes">
            <div class="card-header d-flex justify-content-between align-items-center gap-3"><h2 class="h5 fw-bold mb-0">Evolução do Nº de inscrições</h2>@include('partials.dashboard-exportar', ['titulo' => 'Evolução do Nº de inscrições', 'card' => 'evolucao-inscricoes', 'filtros' => $filtrosExportacao['evolucao-inscricoes']])</div>
            <div class="card-body p-4">
                <form method="GET" action="{{ route('dashboard') }}#evolucao-inscricoes" class="row g-3 mb-4">
                    <input type="hidden" name="dispositivo_evento" value="{{ $dispositivoEventoId }}">
                    <input type="hidden" name="dispositivo_atividade" value="{{ $dispositivoAtividadeId }}">
                    <input type="hidden" name="campo" value="{{ $campoNome }}">
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="evolucao_evento">Evento</label>
                        <select class="form-select dashboard-evento-pesquisa" id="evolucao_evento" name="evento" onchange="this.form.submit()">
                            @forelse($eventos as $evento)<option value="{{ $evento->id }}" @selected($eventoId === $evento->id)>{{ $evento->nome }}</option>@empty<option value="">Nenhum evento</option>@endforelse
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="evolucao_atividade">Atividade</label>
                        <select class="form-select" id="evolucao_atividade" name="atividade" onchange="this.form.submit()" @disabled($atividades->isEmpty())>
                            @forelse($atividades as $atividade)<option value="{{ $atividade->id }}" @selected($atividadeId === $atividade->id)>{{ $atividade->nome }}</option>@empty<option value="">Nenhuma atividade</option>@endforelse
                        </select>
                    </div>
                </form>
                @if($evolucao && ! $evolucao['erro'])
                    <p class="small text-muted">Inscrições em cada intervalo de {{ $evolucao['intervalo'] }}. A escala se ajusta à duração e ao volume de inscrições.</p>
                    <div class="dashboard-grafico"><canvas id="graficoEvolucao" role="img" aria-label="Inscrições por intervalo de tempo"></canvas></div>
                    <p class="small text-muted mt-3 mb-1">{{ $evolucao['inicio'] }} até {{ $evolucao['fim'] }} · Horário de Brasília</p>
                    <p class="small mb-1">{{ $evolucao['total'] }} inscrições no período · Pico: {{ $evolucao['pico'] }} por intervalo.</p>
                    <p class="small text-muted mb-0">Intervalos futuros ficam sem dados. Intervalos parciais são indicados ao passar o cursor. Os filtros selecionam a atividade nos dois gráficos.</p>
                    @if($evolucao['total'] === 0)<p class="small text-muted mt-2 mb-0">Ainda não há inscrições no período exibido.</p>@endif
                    @if($evolucao['fora'])<p class="small text-muted mt-2 mb-0">{{ $evolucao['fora'] }} inscrição(ões) fora do período observado não entram neste gráfico.</p>@endif
                @else
                    <div class="alert alert-light border">{{ $evolucao['erro'] ?? 'Selecione um evento com atividades para visualizar o gráfico.' }}</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card content-card h-100 dashboard-card-filtro" id="inscricoes-dispositivo">
            <div class="card-header d-flex justify-content-between align-items-center gap-3"><h2 class="h5 fw-bold mb-0">Inscrições por dispositivo</h2>@include('partials.dashboard-exportar', ['titulo' => 'Inscrições por dispositivo', 'card' => 'inscricoes-dispositivo', 'filtros' => $filtrosExportacao['inscricoes-dispositivo']])</div>
            <div class="card-body p-4">
                <form method="GET" action="{{ route('dashboard') }}#inscricoes-dispositivo" class="row g-3 mb-4">
                    <input type="hidden" name="evento" value="{{ $eventoId }}">
                    <input type="hidden" name="atividade" value="{{ $atividadeId }}">
                    <input type="hidden" name="campo" value="{{ $campoNome }}">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold" for="dispositivo_evento">Evento</label>
                        <select class="form-select dashboard-evento-pesquisa" id="dispositivo_evento" name="dispositivo_evento" onchange="this.form.elements.dispositivo_atividade.value='0';this.form.submit()">
                            <option value="0" @selected($dispositivoEventoId === 0)>Todos os eventos</option>
                            @foreach($eventos as $evento)<option value="{{ $evento->id }}" @selected($dispositivoEventoId === $evento->id)>{{ $evento->nome }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold" for="dispositivo_atividade">Atividade</label>
                        <select class="form-select" id="dispositivo_atividade" name="dispositivo_atividade" onchange="this.form.submit()">
                            <option value="0" @selected($dispositivoAtividadeId === 0)>Todas as atividades</option>
                            @foreach($dispositivoAtividades as $atividade)<option value="{{ $atividade->id }}" @selected($dispositivoAtividadeId === $atividade->id)>{{ $atividade->nome }}{{ $dispositivoEventoId === 0 ? ' — '.$atividade->evento?->nome : '' }}</option>@endforeach
                        </select>
                    </div>
                </form>
                @if($totalDispositivo)
                    <p class="small text-muted mb-3">Base: {{ $totalDispositivo }} inscrições. Cada inscrição é contada uma vez em cada agrupamento.</p>
                    <div class="row g-4">
                        @foreach($dimensoesDispositivo as $dimensao => $titulo)
                        @php($dadosDispositivo = $graficosDispositivo[$dimensao])
                        <section class="col-12 col-xl-6" aria-labelledby="titulo-dispositivo-{{ $dimensao }}">
                            <div class="border rounded p-3 h-100">
                                <h3 class="h6 fw-bold" id="titulo-dispositivo-{{ $dimensao }}">{{ $titulo }}</h3>
                                <div style="max-height:360px;overflow-y:auto">
                                    <div style="position:relative;height:{{ max(150, count($dadosDispositivo['itens']) * 36 + 55) }}px">
                                        <canvas id="grafico-dispositivo-{{ $dimensao }}" role="img" aria-label="Inscrições por {{ $titulo }}"></canvas>
                                    </div>
                                </div>
                                <div class="table-responsive mt-3" style="max-height:240px;overflow:auto">
                                    <table class="table table-sm mb-0">
                                        <thead><tr><th>{{ $titulo }}</th><th class="text-end">Inscrições</th><th class="text-end">%</th></tr></thead>
                                        <tbody>@foreach($dadosDispositivo['itens'] as $item)<tr><td class="text-break">{{ $item['rotulo'] }}</td><td class="text-end">{{ $item['quantidade'] }}</td><td class="text-end">{{ number_format($item['percentual'], 1, ',', '.') }}%</td></tr>@endforeach</tbody>
                                    </table>
                                </div>
                            </div>
                        </section>
                        @endforeach
                    </div>
                    <p class="small text-muted mt-3 mb-0">Dados ausentes aparecem como “Não informado”, inclusive inscrições de teste sem dispositivo registrado.</p>
                @else
                    <div class="alert alert-light border mb-0">Nenhuma inscrição encontrada para esta seleção.</div>
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

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ asset('dashboard-filtros.js') }}?v=1"></script>
<script src="{{ asset('vendor/dashboard-export/chart.umd.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
    @if($totalDispositivo)
    const graficosDispositivo={{ Illuminate\Support\Js::from($graficosDispositivo) }};
    Object.entries(graficosDispositivo).forEach(([dimensao, dados]) => {
        const itens=dados.itens;
        new Chart(document.getElementById(`grafico-dispositivo-${dimensao}`), {
            type:'bar',
            data:{labels:itens.map(item=>item.rotulo),datasets:[{data:itens.map(item=>item.quantidade),backgroundColor:'#2563eb',borderRadius:3}]},
            options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:context=>`${context.raw} inscrições (${itens[context.dataIndex].percentual}%)`}}},scales:{x:{beginAtZero:true,title:{display:true,text:'Inscrições'},ticks:{precision:0}},y:{ticks:{autoSkip:false,callback:function(value){const rotulo=this.getLabelForValue(value);return rotulo.length>32 ? rotulo.slice(0,29)+'…' : rotulo;}}}}}
        });
    });
    @endif
    @if($grafico['total'] > 0)
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
    @endif
    @if($evolucao && ! $evolucao['erro'])
    const evolucao={{ Illuminate\Support\Js::from($evolucao) }};
    new Chart(document.getElementById('graficoEvolucao'), {
        type:'bar',
        data:{labels:evolucao.itens.map(item=>[item.rotulo,item.dia_semana]),datasets:[{label:`Inscrições / ${evolucao.intervalo}`,data:evolucao.itens.map(item=>item.quantidade),backgroundColor:evolucao.itens.map(item=>item.parcial ? '#93c5fd' : '#2563eb'),borderRadius:3}]},
        options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{
            title:context=>{const item=evolucao.itens[context[0].dataIndex];return `${item.inicio} até ${item.fim}`;},
            label:context=>`${context.raw} inscrição(ões)${evolucao.itens[context.dataIndex].parcial ? ' · intervalo parcial' : ''}`
        }}},scales:{x:{title:{display:true,text:'Período de inscrições'},ticks:{maxTicksLimit:8,maxRotation:45}},y:{beginAtZero:true,title:{display:true,text:`Inscrições / ${evolucao.intervalo}`},ticks:{precision:0}}}}
    });
    @endif
});
</script>
@endpush
