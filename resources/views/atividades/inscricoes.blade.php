@extends('layouts.app')
@section('title','Inscrições da atividade')
@section('content')
<div class="mb-4 d-flex flex-wrap gap-3 justify-content-between"><div><h1 class="page-title">Inscrições</h1><p class="page-description mb-0">{{ $atividade->nome }}</p></div><div class="d-flex gap-2 align-items-start"><div class="dropdown"><button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-download me-1"></i>Exportar respostas</button><ul class="dropdown-menu">@foreach(['ods' => 'Planilha (.ods)', 'csv' => 'CSV (.csv)', 'xls' => 'Excel (.xls)', 'xlsx' => 'Excel (.xlsx)'] as $formato => $rotulo)<li><a class="dropdown-item exportar-respostas" href="#" data-link="{{ route('atividades.inscricoes.exportar-link', [$atividade, $formato]) }}">{{ $rotulo }}</a></li>@endforeach</ul></div><a href="{{ route('atividades.index') }}" class="btn btn-outline-secondary">Voltar</a></div></div>
<form method="GET" action="{{ route('atividades.inscricoes',$atividade) }}" class="row g-2 justify-content-end mb-3"><div class="col-12 col-md-5 col-lg-4"><label class="visually-hidden" for="pesquisar">Pesquisar</label><div class="input-group"><input id="pesquisar" name="pesquisar" class="form-control" value="{{ $pesquisar }}" placeholder="Pesquisar inscrições"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-search me-1"></i>Pesquisar</button>@if($pesquisar!=='')<a class="btn btn-outline-secondary" href="{{ route('atividades.inscricoes',$atividade) }}?pesquisar=">Limpar</a>@endif</div></div></form>
@php
    $campos = collect($atividade->formulario['campos'] ?? [])->keyBy('nome');
    $participantes = App\Models\Participante::query()
        ->whereIn('id', $inscricoes->pluck('participante_id')->filter()->unique()->all())
        ->pluck('nome', 'id');
    $dispositivos = app(App\Services\DispositivoVisitanteService::class);
    $podeValidarPresenca = app(App\Services\GiPermissionService::class)->permite('atividades.validador_qr');
    // Quantidade de respostas exibidas na linha; o restante fica no modal.
    $visiveis = 4;
    $linhas = [];

    foreach ($inscricoes as $inscricao) {
        $resposta = $inscricao->resposta ?? [];
        // Segue a ordem dos campos do formulário para alinhar as colunas entre as linhas;
        // respostas de campos já removidos vão para o fim.
        $checkboxesSimples = $campos->filter(fn ($campo) =>
            ($campo['tipo'] ?? '') === 'checkbox' && empty($campo['opcoes'])
        )->keys()->all();
        $nomes = $campos->keys()->filter(fn ($nome) =>
            array_key_exists($nome, $resposta) || in_array($nome, $checkboxesSimples, true)
        )->all();
        $nomes = array_merge($nomes, array_values(array_diff(array_keys($resposta), $nomes)));

        $respostas = [];
        $anexos = [];

        if ($inscricao->sessao) $respostas[] = ['label' => 'Sessão', 'valores' => [$inscricao->sessao->rotuloPublico()]];

        foreach ($nomes as $nome) {
            $campo = $campos->get($nome, []);
            $label = ($campo['label'] ?? '') ?: str_replace('_', ' ', $nome);
            $valorResposta = $resposta[$nome] ?? null;
            $valores = is_array($valorResposta) ? Illuminate\Support\Arr::flatten($valorResposta) : [$valorResposta];
            $textos = [];
            $arquivos = [];
            $checkboxSimples = ($campo['tipo'] ?? '') === 'checkbox' && empty($campo['opcoes']);
            $textoOpcaoUnica = trim((string) ($campo['texto_opcao'] ?? '')) ?: 'Sim';

            $posicao = 0;
            foreach ($valores as $item) {
                // Stored uploads also remain accessible after their field is removed.
                if (is_string($item) && str_starts_with($item, 'inscricoes/') && !str_contains($item, '..')) {
                    // Disco privado: o acesso passa por rota assinada, válida por 2 horas.
                    $assinar = fn (string $modo) => Illuminate\Support\Facades\URL::temporarySignedRoute(
                        'inscricoes.arquivo', now()->addHours(2),
                        ['inscricao' => $inscricao->id, 'campo' => $nome, 'indice' => $posicao, 'modo' => $modo],
                    );
                    $arquivos[] = [
                        'url' => $assinar('visualizar'),
                        'download' => $assinar('baixar'),
                        'extensao' => strtoupper(pathinfo($item, PATHINFO_EXTENSION)) ?: 'ARQUIVO',
                    ];
                    $posicao++;
                } else {
                    $textos[] = $checkboxSimples
                        ? ((string) $item === '1' || $item === true ? $textoOpcaoUnica : 'Não')
                        : (is_bool($item) ? ($item ? 'Sim' : 'Não') : (string) $item);
                }
            }

            if ($arquivos !== []) $anexos[] = ['label' => $label, 'arquivos' => $arquivos];
            if ($textos !== [] || ($arquivos === [] && ($campo['tipo'] ?? '') !== 'file')) {
                $respostas[] = ['label' => $label, 'valores' => $textos];
            }
        }

        $dispositivo = $inscricao->dispositivo ?? [];

        $linhas[] = [
            'id' => $inscricao->id,
            'ip' => $inscricao->ip,
            'user_agent' => $inscricao->user_agent,
            'dispositivo_resumo' => $dispositivos->resumo($dispositivo),
            'dispositivo' => array_filter([
                'Navegador' => trim(($dispositivo['navegador'] ?? '').' '.($dispositivo['navegador_versao'] ?? '')),
                'Sistema operacional' => trim(($dispositivo['sistema'] ?? '').' '.($dispositivo['sistema_versao'] ?? '')),
                'Aparelho' => $dispositivo['plataforma'] ?? '',
                'Idioma' => $dispositivo['idioma'] ?? '',
                'Origem' => $dispositivo['origem'] ?? '',
                'Sessão' => $dispositivo['sessao'] ?? '',
            ], 'strlen'),
            'participante' => $participantes->get($inscricao->participante_id),
            'participante_id' => $inscricao->participante_id,
            'participante_email' => $inscricao->participante_email,
            'data' => $inscricao->created_at?->format('d/m/Y') ?? '—',
            'hora' => $inscricao->created_at?->format('H:i') ?? '',
            'presente' => (bool) $inscricao->presente,
            'data_presenca' => $inscricao->data_presenca?->format('d/m/Y H:i:s'),
            'presenca_url' => route('atividades.inscricoes.presenca', $inscricao),
            'respostas' => $respostas,
            'anexos' => $anexos,
        ];
    }
