@extends('layouts.app')
@section('title', $config['titulo'] ?? $atividade->nome)
@section('content')
@php
    // O estado vem do controller (FormularioInscricaoService::estado), que também conhece
    // o participante identificado e por isso sabe dizer se ele já se inscreveu.
    $eventoVisual = $atividade->evento ?? new \App\Models\Evento;
    $imagemVisual = $atividade->estiloImagem();
    $aberto = $estado['aberto'];
    $errosIdentificacao = $errors->identificacao;
    // Mantém o e-mail digitado entre um passo e outro da identificação.
    $emailInformado = old('email', session('codigo_enviado', ''));
@endphp
<div class="container py-4" style="max-width: 900px">
    <div class="mb-4 rounded-4 p-4 p-md-5" style="background: {{ $eventoVisual->fundoFormulario('atividade') }}; color: {{ $eventoVisual->estiloFormulario('atividade')['cor_fonte'] }};">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-4 {{ $imagemVisual['imagem'] && $imagemVisual['posicao'] === 'direita' ? 'flex-sm-row-reverse justify-content-sm-between' : '' }}">
            @if($imagemVisual['imagem'])
                <img src="{{ route('eventos.personalizacao.imagem', ['arquivo' => $imagemVisual['imagem']]) }}" alt="Imagem da atividade {{ $atividade->nome }}" width="150" height="108" class="rounded object-fit-cover flex-shrink-0" style="{{ $imagemVisual['borda'] ? 'border: 3px solid '.$imagemVisual['cor_borda'].';' : '' }}">
            @endif
            <div>
                <h1 class="page-title">{{ $config['titulo'] ?? $atividade->nome }}</h1>
                <p class="page-description" style="color: inherit;">{{ $config['subtitulo'] ?? '' }}</p>
                <div class="small d-flex flex-wrap column-gap-4 row-gap-2">
                    <p class="mb-1"><strong>Evento:</strong> {{ $atividade->evento?->nome ?? 'Não informado' }}</p>
                    <p class="mb-1"><strong>Início:</strong> {{ $atividade->data_inicio?->format('d/m/Y \à\s H:i') ?? 'Não informado' }}</p>
                    <p class="mb-0"><strong>Fim:</strong> {{ $atividade->data_fim?->format('d/m/Y \à\s H:i') ?? 'Não informado' }}</p>
                </div>
            </div>
        </div>
    </div>

    @if(($config['editor']['exibir'] ?? !empty($config['editor']['conteudo'])) && !empty($config['editor']['conteudo']))
        <div class="editor-publico mb-4">{!! $config['editor']['conteudo'] !!}</div>
    @endif

    @if(!empty($config['limitar_inscricoes']) && !empty($config['mostrar_vagas_restantes']))
        @php
            $totalVagas = $config['distribuicao_vagas']['total'] ?? [
                'usadas' => $atividade->inscricoes()->count(),
                'disponiveis' => (int) ($config['limite_inscricoes'] ?? 0),
                'restantes' => 0,
            ];
        @endphp
        <div class="d-flex justify-content-end mb-3"><span class="badge text-bg-light border fs-6" title="{{ $totalVagas['usadas'] }} inscrição(ões) de {{ $totalVagas['disponiveis'] }} vagas; restam {{ $totalVagas['restantes'] }}">Vagas: {{ $totalVagas['usadas'] }}/{{ $totalVagas['disponiveis'] }}</span></div>
    @endif

    @if(session('status'))<div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>{{ session('status') }}</div>@endif
    @if(session('vagas_esgotadas'))<div class="alert alert-warning">{{ session('vagas_esgotadas') }}</div>@endif
    @if(session('identificacao_expirada'))<div class="alert alert-warning">{{ session('identificacao_expirada') }}</div>@endif
    @if(session('comprovante_erro'))<div class="alert alert-danger">{{ session('comprovante_erro') }}</div>@endif

    @if($identificacao)
        <div id="inicio-formulario" class="d-flex flex-wrap gap-2 align-items-center justify-content-between alert alert-light border ancora-formulario">
            <span><i class="bi bi-envelope-check me-1"></i>Você já entrou com <strong>{{ $identificacao['email'] }}</strong></span>
            <form method="POST" action="{{ request()->fullUrl() }}" class="m-0">
                @csrf<input type="hidden" name="acao" value="trocar_email">
                <button class="btn btn-link btn-sm" type="submit">Entrar com outro e-mail</button>
            </form>
        </div>
    @endif

    @if(! $aberto)
        <div class="alert {{ $estado['motivo'] === 'antes' ? 'alert-info' : 'alert-warning' }}">
            @if($estado['motivo'] === 'duplicada')<i class="bi bi-person-check me-1"></i>@endif{{ $estado['mensagem'] }}
        </div>
    @endif

    @if($estado['motivo'] === 'duplicada' && $inscricao)
        @php
            $itensComprovante = count($dadosComprovante) + count($respostasComprovante);
            $caracteresComprovante = collect([...$dadosComprovante, ...$respostasComprovante])
                ->sum(fn ($item) => mb_strlen((string) ($item['label'] ?? '') . (string) ($item['valor'] ?? '')));
            $densidadeComprovante = $itensComprovante > 22 || $caracteresComprovante > 2600
                ? 'comprovante-ultracompacto'
                : ($itensComprovante > 13 || $caracteresComprovante > 1500 ? 'comprovante-compacto' : '');
        @endphp
        <div class="d-flex flex-wrap gap-2 mb-3">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#comprovanteModal"><i class="bi bi-printer me-1"></i>Imprimir comprovante</button>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#apagarInscricaoModal"><i class="bi bi-trash me-1"></i>Apagar inscrição</button>
        </div>

        <div id="comprovanteRespostas" class="card content-card {{ $densidadeComprovante }}">
            <div class="card-header"><h2 class="h5 fw-bold mb-0">Respostas da inscrição</h2></div>
            <div class="card-body p-4">
                @include('atividades.partials.comprovante-respostas', ['dadosParticipante' => $dadosComprovante, 'respostas' => $respostasComprovante])
                @include('atividades.partials.qrcode-presenca', ['qrPresenca' => $qrPresenca, 'presenca' => $presencaInscricao ?? null])
            </div>
        </div>

        <div class="modal fade" id="comprovanteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <div class="modal-header"><h2 class="modal-title fs-5">Comprovante da inscrição</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                <div class="modal-body"><p>Escolha como deseja guardar ou compartilhar suas respostas.</p><div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary" id="imprimirComprovante"><i class="bi bi-printer me-1"></i>Imprimir</button>
                    <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('inscricoes.comprovante.pdf', $inscricao->comprovante_hash) }}"><i class="bi bi-file-earmark-pdf me-1"></i>Abrir ou exportar PDF</a>
                    <form method="POST" action="{{ route('inscricoes.comprovante.email', ['atividade' => $atividade->hash_publica]) }}">@csrf<button class="btn btn-outline-primary w-100"><i class="bi bi-envelope me-1"></i>Enviar respostas por e-mail</button></form>
                </div></div>
            </div></div>
        </div>

        <div class="modal fade" id="apagarInscricaoModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('inscricoes.apagar', ['atividade' => $atividade->hash_publica]) }}">@csrf @method('DELETE')
                <div class="modal-header"><h2 class="modal-title fs-5">Apagar inscrição</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
                <div class="modal-body"><div class="alert alert-danger mb-3"><strong>Esta ação é definitiva.</strong> A inscrição, as respostas e os arquivos enviados serão apagados e não será possível recuperá-los.</div><label class="form-check"><input class="form-check-input" type="checkbox" name="confirmacao" value="APAGAR" required><span class="form-check-label">Confirmo que desejo apagar esta inscrição.</span></label></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-danger"><i class="bi bi-trash me-1"></i>Apagar definitivamente</button></div>
            </form></div></div>
        </div>
    @endif

    @if($aberto && ! $identificacao)
        {{-- Etapa 1: o visitante confirma o e-mail antes de o formulário da atividade aparecer. --}}
        <div class="card content-card">
            <div class="card-header"><h2 class="h5 fw-bold mb-0">Identifique-se para se inscrever</h2></div>
            <div class="card-body p-4">
                <p class="text-muted">{{ $atividade->mensagemIdentificacao() }}</p>

                @if($errosIdentificacao->any())
                    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errosIdentificacao->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>
                @endif
                @if(session('ja_inscrito'))
                    <div class="alert alert-warning"><i class="bi bi-person-check me-1"></i>{{ session('ja_inscrito') }}</div>
                @endif
                @if(session('codigo_enviado'))
                    <div class="alert alert-success"><i class="bi bi-envelope-check me-1"></i>Enviamos o código para <strong>{{ session('codigo_enviado') }}</strong>. Ele vale por {{ \App\Services\IdentificacaoParticipanteService::MINUTOS_VALIDADE }} minutos.</div>
                @endif

                <form method="POST" action="{{ request()->fullUrl() }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="email">E-mail *</label>
                        <input class="form-control @if($errosIdentificacao->has('email')) is-invalid @endif" type="email" id="email" name="email" maxlength="150" required autocomplete="email" placeholder="voce@exemplo.com" value="{{ $emailInformado }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="senha">Senha de inscrição</label>
                        <div class="input-group senha-inscricao">
                            <input class="form-control @if($errosIdentificacao->has('senha')) is-invalid @endif" type="password" id="senha" name="senha" maxlength="200" autocomplete="current-password">
                            <button class="btn btn-primary" type="submit" name="acao" value="validar_senha"><i class="bi bi-key me-1"></i>Entrar com senha</button>
                        </div>
                        @if($errosIdentificacao->has('senha'))<div class="text-danger small mt-1">{{ $errosIdentificacao->first('senha') }}</div>@endif
                    </div>
                    <div class="d-flex align-items-center gap-3 my-4"><hr class="flex-grow-1 m-0"><span class="text-muted small text-center">Esqueceu ou ainda não tem uma senha?</span><hr class="flex-grow-1 m-0"></div>
                    <p class="text-muted small">Receba um código temporário por e-mail. A mensagem também terá um link para você definir uma senha.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="captcha">Digite o texto da imagem</label>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <img id="captchaImagem" src="{{ route('inscricoes.captcha', ['atividade' => $atividade->hash_publica]) }}" width="220" height="70" class="border rounded" alt="Imagem com seis caracteres para confirmação">
                            <button class="btn btn-sm btn-outline-secondary" type="button" id="renovarCaptcha"><i class="bi bi-arrow-clockwise me-1"></i>Nova imagem</button>
                        </div>
                        <input class="form-control @if($errosIdentificacao->has('captcha')) is-invalid @endif" style="max-width:220px;text-transform:uppercase;letter-spacing:.2em" type="text" id="captcha" name="captcha" maxlength="6" autocomplete="off" autocapitalize="characters">
                        @if($errosIdentificacao->has('captcha'))<div class="text-danger small mt-1">{{ $errosIdentificacao->first('captcha') }}</div>@endif
                    </div>
                    <button class="btn btn-outline-primary mb-3" type="submit" name="acao" value="solicitar_codigo"><i class="bi bi-send me-1"></i>Enviar código para o e-mail</button>
                    <div class="mb-3">
                        <label class="form-label" for="codigo">Código recebido</label>
                        <div class="input-group">
                            <input class="form-control @if($errosIdentificacao->has('codigo')) is-invalid @endif" type="text" id="codigo" name="codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000">
                            <button class="btn btn-outline-primary" type="submit" name="acao" value="validar_codigo"><i class="bi bi-check2 me-1"></i>Confirmar código</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @elseif($aberto)
        {{-- Etapa 2: identificado, o visitante completa o cadastro e responde ao formulário. --}}
        @if(session('identificado'))<div class="alert alert-info"><i class="bi bi-person-check me-1"></i>{{ session('identificado') }}</div>@endif
        @if($errors->getBag('default')->any())
            <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->getBag('default')->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>
        @endif



        <form method="POST" action="{{ request()->fullUrl() }}" enctype="multipart/form-data">
            @csrf
            {{-- Segue junto da inscrição; o servidor confere o valor gravado na sessão. --}}
            <input type="hidden" name="participante_email" value="{{ $identificacao['email'] }}">

            <div class="card content-card mb-3">
                <div class="card-header"><h2 class="h5 fw-bold mb-0">Seus dados</h2></div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label class="form-label" for="participante_nome">Nome completo *</label>
                            <input class="form-control" id="participante_nome" name="participante[nome]" maxlength="100" required autocomplete="name" placeholder="Nome e sobrenome" value="{{ old('participante.nome', $participante->nome) }}">
                            <div class="invalid-feedback">Informe o nome completo, com pelo menos um sobrenome.</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="participante_cpf">CPF *</label>
                            <input class="form-control" id="participante_cpf" name="participante[cpf]" maxlength="14" inputmode="numeric" autocomplete="off" required placeholder="000.000.000-00" value="{{ old('participante.cpf', $participante->cpf) }}">
                            <div class="invalid-feedback">CPF inválido: confira os números digitados.</div>
                            <div class="form-text">solicitado para a emissão de certificado quando for o caso.</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">E-mail</label>
                            <input class="form-control" type="email" value="{{ $identificacao['email'] }}" readonly>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="participante_email2">E-mail alternativo</label>
                            <input class="form-control" type="email" id="participante_email2" name="participante[email2]" maxlength="150" value="{{ old('participante.email2', $participante->email2) }}">
                            <div class="invalid-feedback">Informe um e-mail completo, no formato nome@dominio.com.</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="participante_email_institucional">E-mail institucional</label>
                            <input class="form-control" type="email" id="participante_email_institucional" name="participante[email_institucional]" maxlength="150" value="{{ old('participante.email_institucional', $participante->email_institucional) }}">
                            <div class="invalid-feedback">Informe um e-mail completo, no formato nome@dominio.com.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="participante_instituicao">Instituição de ensino</label>
                            <input class="form-control" id="participante_instituicao" name="participante[instituicao_ensino]" maxlength="80" value="{{ old('participante.instituicao_ensino', $participante->instituicao_ensino) }}">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="participante_sexo">Sexo</label>
                            @php
                                $sexo = old('participante.sexo', $participante->sexo);
                            @endphp
                            <select class="form-select" id="participante_sexo" name="participante[sexo]">
                                <option value="">Não informado</option>
                                <option value="M" @selected($sexo === 'M')>Masculino</option>
                                <option value="F" @selected($sexo === 'F')>Feminino</option>
                            </select>
                            <div class="form-text">Solicitado para emissão automatizada de certificado com o pronome correto, quando for o caso.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card content-card">
                <div class="card-body p-4">
                    <div class="row g-3">
                        @foreach($config['campos'] ?? [] as $campo)
                            @php
                                $nome = $campo['nome'];
                                $tipo = $campo['tipo'] ?? 'text';
                                $opcoesCampo = $campo['opcoes'] ?? [];
                                $checkboxSimples = $tipo === 'checkbox' && $opcoesCampo === [];
                                $multiplo = $tipo === 'multiselect' || ($tipo === 'checkbox' && ! $checkboxSimples);
                                $anterior = old($nome);
                                $criterioVagas = !empty($campo['criterio_vagas']);
                                $nivelVagas = collect($config['distribuicao_vagas']['niveis'] ?? [])->firstWhere('campo', $nome);
                            @endphp
                            <div class="col-12 col-md-{{ in_array((int) ($campo['grid'] ?? 6), [12, 6, 4]) ? (int) ($campo['grid'] ?? 6) : 6 }}">
                                @if(in_array($tipo, ['radio', 'checkbox'], true) && ! $checkboxSimples)
                                    <div class="form-label">{{ $campo['label'] ?? $nome }} @if(!empty($campo['obrigatorio']))*@endif</div>
                                @else
                                    <label class="form-label" for="campo_{{ $loop->index }}">{{ $campo['label'] ?? $nome }} @if(!empty($campo['obrigatorio']))*@endif</label>
                                @endif
                                @if(in_array($tipo, ['select', 'multiselect'], true))
                                    <select class="form-select" id="campo_{{ $loop->index }}" name="{{ $nome }}{{ $multiplo ? '[]' : '' }}" data-nome-campo="{{ $nome }}" @if($criterioVagas) data-criterio-vagas="1" @endif @if(!empty($campo['obrigatorio']) || $criterioVagas) required @endif @if($multiplo) multiple @endif>
                                        @foreach($campo['opcoes'] ?? [] as $opcao)
                                            @php
                                                $valorOpcao = is_array($opcao) ? (string) $opcao['valor'] : (string) $opcao;
                                                $textoOpcao = is_array($opcao) ? $opcao['texto'] : $opcao;
                                                $cotasOpcao = collect($nivelVagas['contextos'] ?? [])->sum(fn ($contexto) => (int) ($contexto['opcoes'][$valorOpcao]['usadas'] ?? 0));
                                                $restantesOpcao = collect($nivelVagas['contextos'] ?? [])->sum(fn ($contexto) => (int) ($contexto['opcoes'][$valorOpcao]['restantes'] ?? 0));
                                            @endphp
                                            <option value="{{ $valorOpcao }}" data-texto="{{ $textoOpcao }}" @selected(is_array($anterior) ? in_array($valorOpcao, $anterior) : (string) $anterior === $valorOpcao)>{{ $textoOpcao }}@if($criterioVagas && !empty($config['mostrar_vagas_restantes'])) — {{ $cotasOpcao }}/{{ $restantesOpcao }}@endif</option>
                                        @endforeach
                                    </select>
                                @elseif(in_array($tipo, ['radio', 'checkbox'], true))
                                    @if($checkboxSimples)
                                        <div class="form-check pt-1">
                                            <input class="form-check-input" type="checkbox" id="campo_{{ $loop->index }}" name="{{ $nome }}" value="1" @checked((string) $anterior === '1') @if(!empty($campo['obrigatorio'])) required @endif>
                                            <label class="form-check-label" for="campo_{{ $loop->index }}">{{ $campo['label'] ?? $nome }} @if(!empty($campo['obrigatorio']))*@endif</label>
                                            @if(!empty($campo['obrigatorio']))<div class="invalid-feedback">Marque esta declaração para continuar.</div>@endif
                                        </div>
                                    @else
                                    <div id="campo_{{ $loop->index }}" class="d-flex flex-column gap-2 pt-1" role="group" aria-label="{{ $campo['label'] ?? $nome }}" @if(!empty($campo['obrigatorio'])) data-checkbox-obrigatorio @endif>
                                        @foreach($opcoesCampo as $opcao)
                                            @php
                                                $valorOpcao = is_array($opcao) ? (string) $opcao['valor'] : (string) $opcao;
                                                $textoOpcao = is_array($opcao) ? $opcao['texto'] : $opcao;
                                                $selecionado = $multiplo
                                                    ? in_array($valorOpcao, (array) $anterior, true)
                                                    : (string) $anterior === $valorOpcao;
                                            @endphp
                                            <div class="form-check">
                                                <input class="form-check-input" type="{{ $tipo }}" id="campo_{{ $loop->parent->index }}_opcao_{{ $loop->index }}" name="{{ $nome }}{{ $multiplo ? '[]' : '' }}" value="{{ $valorOpcao }}" @checked($selecionado) @if($tipo === 'radio' && !empty($campo['obrigatorio'])) required @endif>
                                                <label class="form-check-label" for="campo_{{ $loop->parent->index }}_opcao_{{ $loop->index }}">{{ $textoOpcao }}</label>
                                            </div>
                                        @endforeach
                                        @if($tipo === 'checkbox' && !empty($campo['obrigatorio']))<div class="invalid-feedback">Selecione pelo menos uma opção.</div>@endif
                                    </div>
                                    @endif
                                @elseif($tipo === 'textarea')
                                    <textarea class="form-control" id="campo_{{ $loop->index }}" name="{{ $nome }}" placeholder="{{ $campo['placeholder'] ?? '' }}" @if(!empty($campo['obrigatorio'])) required @endif>{{ $anterior }}</textarea>
                                @elseif($tipo === 'file')
                                    @php $varios = ($campo['max_arquivos'] ?? 1) > 1; @endphp
                                    <input class="form-control" type="file" id="campo_{{ $loop->index }}" name="{{ $nome }}{{ $varios ? '[]' : '' }}" @if($varios) multiple @endif @if(!empty($campo['obrigatorio'])) required @endif accept="{{ implode(',', $campo['aceitos'] ?? []) }}">
                                @else
                                    <input class="form-control" type="{{ $tipo }}" id="campo_{{ $loop->index }}" name="{{ $nome }}" placeholder="{{ $campo['placeholder'] ?? '' }}" @if(!empty($campo['obrigatorio'])) required @endif value="{{ is_array($anterior) ? '' : $anterior }}">
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <button class="btn btn-primary mt-4"><i class="bi bi-send me-1"></i>Enviar inscrição</button>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection

