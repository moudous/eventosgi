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

    @if(session('status'))<div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>{{ session('status') }}</div>@endif
    @if(session('vagas_esgotadas'))<div class="alert alert-warning">{{ session('vagas_esgotadas') }}</div>@endif
    @if(session('identificacao_expirada'))<div class="alert alert-warning">{{ session('identificacao_expirada') }}</div>@endif

    @if(! $aberto)
        <div class="alert {{ $estado['motivo'] === 'antes' ? 'alert-info' : 'alert-warning' }}">
            @if($estado['motivo'] === 'duplicada')<i class="bi bi-person-check me-1"></i>@endif{{ $estado['mensagem'] }}
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
                    {{-- Selo do momento em que esta tela foi montada: assinado, então
                         quem faz POST direto no endereço não consegue produzir um. --}}
                    <input type="hidden" name="{{ \App\Services\IdentificacaoParticipanteService::CAMPO_SELO }}" value="{{ $selo }}">
                    {{-- Campo isca: fica fora da tela, então só um robô o preenche. --}}
                    <div class="campo-isca" aria-hidden="true">
                        <label>Deixe este campo em branco
                            <input type="text" name="{{ \App\Services\IdentificacaoParticipanteService::CAMPO_ISCA }}" value="" tabindex="-1" autocomplete="off">
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">E-mail *</label>
                        <input class="form-control @if($errosIdentificacao->has('email')) is-invalid @endif" type="email" id="email" name="email" maxlength="150" required autocomplete="email" placeholder="voce@exemplo.com" value="{{ $emailInformado }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="senha">Senha de inscrição</label>
                        <div class="input-group">
                            <input class="form-control @if($errosIdentificacao->has('senha')) is-invalid @endif" type="password" id="senha" name="senha" maxlength="200" autocomplete="current-password">
                            <button class="btn btn-primary" type="submit" name="acao" value="validar_senha"><i class="bi bi-key me-1"></i>Entrar com senha</button>
                        </div>
                        @if($errosIdentificacao->has('senha'))<div class="text-danger small mt-1">{{ $errosIdentificacao->first('senha') }}</div>@endif
                    </div>
                    <div class="d-flex align-items-center gap-3 my-4"><hr class="flex-grow-1 m-0"><span class="text-muted small text-center">Esqueceu ou ainda não tem uma senha?</span><hr class="flex-grow-1 m-0"></div>
                    <p class="text-muted small">Receba um código temporário por e-mail. A mensagem também terá um link para você definir uma senha.</p>
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

        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between alert alert-light border">
            <span><i class="bi bi-envelope-check me-1"></i>Inscrevendo com <strong>{{ $identificacao['email'] }}</strong></span>
            <form method="POST" action="{{ request()->fullUrl() }}" class="m-0">
                @csrf<input type="hidden" name="acao" value="trocar_email">
                <button class="btn btn-sm btn-outline-secondary" type="submit">Usar outro e-mail</button>
            </form>
        </div>

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
                            @php $sexo = old('participante.sexo', $participante->sexo); @endphp
                            <select class="form-select" id="participante_sexo" name="participante[sexo]">
                                <option value="">Não informado</option>
                                <option value="M" @selected($sexo === 'M')>Masculino</option>
                                <option value="F" @selected($sexo === 'F')>Feminino</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label" for="participante_grupo">Grupo</label>
                            <input class="form-control text-uppercase" id="participante_grupo" name="participante[grupo]" maxlength="1" value="{{ old('participante.grupo', $participante->grupo) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card content-card">
                <div class="card-body p-4">
                    @if(!empty($config['editor']['conteudo']) && in_array($config['editor']['posicao'] ?? '', ['acima', 'esquerda']))
                        <div class="mb-3">{!! $config['editor']['conteudo'] !!}</div>
                    @endif

                    <div class="row g-3">
                        @foreach($config['campos'] ?? [] as $campo)
                            @php
                                $nome = $campo['nome'];
                                $tipo = $campo['tipo'] ?? 'text';
                                $multiplo = $tipo === 'multiselect';
                                $anterior = old($nome);
                            @endphp
                            <div class="col-md-6">
                                <label class="form-label" for="campo_{{ $loop->index }}">{{ $campo['label'] ?? $nome }} @if(!empty($campo['obrigatorio']))*@endif</label>
                                @if(in_array($tipo, ['select', 'radio', 'checkbox', 'multiselect']))
                                    <select class="form-select" id="campo_{{ $loop->index }}" name="{{ $nome }}{{ $multiplo ? '[]' : '' }}" @if(!empty($campo['obrigatorio'])) required @endif @if($multiplo) multiple @endif>
                                        @foreach($campo['opcoes'] ?? [] as $opcao)
                                            <option value="{{ $opcao }}" @selected(is_array($anterior) ? in_array($opcao, $anterior) : $anterior === $opcao)>{{ $opcao }}</option>
                                        @endforeach
                                    </select>
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

                    @if(!empty($config['editor']['conteudo']) && in_array($config['editor']['posicao'] ?? '', ['abaixo', 'direita']))
                        <div class="mt-3">{!! $config['editor']['conteudo'] !!}</div>
                    @endif

                    <button class="btn btn-primary mt-4"><i class="bi bi-send me-1"></i>Enviar inscrição</button>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection

@push('styles')
<style>
    /* Fora da tela em vez de display:none, para o robô continuar preenchendo. */
    .campo-isca { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
</style>
@endpush

@push('scripts')
<script>
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
</script>
@endpush