@endphp
<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 tabela-inscricoes">
                <thead><tr><th>ID</th><th>Data</th><th>Participante</th><th>Presença</th><th>Origem</th><th>Respostas</th><th>Anexos</th><th class="text-end">Ações</th></tr></thead>
                <tbody>
                @forelse($linhas as $linha)
                    <tr>
                        <td class="text-muted">#{{ $linha['id'] }}</td>
                        <td class="text-nowrap">{{ $linha['data'] }} <small class="text-muted">{{ $linha['hora'] }}</small></td>
                        <td>
                            @if($linha['participante_id'])
                                <div class="resposta-item" style="max-width: 220px">
                                    <span class="resposta-valor" title="{{ $linha['participante'] ?? 'Cadastro removido' }}">{{ $linha['participante'] ?? 'Cadastro removido' }}</span>
                                    <span class="resposta-rotulo text-lowercase" title="{{ $linha['participante_email'] }}">#{{ $linha['participante_id'] }} · {{ $linha['participante_email'] ?? '—' }}</span>
                                </div>
                            @else
                                <span class="text-muted small">Não identificado</span>
                            @endif
                        </td>
                        <td id="presenca-status-{{ $linha['id'] }}">@if($linha['presente'])<span class="badge text-bg-success" title="Registrada em {{ $linha['data_presenca'] }}">Presente</span>@else<span class="text-muted">—</span>@endif</td>
                        <td>
                            <div class="resposta-item" style="max-width: 190px">
                                <span class="resposta-valor" title="{{ $linha['ip'] ?? 'IP não registrado' }}">{{ $linha['ip'] ?? '—' }}</span>
                                <span class="resposta-rotulo rotulo-livre" title="{{ $linha['user_agent'] }}">{{ $linha['dispositivo_resumo'] }}</span>
                            </div>
                        </td>
                        <td>
                            @if($linha['respostas'])
                                <div class="respostas-inline">
                                    @foreach(array_slice($linha['respostas'], 0, $visiveis) as $item)
                                        @php
                                            $texto = implode(', ', array_filter($item['valores'], 'strlen'));
                                        @endphp
                                        <div class="resposta-item">
                                            <span class="resposta-rotulo">{{ $item['label'] }}</span>
                                            <span class="resposta-valor {{ $texto === '' ? 'text-muted fst-italic' : '' }}" title="{{ $texto }}">{{ $texto !== '' ? $texto : 'Não informado' }}</span>
                                        </div>
                                    @endforeach
                                    @if(count($linha['respostas']) > $visiveis)
                                        <span class="badge rounded-pill text-bg-light align-self-center">+{{ count($linha['respostas']) - $visiveis }}</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-muted small">Sem respostas textuais.</span>
                            @endif
                        </td>
                        <td>
                            @if($linha['anexos'])
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($linha['anexos'] as $anexo)
                                        @foreach($anexo['arquivos'] as $arquivo)
                                            <a class="badge text-bg-light text-decoration-none border" href="{{ $arquivo['url'] }}" target="_blank" rel="noopener noreferrer" title="{{ $anexo['label'] }} — abrir em nova aba">
                                                <i class="bi bi-paperclip" aria-hidden="true"></i> {{ $arquivo['extensao'] }}
                                            </a>
                                        @endforeach
                                    @endforeach
                                </div>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if($podeValidarPresenca)<button type="button" class="btn btn-sm {{ $linha['presente'] ? 'btn-outline-danger' : 'btn-outline-success' }} alternar-presenca" data-inscricao="{{ $linha['id'] }}" title="{{ $linha['presente'] ? 'Remover presença' : 'Marcar presença' }}" aria-label="{{ $linha['presente'] ? 'Remover presença' : 'Marcar presença' }}"><i class="bi {{ $linha['presente'] ? 'bi-person-x-fill' : 'bi-person-check-fill' }}" aria-hidden="true"></i></button>@endif
                            <button type="button" class="btn btn-sm btn-outline-dark ver-respostas" data-inscricao="{{ $linha['id'] }}" title="Visualizar respostas" aria-label="Visualizar respostas"><i class="bi bi-eye-fill" aria-hidden="true"></i></button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center py-4">Nenhuma inscrição encontrada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">{{ $inscricoes->links() }}</div>