@push('styles')
<style>
    .editor-publico { width: 100%; max-width: 100%; overflow: visible; overflow-wrap: anywhere; }
    .editor-publico img { display: inline-block; max-width: 100%; height: auto; vertical-align: middle; }
    .editor-publico .ql-align-center { text-align: center; }
    .editor-publico .ql-align-right { text-align: right; }
    .editor-publico .ql-align-justify { text-align: justify; }
    .editor-publico .ql-font-serif { font-family: Georgia, serif; }
    .editor-publico .ql-font-monospace { font-family: Monaco, Consolas, monospace; }
    .editor-publico .ql-size-small { font-size: .75em; }
    .editor-publico .ql-size-large { font-size: 1.5em; }
    .editor-publico .ql-size-huge { font-size: 2.5em; }
    .editor-publico li[data-list="bullet"] { list-style-type: disc; }
    .editor-publico li[data-list="ordered"] { list-style-type: decimal; }
    .editor-publico pre { white-space: pre-wrap; overflow: visible; }
    [data-checkbox-obrigatorio].is-invalid .invalid-feedback { display: block; }
    .ancora-formulario { scroll-margin-top: 1rem; }
    @media (max-width: 575.98px) {
        .senha-inscricao { flex-direction: column; align-items: stretch; gap: .5rem; }
        .senha-inscricao > .form-control,
        .senha-inscricao > .btn { width: 100%; border-radius: var(--bs-border-radius) !important; }
    }
    /* Fora da tela em vez de display:none, para o robô continuar preenchendo. */
