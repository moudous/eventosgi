@php
    $acao = $novoTrabalho ? route('submissoes.publicas.criar', $submissao) : route('submissoes.publicas.atualizar', [$submissao, $trabalho]);
    $conteudoInicial = old('conteudo', $novoTrabalho ? ($submissao->modelo_trabalho ?? '') : ($trabalho->conteudo ?? ''));
    $primeiroInicial = old('primeiro_autor', $novoTrabalho ? '' : ($primeiro?->nome ?? ''));
    $primeiroAfiliacao = old('primeiro_autor_afiliacao', $novoTrabalho ? '' : ($primeiro?->afiliacao ?? ''));
    $afiliacaoPlaceholder = 'Doutora em Ciências da Saúde. Professora da Faculdade de Ciências Odontológicas';
    $outrosIniciais = old('outros_autores');
    if (! is_array($outrosIniciais)) {
        $outrosIniciais = $novoTrabalho
            ? []
            : $trabalho->autores->where('principal', false)->map(fn ($autor) => [
                'nome' => $autor->nome,
                'email' => $autor->email ?? '',
                'afiliacao' => $autor->afiliacao ?? '',
            ])->values()->all();
    } else {
        $outrosIniciais = array_values($outrosIniciais);
    }
    $temApoio = (int) old('tem_apoio_financeiro', $novoTrabalho ? 0 : (int) $trabalho->tem_apoio_financeiro);
    $apresentacao = old('apresentacao', $novoTrabalho ? 'presencial' : ($trabalho->apresentacao ?? 'presencial'));
    $aprovacaoEtica = (int) old('aprovacao_comite_etica', $novoTrabalho ? 0 : (int) $trabalho->aprovacao_comite_etica);
