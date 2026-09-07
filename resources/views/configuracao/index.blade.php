@extends('layouts.app')
@section('title', 'Configuração')
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><h1 class="page-title">Configuração</h1><p class="page-description mb-0">Redes liberadas dos limites de envio de código e o plugin que publica os formulários no WordPress.</p></div>
    <a href="{{ route('atividades.index') }}" class="btn btn-outline-secondary">Voltar para atividades</a>
</div>

@if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif

{{-- ------------------------------------------------------------------ Faixas de IP --}}
<div class="card content-card mb-4">
    <div class="card-header"><h2 class="h5 fw-bold mb-0"><i class="bi bi-hdd-network me-2"></i>Faixas de IP liberadas</h2></div>
    <div class="card-body">
        <p class="text-muted">
            Para conter robôs, o formulário público limita quantos <strong>e-mails diferentes</strong> podem pedir código a partir de uma mesma rede
            ({{ $limites['enderecos_por_faixa'] }} por hora) e quantos códigos ela pode disparar no total ({{ $limites['envios_por_faixa'] }} por hora).
            Numa rede da instituição isso atrapalha: uma turma inteira sai por um único IP público e trava antes de todo mundo se inscrever.
        </p>
        <p class="text-muted">Cadastre aqui as redes que são suas. Nelas, os dois limites acima deixam de valer.</p>

        <div class="alert alert-warning">
            <i class="bi bi-shield-check me-1"></i>
            <strong>O que continua valendo nas faixas liberadas:</strong> {{ $limites['enderecos_por_sessao'] }} e-mails diferentes por pessoa (por sessão do navegador),
            {{ $limites['por_email'] }} códigos por hora para o mesmo e-mail com 1 minuto entre reenvios,
            {{ $limites['por_atividade'] }} códigos por hora em cada atividade, além do campo isca e do selo do formulário.
            Libere apenas redes que você controla — em uma rede aberta a qualquer um, some a proteção que separa a turma do robô.
        </div>

        @if($permissoes->permite('configuracao.faixa.criar'))
        <form method="POST" action="{{ route('configuracao.faixas-ip.store') }}" class="row g-2 align-items-end mb-4">
            @csrf
            <div class="col-md-4">
                <label class="form-label" for="faixa">Endereço ou faixa</label>
                <input class="form-control @error('faixa') is-invalid @enderror" id="faixa" name="faixa" maxlength="60" required placeholder="200.130.15.0/24" value="{{ old('faixa') }}">
                <div class="form-text">Um endereço (<code>203.0.113.7</code>) ou uma faixa em CIDR (<code>200.130.15.0/24</code>, <code>2001:db8::/32</code>).</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="descricao">Descrição</label>
                <input class="form-control @error('descricao') is-invalid @enderror" id="descricao" name="descricao" maxlength="150" required placeholder="Wi-fi do campus central" value="{{ old('descricao') }}">
            </div>
            <div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Liberar</button></div>
        </form>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                @php $podeAgir = $permissoes->permite('configuracao.faixa.ativar_desativar') || $permissoes->permite('configuracao.faixa.excluir'); @endphp
                <thead><tr><th>Faixa</th><th>Descrição</th><th>Situação</th><th>Cadastrada por</th>@if($podeAgir)<th class="text-end">Ações</th>@endif</tr></thead>
                <tbody>
                @forelse($faixas as $faixa)
                    <tr>
                        <td><code>{{ $faixa->faixa }}</code></td>
                        <td>{{ $faixa->descricao }}</td>
                        <td>
                            @if($faixa->ativo)<span class="badge text-bg-success">Ativa</span>
                            @else<span class="badge text-bg-secondary">Inativa</span>@endif
                        </td>
                        <td class="text-muted small">{{ $faixa->criador?->nome ?? '—' }}<br>{{ $faixa->created_at?->format('d/m/Y H:i') }}</td>
                        @if($podeAgir)
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                @if($permissoes->permite('configuracao.faixa.ativar_desativar'))
                                <form method="POST" action="{{ route('configuracao.faixas-ip.toggle', $faixa) }}" class="m-0">
                                    @csrf @method('PATCH')<input type="hidden" name="ativo" value="{{ $faixa->ativo ? '0' : '1' }}">
                                    <button class="btn btn-sm {{ $faixa->ativo ? 'btn-outline-secondary' : 'btn-outline-success' }}" title="{{ $faixa->ativo ? 'Desativar' : 'Ativar' }}">
                                        <i class="bi {{ $faixa->ativo ? 'bi-pause-fill' : 'bi-play-fill' }}"></i>
                                    </button>
                                </form>
                                @endif
                                @if($permissoes->permite('configuracao.faixa.excluir'))
                                <form method="POST" action="{{ route('configuracao.faixas-ip.destroy', $faixa) }}" class="m-0" onsubmit="return confirm('Remover a faixa {{ $faixa->faixa }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Remover"><i class="bi bi-trash-fill"></i></button>
                                </form>
                                @endif
                            </div>
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $podeAgir ? 5 : 4 }}" class="text-center text-muted py-4">Nenhuma faixa liberada. Os limites por rede valem para todo mundo.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <p class="form-text mt-2 mb-0">Para descobrir o IP público de uma rede, abra um site como <em>meuip.com.br</em> a partir dela.</p>
    </div>