</style>
@endpush

@push('scripts')
<script>
const captchaImagem = document.getElementById('captchaImagem');
document.getElementById('renovarCaptcha')?.addEventListener('click', () => {
    captchaImagem.src = @json(route('inscricoes.captcha', ['atividade' => $atividade->hash_publica])) + '?novo=1&t=' + Date.now();
    document.getElementById('captcha').value = '';
});

// Conferencia imediata no navegador. O servidor revalida tudo (App\Rules\Cpf e App\Rules\EmailValido),
// entao aqui o objetivo e so evitar que o visitante envie e volte com erro.
const digitosDoCpf = valor => valor.replace(/\D/g, '');

const cpfValido = valor => {
    const digitos = digitosDoCpf(valor);
    if (digitos.length !== 11 || /^(\d)\1{10}$/.test(digitos)) return false;

    return [9, 10].every(posicao => {
        let soma = 0;
        for (let i = 0; i < posicao; i++) soma += Number(digitos[i]) * (posicao + 1 - i);
        const resto = soma % 11;
        return Number(digitos[posicao]) === (resto < 2 ? 0 : 11 - resto);
    });
};

const emailValido = valor => /^[^\s@]+@[^\s@.]+(\.[^\s@.]+)+$/.test(valor.trim());

// Espelha App\Rules\NomeCompleto: particulas e iniciais soltas nao valem como sobrenome.
const PARTICULAS = ['de', 'da', 'do', 'das', 'dos', 'del', 'della', 'di', 'du', 'e', 'la', 'le', 'van', 'von', 'y'];

