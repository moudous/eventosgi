@extends('layouts.app')
@section('title','Inscrições da atividade')
@section('content')
<div class="mb-4 d-flex flex-wrap gap-3 justify-content-between"><div><h1 class="page-title">Inscrições</h1><p class="page-description mb-0">{{ $atividade->nome }}</p></div><div class="d-flex gap-2 align-items-start"><button class="btn btn-outline-success" type="button" data-bs-toggle="modal" data-bs-target="#autoPresencaModal"><i class="bi bi-person-check-fill me-1"></i>Auto registro de presença</button><div class="dropdown"><button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-download me-1"></i>Exportar respostas</button><ul class="dropdown-menu">@foreach(['ods' => 'Planilha (.ods)', 'csv' => 'CSV (.csv)', 'xls' => 'Excel (.xls)', 'xlsx' => 'Excel (.xlsx)'] as $formato => $rotulo)<li><a class="dropdown-item exportar-respostas" href="#" data-link="{{ route('atividades.inscricoes.exportar-link', [$atividade, $formato]) }}">{{ $rotulo }}</a></li>@endforeach</ul></div>@if(app(\App\Services\GiPermissionService::class)->permite('atividades.listar'))<a href="{{ route('atividades.index') }}" class="btn btn-outline-secondary">Voltar</a>@endif</div></div>
<form method="GET" action="{{ route('atividades.inscricoes',$atividade) }}" class="row g-2 justify-content-end mb-3"><div class="col-12 col-md-5 col-lg-4"><label class="visually-hidden" for="pesquisar">Pesquisar</label><div class="input-group"><input id="pesquisar" name="pesquisar" class="form-control" value="{{ $pesquisar }}" placeholder="Pesquisar inscrições"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-search me-1"></i>Pesquisar</button>@if($pesquisar!=='')<a class="btn btn-outline-secondary" href="{{ route('atividades.inscricoes',$atividade) }}?pesquisar=">Limpar</a>@endif</div></div></form>
@php
    $campos = collect($atividade->formulario['campos'] ?? [])->keyBy('nome');
    $participantes = App\Models\Participante::query()
        ->whereIn('id', $inscricoes->pluck('participante_id')->filter()->unique()->all())
        ->get(['id', 'nome', 'email', 'email2', 'email_institucional', 'instituicao_ensino', 'sexo'])
        ->keyBy('id');
    $dispositivos = app(App\Services\DispositivoVisitanteService::class);
    $podeValidarPresenca = app(App\Services\GiPermissionService::class)->permite('atividades.validador_qr');
    $podeExcluirInscricao = app(App\Services\GiPermissionService::class)->permite('atividades.inscricoes.excluir');
    // Quantidade de respostas exibidas na linha; o restante fica no modal.
    $visiveis = 4;
    $linhas = [];

    foreach ($inscricoes as $inscricao) {
        $resposta = $inscricao->resposta ?? [];
        $participante = $participantes->get($inscricao->participante_id);
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
            'participante' => $participante?->nome,
            'participante_id' => $inscricao->participante_id,
            'participante_email' => $inscricao->participante_email,
            'dados_participante' => $participante ? array_filter([
                'Instituição de ensino' => $participante->instituicao_ensino,
                'E-mail alternativo' => $participante->email2,
                'E-mail institucional' => $participante->email_institucional,
                'Sexo' => $participante->sexo,
            ], fn ($valor) => filled($valor)) : [],
            'lista_reserva' => (bool) $inscricao->lista_reserva,
            'data' => $inscricao->created_at?->format('d/m/Y') ?? '—',
            'hora' => $inscricao->created_at?->format('H:i') ?? '',
            'presente' => (bool) $inscricao->presente,
            'data_presenca' => $inscricao->data_presenca?->format('d/m/Y H:i:s'),
            'presenca_url' => route('atividades.inscricoes.presenca', $inscricao),
            'possui_pix_confirmado' => (int) $inscricao->cobrancas_pix_confirmadas_count > 0,
            'exclusao_url' => route('atividades.inscricoes.destroy', [$atividade, $inscricao]),
            'exclusao_identificacao' => $participante?->nome
                ?: ($inscricao->participante_email ?: 'Inscrição #'.$inscricao->id),
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
                        <td class="text-muted">#{{ $linha['id'] }} @if($linha['lista_reserva'])<span class="badge text-bg-warning d-block mt-1">Além do limite</span>@endif</td>
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
                            @if($podeExcluirInscricao)
                                @if($linha['possui_pix_confirmado'])
                                    <button type="button" class="btn btn-sm btn-outline-danger" disabled title="Esta inscrição possui pagamento PIX confirmado e não pode ser cancelada" aria-label="Cancelamento indisponível: pagamento PIX confirmado"><i class="bi bi-x-circle-fill" aria-hidden="true"></i></button>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-danger excluir-inscricao" data-inscricao="{{ $linha['id'] }}" title="Cancelar inscrição" aria-label="Cancelar inscrição #{{ $linha['id'] }}"><i class="bi bi-x-circle-fill" aria-hidden="true"></i></button>
                                @endif
                            @endif
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
    <div class="modal-header"><div><h2 class="modal-title fs-5">Inscrição <span id="respostaNumero"></span></h2><small class="text-muted d-block" id="respostaData"></small><small class="text-success fw-semibold d-block" id="respostaDataPresenca"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
    <div class="modal-body" id="respostaCorpo"></div>
    <div class="modal-footer">@if($podeValidarPresenca)<button type="button" class="btn btn-outline-success alternar-presenca me-auto" id="respostaPresenca"><i class="bi bi-person-check-fill me-1" aria-hidden="true"></i><span>Marcar presença</span></button>@endif<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button></div>
</div></div></div>

<div class="modal fade" id="autoPresencaModal" tabindex="-1" aria-labelledby="autoPresencaTitulo" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><div><h2 class="modal-title fs-5" id="autoPresencaTitulo"><i class="bi bi-person-check-fill text-success me-2"></i>Auto registro de presença</h2><small class="text-muted">Crie links temporários para os participantes confirmarem a própria presença.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
    <div class="modal-body">
        <form id="criarAutoPresenca" class="row g-3 align-items-end border rounded p-3 bg-light mb-4">
            <div class="col-12 col-md-4"><label class="form-label" for="autoPresencaInicio">Data e hora inicial</label><input class="form-control" type="datetime-local" id="autoPresencaInicio" required></div>
            <div class="col-12 col-md-4"><label class="form-label" for="autoPresencaFim">Data e hora final</label><input class="form-control" type="datetime-local" id="autoPresencaFim" required></div>
            <div class="col-12 col-md-4 d-grid"><button class="btn btn-success" id="gerarAutoPresenca"><i class="bi bi-link-45deg me-1"></i>Gerar link</button></div>
            <div class="col-12"><div class="alert alert-danger py-2 mb-0 d-none" id="autoPresencaErro"></div></div>
        </form>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Link</th><th>Período de validade</th><th class="text-center">Cliques</th><th class="text-center">Ajuste</th><th class="text-end">Ações</th></tr></thead><tbody id="listaAutoPresenca"></tbody></table></div>
        <p class="text-muted text-center py-3 mb-0 d-none" id="autoPresencaVazia">Nenhum link de auto presença foi criado.</p>
    </div>
</div></div></div>

@if($podeExcluirInscricao)
<div class="modal fade" id="excluirInscricaoModal" tabindex="-1" aria-labelledby="excluirInscricaoTitulo" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title fs-5" id="excluirInscricaoTitulo"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Cancelar inscrição</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
    <div class="modal-body">
        <p>Você está prestes a cancelar a inscrição <strong id="excluirInscricaoNumero"></strong> de <strong id="excluirInscricaoParticipante"></strong>.</p>
        <p class="text-muted small">Cobranças pendentes serão removidas no Sicoob antes de liberar a vaga. Respostas, anexos, TXID e histórico serão preservados para auditoria.</p>
        <div class="form-check border rounded p-3 ps-5 bg-light"><input class="form-check-input" type="checkbox" value="1" id="confirmarExclusaoInscricao"><label class="form-check-label fw-semibold" for="confirmarExclusaoInscricao">Tenho certeza de que desejo cancelar esta inscrição.</label></div>
        <div class="alert alert-danger py-2 mt-3 mb-0 d-none" id="excluirInscricaoErro" role="alert"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Voltar</button><button type="button" class="btn btn-danger" id="confirmarExclusaoInscricaoBotao" disabled><i class="bi bi-x-circle-fill me-1"></i>Cancelar inscrição</button></div>
</div></div></div>
@endif

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
const linksAutoPresenca = @json($linksAutoPresenca);
const csrfAutoPresenca = @json(csrf_token());
const listaAutoPresenca = document.getElementById('listaAutoPresenca');
const autoPresencaVazia = document.getElementById('autoPresencaVazia');
const autoPresencaErro = document.getElementById('autoPresencaErro');

function ajusteAutoPresenca(minutos) { return (minutos > 0 ? '+' : '') + minutos; }
function desenharLinksAutoPresenca() {
    listaAutoPresenca.replaceChildren();
    linksAutoPresenca.forEach(link => {
        const linha = document.createElement('tr');
        const celulaLink = document.createElement('td');
        const ancora = document.createElement('a');
        ancora.href = link.url; ancora.target = '_blank'; ancora.rel = 'noopener noreferrer';
        ancora.className = 'small text-break'; ancora.textContent = link.url;
        celulaLink.append(ancora);
        const periodo = document.createElement('td'); periodo.textContent = link.inicio + ' até ' + link.fim;
        const cliques = document.createElement('td'); cliques.className = 'text-center'; cliques.textContent = link.cliques;
        const ajuste = document.createElement('td'); ajuste.className = 'text-center'; ajuste.textContent = ajusteAutoPresenca(link.ajuste_minutos);
        const acoes = document.createElement('td'); acoes.className = 'text-end text-nowrap';
        [[1, 'bi-plus-lg', 'Adicionar um minuto', 'btn-outline-success'], [-1, 'bi-dash-lg', 'Reduzir um minuto', 'btn-outline-warning']].forEach(([minutos, icone, titulo, classe]) => {
            const botao = document.createElement('button'); botao.type = 'button'; botao.className = 'btn btn-sm ' + classe + ' me-1';
            botao.title = titulo; botao.setAttribute('aria-label', titulo); botao.innerHTML = '<i class="bi ' + icone + '"></i>';
            botao.addEventListener('click', () => ajustarLinkAutoPresenca(link, minutos, botao)); acoes.append(botao);
        });
        const excluir = document.createElement('button'); excluir.type = 'button'; excluir.className = 'btn btn-sm btn-outline-danger';
        excluir.title = 'Excluir definitivamente'; excluir.setAttribute('aria-label', 'Excluir definitivamente'); excluir.innerHTML = '<i class="bi bi-trash-fill"></i>';
        excluir.addEventListener('click', () => excluirLinkAutoPresenca(link, excluir)); acoes.append(excluir);
        linha.append(celulaLink, periodo, cliques, ajuste, acoes); listaAutoPresenca.append(linha);
    });
    autoPresencaVazia.classList.toggle('d-none', linksAutoPresenca.length > 0);
}
async function ajustarLinkAutoPresenca(link, minutos, botao) {
    botao.disabled = true;
    try {
        const resposta = await fetch(link.ajustar_url, {method: 'PATCH', credentials: 'same-origin', headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrfAutoPresenca}, body: JSON.stringify({minutos})});
        const dados = await resposta.json(); if (!resposta.ok) throw new Error(dados.message || 'Não foi possível ajustar o prazo.');
        Object.assign(link, dados); desenharLinksAutoPresenca();
    } catch (erro) { alert(erro.message || 'Não foi possível ajustar o prazo.'); } finally { botao.disabled = false; }
}
async function excluirLinkAutoPresenca(link, botao) {
    if (!confirm('Excluir definitivamente este link de auto presença?')) return;
    botao.disabled = true;
    try {
        const resposta = await fetch(link.excluir_url, {method: 'DELETE', credentials: 'same-origin', headers: {'Accept':'application/json','X-CSRF-TOKEN':csrfAutoPresenca}});
        const dados = await resposta.json(); if (!resposta.ok) throw new Error(dados.message || 'Não foi possível excluir o link.');
        linksAutoPresenca.splice(linksAutoPresenca.indexOf(link), 1); desenharLinksAutoPresenca();
    } catch (erro) { alert(erro.message || 'Não foi possível excluir o link.'); } finally { botao.disabled = false; }
}
document.getElementById('criarAutoPresenca').addEventListener('submit', async event => {
    event.preventDefault(); autoPresencaErro.classList.add('d-none');
    const botao = document.getElementById('gerarAutoPresenca'); if (botao.disabled) return; botao.disabled = true;
    try {
        const resposta = await fetch(@json(route('atividades.auto-presenca.criar', $atividade)), {method: 'POST', credentials: 'same-origin', headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrfAutoPresenca}, body: JSON.stringify({inicio:document.getElementById('autoPresencaInicio').value, fim:document.getElementById('autoPresencaFim').value})});
        const dados = await resposta.json(); if (!resposta.ok) throw new Error(dados.message || Object.values(dados.errors || {}).flat()[0] || 'Não foi possível gerar o link.');
        linksAutoPresenca.unshift(dados); desenharLinksAutoPresenca(); event.target.reset();
    } catch (erro) { autoPresencaErro.textContent = erro.message || 'Não foi possível gerar o link.'; autoPresencaErro.classList.remove('d-none'); } finally { botao.disabled = false; }
});
desenharLinksAutoPresenca();