</div>

<div class="modal fade" id="respostaModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><div><h2 class="modal-title fs-5">Inscrição <span id="respostaNumero"></span></h2><small class="text-muted d-block" id="respostaData"></small><small class="text-success fw-semibold d-block" id="respostaDataPresenca"></small></div><div class="d-flex align-items-center gap-2">@if($podeValidarPresenca)<button type="button" class="btn btn-sm btn-outline-success alternar-presenca" id="respostaPresenca" title="Marcar presença" aria-label="Marcar presença"><i class="bi bi-person-check-fill" aria-hidden="true"></i></button>@endif<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div></div>
    <div class="modal-body" id="respostaCorpo"></div>
</div></div></div>

<div class="modal fade" id="exportacaoCamposModal" tabindex="-1" aria-labelledby="exportacaoCamposTitulo" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><div><h2 class="modal-title fs-5" id="exportacaoCamposTitulo"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Campos da planilha</h2><small class="text-muted" id="exportacaoFormato"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
    <div class="modal-body">
        <p class="text-muted small">Escolha as colunas que serão incluídas no arquivo.</p>
        <div class="d-flex flex-wrap gap-2 mb-3"><button type="button" class="btn btn-sm btn-outline-secondary" id="exportacaoMarcarTodos">Marcar todos</button><button type="button" class="btn btn-sm btn-outline-secondary" id="exportacaoRestaurar">Restaurar seleção inicial</button></div>
        @foreach(collect($camposExportacao)->groupBy('grupo') as $grupo => $camposDoGrupo)
            <fieldset class="border rounded p-3 mb-3"><legend class="float-none w-auto px-1 h6 mb-1">{{ $grupo }}</legend>
                @foreach($camposDoGrupo as $campoExportacao)
                    <div class="form-check mb-1"><input class="form-check-input campo-exportacao" type="checkbox" id="exportar_{{ $loop->parent->index }}_{{ $loop->index }}" value="{{ $campoExportacao['chave'] }}" data-padrao="{{ $campoExportacao['marcado'] ? '1' : '0' }}" @checked($campoExportacao['marcado'])><label class="form-check-label" for="exportar_{{ $loop->parent->index }}_{{ $loop->index }}">{{ $campoExportacao['rotulo'] }}</label></div>
                @endforeach
            </fieldset>
        @endforeach
        <div class="alert alert-danger py-2 d-none" id="exportacaoErro"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="gerarExportacao"><i class="bi bi-download me-1"></i>Gerar planilha</button></div>