const nomeCompleto = valor => {
    const nome = valor.trim().replace(/\s+/g, ' ');
    if (nome === '') return false;
    if (!/^[\p{L}\p{M}'\-. ]+$/u.test(nome)) return false;

    return nome.split(' ').filter(parte =>
        !PARTICULAS.includes(parte.toLowerCase()) && parte.replace(/^[.'-]+|[.'-]+$/g, '').length >= 2
    ).length >= 2;
};

const marcar = (campo, valido) => campo.classList.toggle('is-invalid', campo.value.trim() !== '' && !valido);

const nome = document.getElementById('participante_nome');
if (nome) {
    nome.addEventListener('blur', () => marcar(nome, nomeCompleto(nome.value)));
    nome.addEventListener('input', () => nome.classList.remove('is-invalid'));
}

const cpf = document.getElementById('participante_cpf');
if (cpf) {
    // Formata enquanto digita, mas envia so os numeros.
    cpf.addEventListener('input', () => {
        const digitos = digitosDoCpf(cpf.value).slice(0, 11);
        cpf.value = digitos
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
        cpf.classList.remove('is-invalid');
    });
    cpf.addEventListener('blur', () => marcar(cpf, cpfValido(cpf.value)));
}

document.querySelectorAll('#participante_email2, #participante_email_institucional, #email').forEach(campo => {
    campo.addEventListener('blur', () => marcar(campo, emailValido(campo.value)));
    campo.addEventListener('input', () => campo.classList.remove('is-invalid'));
});

const atualizarCheckboxObrigatorio = grupo => {
    const opcoes = [...grupo.querySelectorAll('input[type=checkbox]')];
    const valido = opcoes.some(opcao => opcao.checked);
    opcoes[0]?.setCustomValidity(valido ? '' : 'Selecione pelo menos uma opção.');
    grupo.classList.toggle('is-invalid', !valido);
};
document.querySelectorAll('[data-checkbox-obrigatorio]').forEach(grupo => {
    const opcoes = [...grupo.querySelectorAll('input[type=checkbox]')];
    opcoes.forEach(opcao => opcao.addEventListener('change', () => atualizarCheckboxObrigatorio(grupo)));
    opcoes[0]?.setCustomValidity(opcoes.some(opcao => opcao.checked) ? '' : 'Selecione pelo menos uma opção.');
    opcoes[0]?.addEventListener('invalid', () => grupo.classList.add('is-invalid'));
});

document.querySelectorAll('form').forEach(formulario => formulario.addEventListener('submit', evento => {
    const invalidos = [];
    if (nome && formulario.contains(nome) && !nomeCompleto(nome.value)) invalidos.push(nome);
    if (cpf && formulario.contains(cpf) && !cpfValido(cpf.value)) invalidos.push(cpf);
    formulario.querySelectorAll('input[type=email]').forEach(campo => {
        if (campo.value.trim() !== '' && !emailValido(campo.value)) invalidos.push(campo);
    });

    if (invalidos.length === 0) return;

    evento.preventDefault();
    invalidos.forEach(campo => campo.classList.add('is-invalid'));
    invalidos[0].focus();
}));

document.getElementById('imprimirComprovante')?.addEventListener('click', () => window.print());
const distribuicaoVagas = {{ Illuminate\Support\Js::from($config['distribuicao_vagas'] ?? []) }};
const mostrarVagasRestantes = {{ !empty($config['mostrar_vagas_restantes']) ? 'true' : 'false' }};
const selectsCriterio = [...document.querySelectorAll('select[data-criterio-vagas]')];
const atualizarCotas = () => {
    const anteriores = [];
    (distribuicaoVagas.criterios || []).forEach((nome, indice) => {
        const select = selectsCriterio.find(item => item.dataset.nomeCampo === nome);
        const nivel = (distribuicaoVagas.niveis || []).find(item => item.campo === nome);
        if (!select || !nivel) return;
        const contexto = nivel.contextos?.[JSON.stringify(anteriores)];
        [...select.options].forEach(option => {
            if (!option.value) return;
            const cotas = contexto?.opcoes?.[option.value]
                ? [contexto.opcoes[option.value]]
                : Object.values(nivel.contextos || {}).map(item => item.opcoes?.[option.value]).filter(Boolean);
            const usadas = cotas.reduce((total, cota) => total + Number(cota.usadas || 0), 0);
            const restantes = cotas.reduce((total, cota) => total + Number(cota.restantes || 0), 0);
            const disponiveis = cotas.reduce((total, cota) => total + Number(cota.disponiveis || 0), 0);
            option.textContent = mostrarVagasRestantes ? `${option.dataset.texto} — ${usadas}/${restantes}` : option.dataset.texto;
            option.title = mostrarVagasRestantes ? `${usadas} vaga(s) preenchida(s) de ${disponiveis}; restam ${restantes}` : '';
            option.disabled = restantes < 1 && !option.selected;
        });
        if (select.value) anteriores.push(select.value);
    });
};
selectsCriterio.forEach(select => select.addEventListener('change', atualizarCotas));
atualizarCotas();
if (window.parent !== window && window.ResizeObserver) {
    const avisarAltura = () => {
        const altura = document.documentElement.scrollHeight;
        if (window.frameElement) window.frameElement.style.height = `${altura}px`;
        window.parent.postMessage({tipo: 'eventosgi-formulario-altura', altura}, '*');
    };
    new ResizeObserver(avisarAltura).observe(document.body);
    window.addEventListener('load', avisarAltura);
}
</script>
@endpush

@push('styles')
<style>
@media print {
    @page { size: A4 portrait; margin: 7mm; }
    body * { visibility: hidden !important; }
    #comprovanteRespostas, #comprovanteRespostas * { visibility: visible !important; }
    #comprovanteRespostas {
        position: absolute; inset: 0; width: 100%; border: 0 !important; box-shadow: none !important;
        color: #22303f; font-size: 9pt; line-height: 1.15;
    }
    #comprovanteRespostas .card-header { padding: 3mm 2mm 2mm !important; background: transparent !important; }
    #comprovanteRespostas .card-header h2 { font-size: 14pt !important; }
    #comprovanteRespostas .card-body { padding: 1.5mm 2mm 0 !important; }
    #comprovanteRespostas h3 { margin: 0 0 1.5mm !important; font-size: 8.5pt !important; }
    #comprovanteRespostas .mb-4 { margin-bottom: 2.5mm !important; }
    #comprovanteRespostas .row { --bs-gutter-x: 2.5mm; --bs-gutter-y: 1.5mm; }
    #comprovanteRespostas .col-12 { width: 50%; flex: 0 0 auto; }
    #comprovanteRespostas label { margin: 0 0 .5mm !important; font-size: 7.5pt; font-weight: 700; line-height: 1.05; }
    #comprovanteRespostas .form-control {
        min-height: 0; padding: 1mm 1.5mm; border-color: #dfe3e8; border-radius: 1.5mm;
        font-size: 8.5pt; line-height: 1.12; page-break-inside: avoid;
    }
    #comprovanteRespostas .qr-presenca { margin-top: 2.5mm !important; padding-top: 2mm !important; page-break-inside: avoid; }
    #comprovanteRespostas .qr-presenca p { margin-bottom: 1mm !important; font-size: 7.5pt !important; }
    #comprovanteRespostas .qr-presenca img { width: 42mm !important; height: 42mm !important; }
    #comprovanteRespostas .qr-presenca .font-monospace { margin-top: .5mm !important; font-size: 6.5pt !important; }
    #comprovanteRespostas.comprovante-compacto { font-size: 8pt; }
    #comprovanteRespostas.comprovante-compacto .row { --bs-gutter-y: 1mm; }
    #comprovanteRespostas.comprovante-compacto .form-control { padding: .7mm 1.2mm; font-size: 7.5pt; line-height: 1.05; }
    #comprovanteRespostas.comprovante-ultracompacto { font-size: 7pt; }
    #comprovanteRespostas.comprovante-ultracompacto .card-header { padding: 1.5mm 1mm !important; }
    #comprovanteRespostas.comprovante-ultracompacto .card-body { padding: 1mm !important; }
    #comprovanteRespostas.comprovante-ultracompacto .row { --bs-gutter-x: 1.5mm; --bs-gutter-y: .6mm; }
    #comprovanteRespostas.comprovante-ultracompacto label { font-size: 6.5pt; }
    #comprovanteRespostas.comprovante-ultracompacto .form-control { padding: .5mm 1mm; font-size: 6.8pt; line-height: 1; }
}
</style>
@endpush