function atualizarBotaoPresenca(botao, inscricao) {
    if (!botao) return;
    botao.dataset.inscricao = inscricao.id;
    botao.classList.toggle('btn-outline-success', !inscricao.presente);
    botao.classList.toggle('btn-outline-danger', inscricao.presente);
    const titulo = inscricao.presente ? 'Remover presença' : 'Marcar presença';
    botao.title = titulo;
    botao.setAttribute('aria-label', titulo);
    botao.querySelector('i').className = 'bi ' + (inscricao.presente ? 'bi-person-x-fill' : 'bi-person-check-fill');
    const texto = botao.querySelector('span');
    if (texto) texto.textContent = titulo;
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
    const acao = inscricao.presente ? 'remover a presença' : 'marcar a presença';
    if (!confirm('Deseja ' + acao + ' desta inscrição?')) return;
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

    if (Object.keys(inscricao.dados_participante || {}).length) {
        const alternarDados = document.createElement('button');
        alternarDados.type = 'button';
        alternarDados.className = 'btn btn-link btn-sm px-0 mb-2';
        alternarDados.setAttribute('aria-expanded', 'false');
        alternarDados.innerHTML = '<i class="bi bi-person-vcard me-1" aria-hidden="true"></i>Ver dados do participante';
        const dados = document.createElement('div');
        dados.className = 'd-none mb-4';
        const gradeDados = document.createElement('div');
        gradeDados.className = 'resposta-detalhe row g-3 mx-0';
        Object.entries(inscricao.dados_participante).forEach(([rotulo, valor]) => {
            const coluna = document.createElement('div');
            coluna.className = 'col-sm-6 px-0';
            const titulo = document.createElement('div');
            titulo.className = 'resposta-rotulo';
            titulo.textContent = rotulo;
            const conteudo = document.createElement('div');
            conteudo.className = 'resposta-valor text-break';
            conteudo.textContent = valor;
            coluna.append(titulo, conteudo);
            gradeDados.append(coluna);
        });
        dados.append(gradeDados);
        alternarDados.addEventListener('click', () => {
            const oculto = dados.classList.toggle('d-none');
            alternarDados.setAttribute('aria-expanded', String(!oculto));
            alternarDados.innerHTML = '<i class="bi bi-person-vcard me-1" aria-hidden="true"></i>'
                + (oculto ? 'Ver dados do participante' : 'Ocultar dados do participante');
        });
        corpo.append(alternarDados, dados);
    }

    const tituloRespostas = document.createElement('h3');
    tituloRespostas.className = 'h6 text-uppercase text-muted fw-semibold mb-3';
    tituloRespostas.textContent = 'Respostas da inscrição';
    corpo.append(tituloRespostas);

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

@if($podeExcluirInscricao)
const excluirInscricaoModalElemento = document.getElementById('excluirInscricaoModal');
const excluirInscricaoModal = bootstrap.Modal.getOrCreateInstance(excluirInscricaoModalElemento);
const confirmarExclusaoInscricao = document.getElementById('confirmarExclusaoInscricao');
const confirmarExclusaoInscricaoBotao = document.getElementById('confirmarExclusaoInscricaoBotao');
const excluirInscricaoErro = document.getElementById('excluirInscricaoErro');
let inscricaoParaExcluir = null;

document.querySelectorAll('.excluir-inscricao').forEach(botao => botao.addEventListener('click', () => {
    const inscricao = inscricoes[botao.dataset.inscricao];
    if (!inscricao || inscricao.possui_pix_confirmado) return;
    inscricaoParaExcluir = inscricao;
    confirmarExclusaoInscricao.checked = false;
    confirmarExclusaoInscricaoBotao.disabled = true;
    excluirInscricaoErro.classList.add('d-none');
    excluirInscricaoErro.textContent = '';
    document.getElementById('excluirInscricaoNumero').textContent = '#' + inscricao.id;
    document.getElementById('excluirInscricaoParticipante').textContent = inscricao.exclusao_identificacao;
    excluirInscricaoModal.show();
}));

confirmarExclusaoInscricao.addEventListener('change', () => {
    confirmarExclusaoInscricaoBotao.disabled = !confirmarExclusaoInscricao.checked;
});

confirmarExclusaoInscricaoBotao.addEventListener('click', async () => {
    if (!inscricaoParaExcluir || !confirmarExclusaoInscricao.checked || confirmarExclusaoInscricaoBotao.disabled) return;
    confirmarExclusaoInscricaoBotao.disabled = true;
    excluirInscricaoErro.classList.add('d-none');
    try {
        const resposta = await fetch(inscricaoParaExcluir.exclusao_url, {
            method: 'DELETE', credentials: 'same-origin',
            headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':@json(csrf_token())},
            body: JSON.stringify({confirmacao: true}),
        });
        const dados = await resposta.json().catch(() => ({}));
        if (!resposta.ok) throw new Error(dados.message || 'Não foi possível cancelar a inscrição.');
        window.location.reload();
    } catch (erro) {
        excluirInscricaoErro.textContent = erro.message || 'Não foi possível cancelar a inscrição.';
        excluirInscricaoErro.classList.remove('d-none');
        confirmarExclusaoInscricaoBotao.disabled = !confirmarExclusaoInscricao.checked;
    }
});

excluirInscricaoModalElemento.addEventListener('hidden.bs.modal', () => { inscricaoParaExcluir = null; });
@endif

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