@endphp
<form id="trabalhoForm" method="POST" action="{{ $acao }}">@csrf @if(!$novoTrabalho)@method('PUT')@endif
@if($novoTrabalho)<div class="isca" aria-hidden="true"><label>Não preencha<input name="website_trabalho" tabindex="-1" autocomplete="off"></label></div>@endif
<div class="card submissao-card mb-4">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h2 class="h5 mb-0">{{ $novoTrabalho ? 'Adicionar trabalho' : 'Editar trabalho' }}</h2>
        @if(!$novoTrabalho)<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#alterarSenhaModal"><i class="bi bi-key me-1"></i>Alterar senha</button>@endif
    </div>
    <div class="card-body p-4"><div class="row g-4">
        <div class="col-12"><label class="form-label fw-semibold" for="titulo_trabalho">Título do trabalho / resumo *</label><input class="form-control" id="titulo_trabalho" name="titulo_trabalho" maxlength="120" required value="{{ old('titulo_trabalho', $novoTrabalho ? '' : $trabalho->titulo_trabalho) }}"><div class="form-text">Máximo de 120 caracteres.</div></div>
        <div class="col-12 col-md-6"><label class="form-label fw-semibold" for="email">E-mail do primeiro autor *</label><input type="email" class="form-control" id="email" name="email" required autocomplete="email" value="{{ old('email', $primeiroCadastro ? '' : $inscricao?->email) }}" @readonly(!$primeiroCadastro)></div>
        @if($primeiroCadastro)<div class="col-12 col-md-3"><label class="form-label fw-semibold" for="senha">Senha *</label><input type="password" class="form-control" id="senha" name="senha" minlength="8" required autocomplete="new-password"><div class="form-text">Mínimo de 8 caracteres, com letras e números.</div></div><div class="col-12 col-md-3"><label class="form-label fw-semibold" for="senha_confirmation">Confirmar senha *</label><input type="password" class="form-control" id="senha_confirmation" name="senha_confirmation" minlength="8" required autocomplete="new-password"></div>@endif

        <div class="col-12 col-md-6"><label class="form-label fw-semibold" for="primeiro_autor">Primeiro autor *</label><input class="form-control" id="primeiro_autor" name="primeiro_autor" maxlength="255" required value="{{ $primeiroInicial }}"><div class="form-text">Informe nome e sobrenome.</div></div>
        <div class="col-12 col-md-6"><label class="form-label fw-semibold" for="primeiro_autor_afiliacao">Afiliação do primeiro autor *</label><input class="form-control" id="primeiro_autor_afiliacao" name="primeiro_autor_afiliacao" maxlength="1000" required placeholder="{{ $afiliacaoPlaceholder }}" value="{{ $primeiroAfiliacao }}"></div>

        <div class="col-12">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div><h3 class="h6 fw-bold mb-1">Outros autores</h3><div id="limiteAutoresTexto" class="text-secondary small"></div></div>
                <button type="button" id="adicionarAutor" class="btn btn-sm btn-outline-primary"><i class="bi bi-person-plus me-1"></i>Adicionar autor</button>
            </div>
            <div id="listaAutores" class="d-grid gap-3"></div>
        </div>

        <div class="col-12">
            <div class="border rounded bg-light p-3">
                <h3 class="h6 fw-bold mb-3">Visualização dos autores</h3>
                <div id="visualizacaoNomes" class="mb-3"></div>
                <div id="visualizacaoAfiliacoes" class="small"></div>
            </div>
        </div>

        <div class="col-12 col-md-4"><label class="form-label fw-semibold" for="tem_apoio_financeiro">Tem apoio financeiro? *</label><select class="form-select" id="tem_apoio_financeiro" name="tem_apoio_financeiro" required><option value="0" @selected($temApoio === 0)>Não</option><option value="1" @selected($temApoio === 1)>Sim</option></select></div>
        <div id="campoApoiador" class="col-12 col-md-8 {{ $temApoio === 1 ? '' : 'd-none' }}"><label class="form-label fw-semibold" for="apoiador">Apoiador *</label><input class="form-control" id="apoiador" name="apoiador" maxlength="1000" placeholder="Fundação de Apoio à Pesquisa de Minas Gerais." value="{{ old('apoiador', $novoTrabalho ? '' : $trabalho->apoiador) }}" @required($temApoio === 1)></div>
        <div class="col-12 col-md-4"><label class="form-label fw-semibold" for="apresentacao">Apresentação *</label><select class="form-select" id="apresentacao" name="apresentacao" required><option value="online" @selected($apresentacao === 'online')>Online</option><option value="presencial" @selected($apresentacao === 'presencial')>Presencial</option></select></div>
        <div class="col-12 col-md-4"><label class="form-label fw-semibold" for="aprovacao_comite_etica">Aprovação do Comitê de Ética? *</label><select class="form-select" id="aprovacao_comite_etica" name="aprovacao_comite_etica" required><option value="0" @selected($aprovacaoEtica === 0)>Não</option><option value="1" @selected($aprovacaoEtica === 1)>Sim</option></select></div>
        <div id="campoProtocoloEtica" class="col-12 col-md-4 {{ $aprovacaoEtica === 1 ? '' : 'd-none' }}"><label class="form-label fw-semibold" for="protocolo_comite_etica">Protocolo do Comitê de Ética *</label><input class="form-control" id="protocolo_comite_etica" name="protocolo_comite_etica" maxlength="500" value="{{ old('protocolo_comite_etica', $novoTrabalho ? '' : $trabalho->protocolo_comite_etica) }}" @required($aprovacaoEtica === 1)></div>

        <div class="col-12 mb-3"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><div class="d-flex flex-wrap align-items-center gap-2"><label class="form-label fw-semibold mb-0">Resumo</label><span id="contadorResumo" class="badge text-bg-secondary" aria-live="polite">0 caracteres digitados · {{ $submissao->qtde_resumo }} restantes</span></div>@if(!$novoTrabalho)<a class="btn btn-sm btn-outline-primary" href="{{ route('submissoes.publicas.exportar-documento', [$submissao, $trabalho]) }}"><i class="bi bi-file-earmark-word me-1"></i>Exportar documento</a>@else<button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Salve o trabalho antes de exportar"><i class="bi bi-file-earmark-word me-1"></i>Exportar documento</button>@endif</div><input type="hidden" id="conteudo" name="conteudo"><div id="conteudoEditor" class="modelo-editor bg-white">{!! $conteudoInicial !!}</div></div>
    </div></div>
</div>
<div class="d-flex justify-content-end"><button class="btn btn-primary px-4"><i class="bi bi-check-lg me-1"></i>Salvar trabalho</button></div>
</form>
@if(!$novoTrabalho)<div class="modal fade" id="alterarSenhaModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('submissoes.publicas.senha', [$submissao, $trabalho]) }}">@csrf @method('PATCH')<div class="modal-header"><h2 class="modal-title fs-5">Alterar senha</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Senha atual</label><input type="password" class="form-control" name="senha_atual" required autocomplete="current-password"></div><div class="mb-3"><label class="form-label">Nova senha</label><input type="password" class="form-control" name="senha" minlength="8" required autocomplete="new-password"></div><div><label class="form-label">Confirmar nova senha</label><input type="password" class="form-control" name="senha_confirmation" minlength="8" required autocomplete="new-password"></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Alterar senha</button></div></form></div></div></div>@endif

<script type="application/json" id="autoresIniciais">@json($outrosIniciais)</script>