</div></div></div>
@endsection

@push('styles')
<style>
    .tabela-inscricoes tbody td { padding-top: 11px; padding-bottom: 11px; }
    .respostas-inline { display: flex; flex-wrap: wrap; gap: 4px 22px; }
    .resposta-item { min-width: 0; max-width: 190px; }
    .resposta-rotulo { display: block; font-size: 11px; font-weight: 700; line-height: 1.3; color: #748096; text-transform: uppercase; letter-spacing: .02em; }
    .resposta-valor { display: block; overflow: hidden; font-size: 13.5px; line-height: 1.35; text-overflow: ellipsis; white-space: nowrap; }
    /* Navegador e sistema operacional perdem legibilidade em caixa alta. */
    .rotulo-livre { overflow: hidden; text-overflow: ellipsis; text-transform: none; white-space: nowrap; letter-spacing: 0; }
    .resposta-detalhe dd:not(:last-child) { margin-bottom: 8px; }
    .resposta-detalhe { padding: 10px 14px; background: #fafbfc; border: 1px solid #eef1f5; border-radius: 10px; }
    .resposta-detalhe dt { font-size: 11px; font-weight: 700; color: #748096; text-transform: uppercase; letter-spacing: .02em; }
    .resposta-detalhe dd { margin: 2px 0 0; font-size: 14px; white-space: pre-wrap; overflow-wrap: anywhere; }
</style>
@endpush

@push('scripts')
<script>
const inscricoes = @json(collect($linhas)->keyBy('id'));

function atualizarBotaoPresenca(botao, inscricao) {
    if (!botao) return;
    botao.dataset.inscricao = inscricao.id;
    botao.classList.toggle('btn-outline-success', !inscricao.presente);
    botao.classList.toggle('btn-outline-danger', inscricao.presente);
    const titulo = inscricao.presente ? 'Remover presença' : 'Marcar presença';
    botao.title = titulo;
    botao.setAttribute('aria-label', titulo);
    botao.querySelector('i').className = 'bi ' + (inscricao.presente ? 'bi-person-x-fill' : 'bi-person-check-fill');
}

function atualizarPresencaNaTela(inscricao) {
    const status = document.getElementById('presenca-status-' + inscricao.id);
    if (status) {
        status.replaceChildren();
        if (inscricao.presente) {
            const badge = document.createElement('span');
            badge.className = 'badge text-bg-success';
            badge.textContent = 'Presente';
            badge.title = inscricao.data_presenca ? 'Registrada em ' + inscricao.data_presenca : 'Presença registrada';
            status.append(badge);
        } else {
            const vazio = document.createElement('span');
            vazio.className = 'text-muted';
            vazio.textContent = '—';
            status.append(vazio);
        }
    }
    document.querySelectorAll('.alternar-presenca[data-inscricao="' + inscricao.id + '"]').forEach(botao => atualizarBotaoPresenca(botao, inscricao));
    const botaoModal = document.getElementById('respostaPresenca');
    if (botaoModal?.dataset.inscricao === String(inscricao.id)) atualizarDataPresencaModal(inscricao);
}

function atualizarDataPresencaModal(inscricao) {
    const elemento = document.getElementById('respostaDataPresenca');
    elemento.textContent = inscricao.presente && inscricao.data_presenca
        ? 'Presença registrada em ' + inscricao.data_presenca
        : '';
}

document.addEventListener('click', async evento => {
    const botao = evento.target.closest('.alternar-presenca');
    if (!botao) return;
    const inscricao = inscricoes[botao.dataset.inscricao];
    if (!inscricao || botao.disabled) return;
    botao.disabled = true;
    try {
        const resposta = await fetch(inscricao.presenca_url, {method:'PATCH', credentials:'same-origin', headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':@json(csrf_token())}, body:JSON.stringify({presente:!inscricao.presente})});
        const dados = await resposta.json();
        if (!resposta.ok) throw new Error(dados.message || 'Não foi possível alterar a presença.');
        inscricao.presente = dados.presente;
        inscricao.data_presenca = dados.data_presenca;
        atualizarPresencaNaTela(inscricao);
    } catch (erro) {
        alert(erro.message || 'Não foi possível alterar a presença.');
    } finally {
        botao.disabled = false;
    }
});

document.querySelectorAll('.ver-respostas').forEach(botao => botao.addEventListener('click', () => {
    const inscricao = inscricoes[botao.dataset.inscricao];
    if (!inscricao) return;

    document.getElementById('respostaNumero').textContent = '#' + inscricao.id;
    document.getElementById('respostaData').textContent = 'Enviada em ' + inscricao.data + ' às ' + inscricao.hora
        + (inscricao.participante_id ? ' — ' + (inscricao.participante || 'cadastro removido') + ' (#' + inscricao.participante_id + ')' : '');
    atualizarBotaoPresenca(document.getElementById('respostaPresenca'), inscricao);
    atualizarDataPresencaModal(inscricao);

    const corpo = document.getElementById('respostaCorpo');
    corpo.replaceChildren();

    // Monta o conteúdo por nó para que as respostas dos participantes nunca sejam interpretadas como HTML.
    const grade = document.createElement('div');
    grade.className = 'row g-2';
    inscricao.respostas.forEach(item => {
        const coluna = document.createElement('div');
        coluna.className = 'col-md-6';
        const bloco = document.createElement('dl');
        bloco.className = 'resposta-detalhe mb-0 h-100';
        const rotulo = document.createElement('dt');
        rotulo.textContent = item.label;
        const valor = document.createElement('dd');
        const texto = item.valores.filter(v => v !== '').join(', ');
        valor.textContent = texto || 'Não informado';
        if (!texto) valor.className = 'text-muted fst-italic';
        bloco.append(rotulo, valor);
        coluna.append(bloco);
        grade.append(coluna);
    });

    if (!inscricao.respostas.length) {
        const vazio = document.createElement('p');
        vazio.className = 'text-muted mb-0';
        vazio.textContent = 'Sem respostas textuais.';
        corpo.append(vazio);
    } else {
        corpo.append(grade);
    }

    inscricao.anexos.forEach(anexo => {
        const titulo = document.createElement('div');
        titulo.className = 'fw-semibold small text-muted text-uppercase mt-3 mb-1';
        titulo.textContent = anexo.label;
        const lista = document.createElement('div');
        lista.className = 'd-flex flex-wrap gap-2';
        anexo.arquivos.forEach((arquivo, indice) => {
            const link = document.createElement('a');
            link.className = 'btn btn-sm btn-outline-secondary';
            link.href = arquivo.url;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = 'Anexo ' + (indice + 1) + ' (' + arquivo.extensao + ')';
            lista.append(link);
        });
        corpo.append(titulo, lista);
    });

    const origem = Object.entries(inscricao.dispositivo || {});
    if (inscricao.ip || origem.length || inscricao.user_agent) {
        const titulo = document.createElement('div');
        titulo.className = 'fw-semibold small text-muted text-uppercase mt-3 mb-1';
        titulo.textContent = 'Origem do envio';

        const lista = document.createElement('dl');
        lista.className = 'resposta-detalhe mb-0';
        const itens = inscricao.ip ? [['IP', inscricao.ip], ...origem] : origem;
        if (inscricao.user_agent) itens.push(['User-Agent', inscricao.user_agent]);
        itens.forEach(([rotulo, valor]) => {
            const dt = document.createElement('dt');
            dt.textContent = rotulo;
            const dd = document.createElement('dd');
            dd.textContent = valor;
            lista.append(dt, dd);
        });

        corpo.append(titulo, lista);
    }

    bootstrap.Modal.getOrCreateInstance(document.getElementById('respostaModal')).show();
}));

// A escolha do formato abre primeiro a seleção das colunas. Somente depois de confirmar
// o servidor prepara uma URL assinada para o arquivo solicitado.
const exportacaoModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('exportacaoCamposModal'));
const camposExportacao = [...document.querySelectorAll('.campo-exportacao')];
let exportacaoLink = null;
document.querySelectorAll('.exportar-respostas').forEach(link => link.addEventListener('click', event => {
    event.preventDefault();
    exportacaoLink = link;
    document.getElementById('exportacaoFormato').textContent = link.textContent.trim();
    document.getElementById('exportacaoErro').classList.add('d-none');
    exportacaoModal.show();
}));
document.getElementById('exportacaoMarcarTodos').addEventListener('click', () => camposExportacao.forEach(campo => campo.checked = true));
document.getElementById('exportacaoRestaurar').addEventListener('click', () => camposExportacao.forEach(campo => campo.checked = campo.dataset.padrao === '1'));
document.getElementById('gerarExportacao').addEventListener('click', async () => {
    const selecionados = camposExportacao.filter(campo => campo.checked).map(campo => campo.value);
    const erro = document.getElementById('exportacaoErro');
    if (!selecionados.length) {
        erro.textContent = 'Selecione pelo menos um campo para gerar a planilha.';
        erro.classList.remove('d-none');
        return;
    }
    const button = document.getElementById('gerarExportacao');
    if (button.disabled || !exportacaoLink) return;
    button.disabled = true;
    try {
        const parametros = new URLSearchParams();
        selecionados.forEach(campo => parametros.append('campos[]', campo));
        const response = await fetch(exportacaoLink.dataset.link + '?' + parametros.toString(), {credentials: 'same-origin', headers: {'Accept': 'application/json'}});
        if (!response.ok) throw new Error('A sessão expirou. Reabra a aplicação pelo sistema GI e tente novamente.');
        const {url} = await response.json();
        if (!url) throw new Error('Não foi possível preparar o arquivo. Tente novamente.');

        const oculto = document.createElement('iframe');
        oculto.hidden = true;
        oculto.src = url;
        document.body.appendChild(oculto);
        setTimeout(() => oculto.remove(), 120000);
        exportacaoModal.hide();
    } catch (error) {
        erro.textContent = error.message || 'Não foi possível baixar o arquivo. Tente novamente.';
        erro.classList.remove('d-none');
    } finally {
        button.disabled = false;
    }
});
</script>
@endpush
