@extends('layouts.app')
@section('title','Inscrições da atividade')
@section('content')
<div class="mb-4 d-flex flex-wrap gap-3 justify-content-between"><div><h1 class="page-title">Inscrições</h1><p class="page-description mb-0">{{ $atividade->nome }}</p></div><div class="d-flex gap-2 align-items-start"><div class="dropdown"><button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-download me-1"></i>Exportar respostas</button><ul class="dropdown-menu">@foreach(['ods' => 'Planilha (.ods)', 'csv' => 'CSV (.csv)', 'xls' => 'Excel (.xls)', 'xlsx' => 'Excel (.xlsx)'] as $formato => $rotulo)<li><a class="dropdown-item exportar-respostas" href="{{ route('atividades.inscricoes.exportar', [$atividade, $formato]) }}">{{ $rotulo }}</a></li>@endforeach</ul></div><a href="{{ route('atividades.index') }}" class="btn btn-outline-secondary">Voltar</a></div></div>
@php
    $campos = collect($atividade->formulario['campos'] ?? [])->keyBy('nome');
    // Quantidade de respostas exibidas na linha; o restante fica no modal.
    $visiveis = 4;
    $linhas = [];

    foreach ($inscricoes as $inscricao) {
        $resposta = $inscricao->resposta ?? [];
        // Segue a ordem dos campos do formulário para alinhar as colunas entre as linhas;
        // respostas de campos já removidos vão para o fim.
        $nomes = $campos->keys()->intersect(array_keys($resposta))->all();
        $nomes = array_merge($nomes, array_values(array_diff(array_keys($resposta), $nomes)));

        $respostas = [];
        $anexos = [];

        foreach ($nomes as $nome) {
            $campo = $campos->get($nome, []);
            $label = ($campo['label'] ?? '') ?: str_replace('_', ' ', $nome);
            $valores = is_array($resposta[$nome]) ? Illuminate\Support\Arr::flatten($resposta[$nome]) : [$resposta[$nome]];
            $textos = [];
            $arquivos = [];

            foreach ($valores as $item) {
                // Stored uploads also remain accessible after their field is removed.
                if (is_string($item) && str_starts_with($item, 'inscricoes/') && !str_contains($item, '..')) {
                    $arquivos[] = [
                        'url' => Illuminate\Support\Facades\Storage::disk('public')->url($item),
                        'extensao' => strtoupper(pathinfo($item, PATHINFO_EXTENSION)) ?: 'ARQUIVO',
                    ];
                } else {
                    $textos[] = is_bool($item) ? ($item ? 'Sim' : 'Não') : (string) $item;
                }
            }

            if ($arquivos !== []) $anexos[] = ['label' => $label, 'arquivos' => $arquivos];
            if ($textos !== [] || ($arquivos === [] && ($campo['tipo'] ?? '') !== 'file')) {
                $respostas[] = ['label' => $label, 'valores' => $textos];
            }
        }

        $linhas[] = [
            'id' => $inscricao->id,
            'data' => $inscricao->created_at?->format('d/m/Y') ?? '—',
            'hora' => $inscricao->created_at?->format('H:i') ?? '',
            'respostas' => $respostas,
            'anexos' => $anexos,
        ];
    }
@endphp
<div class="card content-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 tabela-inscricoes">
                <thead><tr><th>ID</th><th>Data</th><th>Respostas</th><th>Anexos</th><th class="text-end">Ações</th></tr></thead>
                <tbody>
                @forelse($linhas as $linha)
                    <tr>
                        <td class="text-muted">#{{ $linha['id'] }}</td>
                        <td class="text-nowrap">{{ $linha['data'] }} <small class="text-muted">{{ $linha['hora'] }}</small></td>
                        <td>
                            @if($linha['respostas'])
                                <div class="respostas-inline">
                                    @foreach(array_slice($linha['respostas'], 0, $visiveis) as $item)
                                        @php($texto = implode(', ', array_filter($item['valores'], 'strlen')))
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
                            <button type="button" class="btn btn-sm btn-outline-dark ver-respostas" data-inscricao="{{ $linha['id'] }}" title="Visualizar respostas">
                                <i class="bi bi-eye-fill me-1" aria-hidden="true"></i>Visualizar
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-4">Nenhuma inscrição encontrada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">{{ $inscricoes->links() }}</div>
</div>

<div class="modal fade" id="respostaModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><div><h2 class="modal-title fs-5">Inscrição <span id="respostaNumero"></span></h2><small class="text-muted" id="respostaData"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
    <div class="modal-body" id="respostaCorpo"></div>
</div></div></div>
@endsection

@push('styles')
<style>
    .tabela-inscricoes tbody td { padding-top: 11px; padding-bottom: 11px; }
    .respostas-inline { display: flex; flex-wrap: wrap; gap: 4px 22px; }
    .resposta-item { min-width: 0; max-width: 190px; }
    .resposta-rotulo { display: block; font-size: 11px; font-weight: 700; line-height: 1.3; color: #748096; text-transform: uppercase; letter-spacing: .02em; }
    .resposta-valor { display: block; overflow: hidden; font-size: 13.5px; line-height: 1.35; text-overflow: ellipsis; white-space: nowrap; }
    .resposta-detalhe { padding: 10px 14px; background: #fafbfc; border: 1px solid #eef1f5; border-radius: 10px; }
    .resposta-detalhe dt { font-size: 11px; font-weight: 700; color: #748096; text-transform: uppercase; letter-spacing: .02em; }
    .resposta-detalhe dd { margin: 2px 0 0; font-size: 14px; white-space: pre-wrap; overflow-wrap: anywhere; }
</style>
@endpush

@push('scripts')
<script>
const inscricoes = @json(collect($linhas)->keyBy('id'));

document.querySelectorAll('.ver-respostas').forEach(botao => botao.addEventListener('click', () => {
    const inscricao = inscricoes[botao.dataset.inscricao];
    if (!inscricao) return;

    document.getElementById('respostaNumero').textContent = '#' + inscricao.id;
    document.getElementById('respostaData').textContent = 'Enviada em ' + inscricao.data + ' às ' + inscricao.hora;

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

    bootstrap.Modal.getOrCreateInstance(document.getElementById('respostaModal')).show();
}));

document.querySelectorAll('.exportar-respostas').forEach(link => link.addEventListener('click', async event => {
    event.preventDefault();
    const button = document.querySelector('.dropdown-toggle');
    if (button.disabled) return;
    button.disabled = true;
    try {
        const response = await fetch(link.href, {credentials: 'same-origin', headers: {'Accept': 'application/octet-stream'}});
        if (!response.ok) throw new Error('Não foi possível exportar as respostas. Verifique sua sessão e tente novamente.');
        const disposition = response.headers.get('Content-Disposition') || '';
        if (!disposition.includes('attachment')) throw new Error('A sessão expirou. Reabra a aplicação pelo sistema GI e tente novamente.');
        const utf8 = disposition.match(/filename\*=utf-8''([^;]+)/i);
        const fallback = disposition.match(/filename="([^"]+)"|filename=([^;]+)/i);
        const filename = utf8 ? decodeURIComponent(utf8[1]) : (fallback?.[1] || fallback?.[2] || 'respostas');
        const url = URL.createObjectURL(await response.blob());
        const download = document.createElement('a');
        download.href = url;
        download.download = filename;
        document.body.appendChild(download);
        download.click();
        download.remove();
        setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (error) {
        alert(error.message || 'Não foi possível baixar o arquivo. Tente novamente.');
    } finally {
        button.disabled = false;
    }
}));
</script>
@endpush