</div>

{{-- --------------------------------------------------------------- Plugin WordPress --}}
@if($permissoes->permite('configuracao.wordpress.visualizar'))
<div class="card content-card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h5 fw-bold mb-0"><i class="bi bi-wordpress me-2"></i>Plugin do WordPress</h2>
        <a href="{{ route('configuracao.plugin-wordpress') }}" class="btn btn-outline-dark">
            <i class="bi bi-download me-2"></i>Baixar plugin @if($versaoPlugin !== '')<span class="badge text-bg-light border ms-1">v{{ $versaoPlugin }}</span>@endif
        </a>
    </div>
    <div class="card-body">
        <p class="text-muted">O plugin exibe o formulário de inscrição de uma atividade em qualquer página ou post do seu site. As inscrições e os arquivos enviados são gravados aqui; nada fica guardado no WordPress.</p>

        <h3 class="h6 fw-bold mt-4">1. Instalar no WordPress</h3>
        <ol class="text-muted">
            <li>Baixe o arquivo <code>.zip</code> no botão acima.</li>
            <li>No painel do WordPress, vá em <strong>Plugins → Adicionar novo → Enviar plugin</strong>.</li>
            <li>Escolha o arquivo baixado e clique em <strong>Instalar agora</strong>.</li>
            <li>Clique em <strong>Ativar plugin</strong>.</li>
        </ol>

        <h3 class="h6 fw-bold mt-4">2. Conectar o plugin a este sistema</h3>
        <ol class="text-muted">
            <li>No WordPress, vá em <strong>Ajustes → EventosGI</strong>.</li>
            <li>Em <strong>URL do sistema</strong>, informe: <code>{{ rtrim(url('/'), '/') }}</code></li>
            <li>Em <strong>Token da API</strong>, cole o valor de <code>FORMULARIOS_API_TOKEN</code> do arquivo <code>.env</code> desta aplicação. <span class="fst-italic">Ele não é exibido aqui de propósito: é um segredo, e esta tela pode estar visível para outras pessoas.</span></li>
            <li>Em <strong>Cache do formulário</strong>, deixe alguns minutos para o site não consultar este sistema a cada visita. Alterações no formulário levam esse tempo para aparecer.</li>
            <li>Salve e use o botão <strong>Testar</strong> da própria tela de ajustes, informando o ID de uma atividade. Ele confirma a conexão e mostra quantos campos o formulário tem.</li>
        </ol>

        <h3 class="h6 fw-bold mt-4">3. Copiar o shortcode da atividade</h3>
        <ol class="text-muted">
            <li>Vá em <a href="{{ route('atividades.index') }}">Atividades</a>.</li>
            <li>Na linha da atividade desejada, clique no botão <span class="badge text-bg-light border"><i class="bi bi-wordpress"></i></span> da coluna <strong>Ações</strong> — o shortcode vai direto para a área de transferência.</li>
            <li>O texto copiado tem esta forma: <code>[eventosgi_formulario id="1"]</code>, em que o número é o ID da atividade.</li>
        </ol>

        <h3 class="h6 fw-bold mt-4">4. Usar em uma página ou post</h3>
        <ol class="text-muted">
            <li>No WordPress, crie ou edite a página (ou post) que vai receber o formulário.</li>
            <li><strong>Editor de blocos:</strong> adicione um bloco <strong>Shortcode</strong> e cole o texto dentro dele.<br>
                <strong>Editor clássico:</strong> cole o texto direto no corpo, na aba <em>Visual</em> ou <em>Texto</em>.</li>
            <li>Publique ou atualize a página. O formulário aparece no lugar do shortcode, com as fontes e cores do tema do site.</li>
        </ol>

        <div class="alert alert-light border mt-3 mb-0">
            <strong>Atributos opcionais do shortcode</strong>
            <ul class="mb-0 mt-2">
                <li><code>id</code> — ID da atividade. Obrigatório.</li>
                <li><code>titulo="nao"</code> — oculta o título e o subtítulo do formulário, útil quando a própria página já tem um.</li>
                <li><code>conteudo="nao"</code> — oculta o texto livre configurado na atividade.</li>
            </ul>
            <div class="mt-2">Exemplo: <code>[eventosgi_formulario id="1" titulo="nao"]</code></div>
        </div>
    </div>
</div>
@endif
@endsection
