@extends('layouts.app')
@section('title', $submissao->titulo)
@push('styles')
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<style>
body{background:#f3f6fa}.submissao-publica{max-width:1120px;margin:0 auto;padding:28px 16px 60px}.submissao-hero{background:linear-gradient(135deg,#102a43,#176b87);color:#fff;border-radius:1rem;padding:30px}.submissao-hero h1{font-size:clamp(1.6rem,4vw,2.4rem)}.submissao-card{border:0;box-shadow:0 8px 26px rgba(16,42,67,.08)}.autor-linha{border:1px solid #dbe4ec;border-radius:.75rem;padding:1rem;background:#fff}.trabalho-opcao{border:1px solid #dbe4ec;border-radius:.75rem;padding:1rem}.modelo-editor.ql-container{height:480px}.conteudo-leitura{min-height:240px;background:#fff}.conteudo-leitura .ql-align-center{text-align:center}.conteudo-leitura .ql-align-right{text-align:right}.conteudo-leitura .ql-align-justify{text-align:justify}.isca{position:absolute!important;left:-10000px!important;width:1px!important;height:1px!important;overflow:hidden!important}.prazo-alerta{border-left:5px solid #0d6efd}.status-avaliado{border-left:5px solid #198754}
</style>
@endpush
@section('content')
@php
    $primeiro = $trabalho?->autores?->firstWhere('principal', true);
    $outros = $trabalho?->autores?->where('principal', false) ?? collect();
    $podeEditar = $trabalho && $submissao->aberta() && $trabalho->status === 'rascunho';
@endphp
<div class="submissao-publica">
    @php($eventoVisual = $submissao->evento ?? new \App\Models\Evento)
    <header class="submissao-hero mb-4" style="background: {{ $eventoVisual->fundoFormulario('submissao') }}; color: {{ $eventoVisual->estiloFormulario('submissao')['cor_fonte'] }};"><div class="small text-uppercase opacity-75 fw-bold mb-2">{{ $submissao->evento?->nome }}</div><h1 class="mb-2">{{ $submissao->titulo }}</h1><div>Período: {{ $submissao->data_inicio?->format('d/m/Y H:i') }} até {{ $submissao->data_fim?->format('d/m/Y H:i') }}</div>@if($submissao->evento?->ativo)<a href="{{ route('eventos.pagina.visualizar',$submissao->evento) }}" class="btn btn-light btn-sm mt-3"><i class="bi bi-arrow-left me-1"></i>Voltar para a página do evento</a>@endif</header>

    @if(session('status'))<div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
    @if(session('recuperacao'))<div class="alert alert-info alert-dismissible fade show">{{ session('recuperacao') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif

    @if(!$submissao->ativo || !$submissao->evento?->ativo)
        <div class="alert alert-secondary">Esta submissão não está disponível.</div>
    @elseif($submissao->aindaNaoAbriu())
        <div class="alert alert-info">O período de submissão começará em <strong>{{ $submissao->data_inicio?->format('d/m/Y H:i') }}</strong>.</div>
    @else
        @if($submissao->encerrada())<div class="alert alert-warning"><strong>O período de submissão foi finalizado.</strong> Aguarde a divulgação dos resultados.</div>@endif

        @if(!$acesso && !$primeiroCadastro)
        <div class="card submissao-card mb-4"><div class="card-header bg-white"><h2 class="h5 mb-0">Já cadastrou um trabalho?</h2></div><div class="card-body p-4">
            <form method="POST" action="{{ route('submissoes.publicas.entrar',$submissao) }}">@csrf<div class="row g-3 align-items-end"><div class="col-12 col-md-5"><label class="form-label" for="email_login">E-mail</label><input type="email" class="form-control" id="email_login" name="email_login" required autocomplete="email" value="{{ old('email_login') }}"></div><div class="col-12 col-md-4"><label class="form-label" for="senha_login">Senha</label><input type="password" class="form-control" id="senha_login" name="senha_login" required autocomplete="current-password"></div><div class="col-12 col-md-3 d-grid"><button class="btn btn-primary">Entrar</button></div></div></form>
            <div class="d-flex flex-wrap gap-3 mt-3"><button type="button" class="btn btn-link p-0" data-bs-toggle="modal" data-bs-target="#recuperarSenhaModal">Esqueci minha senha</button>@if($submissao->aberta())<a class="btn btn-link p-0 fw-semibold" href="{{ route('submissoes.publicas.formulario',$submissao) }}?cadastro=1"><i class="bi bi-plus-circle me-1"></i>Cadastrar novo trabalho</a>@endif</div>
        </div></div>
        @elseif($acesso)
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <form method="POST" action="{{ route('submissoes.publicas.selecionar',$submissao) }}" class="flex-grow-1" style="max-width:620px">@csrf<label for="trabalho_id" class="form-label fw-semibold mb-1">Trabalho selecionado</label><select id="trabalho_id" name="trabalho_id" class="form-select" onchange="this.form.submit()">@foreach($trabalhos as $item)<option value="{{ $item->id }}" @selected($trabalho?->id===$item->id)>{{ $item->titulo_trabalho }}</option>@endforeach</select></form>
            <div class="d-flex flex-wrap gap-2">@if($trabalho && $podeEditar && !$novo)<button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#excluirTrabalhoModal"><i class="bi bi-trash me-1"></i>Excluir trabalho</button>@endif @if($submissao->aberta())<a href="{{ route('submissoes.publicas.formulario',$submissao) }}?novo=1" class="btn btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>Novo trabalho</a>@endif<form method="POST" action="{{ route('submissoes.publicas.sair',$submissao) }}">@csrf<button class="btn btn-outline-secondary">Sair</button></form></div>
        </div>
        @elseif($primeiroCadastro)
        <div class="mb-4"><a class="btn btn-outline-secondary btn-sm" href="{{ route('submissoes.publicas.formulario',$submissao) }}"><i class="bi bi-arrow-left me-1"></i>Voltar para o acesso</a></div>
        @endif

        @if($acesso && !$trabalho && !$novo)
        <div class="alert alert-info">Selecione acima o trabalho que deseja consultar ou editar.</div>
        @endif

        @if($trabalho && !$novo)
            @if($trabalho->avaliada())
                <div class="alert alert-success status-avaliado"><h2 class="h5">Trabalho avaliado</h2><div class="row g-2"><div class="col-md-4"><strong>Nota:</strong> {{ $trabalho->nota ?? 'Não informada' }}</div><div class="col-md-8"><strong>Situação:</strong> {{ $trabalho->situacao ?: 'Não informada' }}</div></div></div>
            @elseif($submissao->encerrada())
                <div class="alert alert-warning prazo-alerta"><strong>O período de submissão foi finalizado.</strong> O trabalho foi encaminhado à comissão responsável. Aguarde a divulgação dos resultados.</div>
            @else
                <div class="alert alert-primary prazo-alerta">Você poderá editar até o dia <strong>{{ $submissao->data_fim?->format('d/m/Y H:i') }}</strong>. Depois desta data e hora, o trabalho será submetido à avaliação da comissão responsável.</div>
            @endif

            @if($podeEditar)
                @include('submissoes.partials.formulario-trabalho',['novoTrabalho'=>false])
            @else
                <div class="card submissao-card"><div class="card-header bg-white d-flex justify-content-between align-items-center gap-2"><h2 class="h5 mb-0">{{ $trabalho->titulo_trabalho }}</h2><div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="{{ route('submissoes.publicas.exportar-documento', [$submissao, $trabalho]) }}"><i class="bi bi-file-earmark-word me-1"></i>Exportar documento</a><span class="badge text-bg-{{ $trabalho->avaliada()?'success':'info' }}">{{ $trabalho->avaliada()?'Avaliado':'Submetido' }}</span></div></div><div class="card-body p-4"><dl class="row"><dt class="col-sm-3">E-mail do primeiro autor</dt><dd class="col-sm-9">{{ $inscricao->email }}</dd><dt class="col-sm-3">Apresentação</dt><dd class="col-sm-9">{{ ucfirst($trabalho->apresentacao ?? 'presencial') }}</dd><dt class="col-sm-3">Apoio financeiro</dt><dd class="col-sm-9">{{ $trabalho->tem_apoio_financeiro ? ($trabalho->apoiador ?: 'Sim') : 'Não' }}</dd><dt class="col-sm-3">Aprovação do Comitê de Ética</dt><dd class="col-sm-9">{{ $trabalho->aprovacao_comite_etica ? 'Sim — Protocolo: '.($trabalho->protocolo_comite_etica ?: 'não informado') : 'Não' }}</dd></dl><div class="border rounded bg-light p-3 mb-4"><h3 class="h6 fw-bold">Autores</h3><div class="mb-3">@foreach($trabalho->autores as $autor){{ !$loop->first ? '; ' : '' }}{{ $autor->nome }}<sup>{{ $autor->numero ?? $autor->ordem }}</sup>@endforeach</div>@foreach($trabalho->autores as $autor)<div class="small"><sup>{{ $autor->numero ?? $autor->ordem }}</sup> {{ $autor->afiliacao ?: 'Afiliação não informada' }}</div>@endforeach</div><h3 class="h6 fw-bold">Resumo</h3><div class="border rounded p-4 conteudo-leitura">{!! $trabalho->conteudo ?: '<span class="text-secondary">Sem conteúdo.</span>' !!}</div></div></div>
            @endif
        @elseif($novo && $submissao->aberta())
            @include('submissoes.partials.formulario-trabalho',['novoTrabalho'=>true])
        @endif
    @endif
</div>

@if($trabalho && $podeEditar && !$novo)
<div class="modal fade" id="excluirTrabalhoModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('submissoes.publicas.excluir',[$submissao,$trabalho]) }}">@csrf @method('DELETE')<div class="modal-header"><h2 class="modal-title fs-5">Excluir trabalho</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>Você está prestes a apagar o trabalho:</p><p class="fw-bold mb-1">{{ $trabalho->titulo_trabalho }}</p><p class="text-secondary">Autores: {{ $trabalho->autores->pluck('nome')->implode(', ') }}</p><div class="alert alert-danger mb-0"><strong>O trabalho não poderá ser recuperado por você.</strong> Somente um administrador poderá restaurá-lo. Tem certeza?</div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Não</button><button type="submit" class="btn btn-danger">Sim, apagar trabalho</button></div></form></div></div></div>
@endif

<div class="modal fade" id="recuperarSenhaModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('submissoes.publicas.esqueci-senha',$submissao) }}">@csrf<div class="modal-header"><h2 class="modal-title fs-5">Recuperar senha</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>Informe o e-mail usado nos trabalhos. Uma senha temporária será enviada se o endereço estiver cadastrado.</p><label class="form-label" for="email_recuperacao">E-mail</label><input type="email" class="form-control" id="email_recuperacao" name="email_recuperacao" required value="{{ old('email_recuperacao') }}"><div class="mt-3"><label class="form-label" for="captcha_recuperacao">Digite o texto da imagem</label><div class="d-flex align-items-center gap-2"><img id="captchaRecuperacaoImagem" src="{{ route('submissoes.publicas.captcha', $submissao) }}" width="220" height="70" class="border rounded" alt="Imagem com seis caracteres para confirmação"><button type="button" class="btn btn-outline-secondary" id="renovarCaptchaRecuperacao" title="Gerar nova imagem"><i class="bi bi-arrow-clockwise"></i></button></div><input class="form-control mt-2" style="max-width:220px;text-transform:uppercase;letter-spacing:.2em" type="text" id="captcha_recuperacao" name="captcha" maxlength="6" required autocomplete="off" autocapitalize="characters">@if($errors->has('captcha'))<div class="text-danger small mt-1">{{ $errors->first('captcha') }}</div>@endif</div><div class="isca" aria-hidden="true"><label>Não preencha<input name="website" tabindex="-1" autocomplete="off"></label></div><div class="form-text">Por segurança, existem limites por e-mail, navegador e rede.</div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Enviar nova senha</button></div></form></div></div></div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
const editor=document.getElementById('conteudoEditor'),form=document.getElementById('trabalhoForm');let quill=null;
if(editor&&form){
    const limiteResumo=@json((int) $submissao->qtde_resumo);
    const contador=document.getElementById('contadorResumo');
    let ajustandoLimite=false;
    quill=new Quill(editor,{theme:'snow',modules:{toolbar:[[{header:[1,2,3,4,false]}],['bold','italic','underline','strike'],[{align:[]}],[{list:'ordered'},{list:'bullet'}],['blockquote'],['clean']]}});
    const atualizarResumo=()=>{
        let digitados=Math.max(0,quill.getLength()-1);
        if(digitados>limiteResumo&&!ajustandoLimite){
            ajustandoLimite=true;
            quill.deleteText(limiteResumo,digitados-limiteResumo,'silent');
            ajustandoLimite=false;
            digitados=Math.max(0,quill.getLength()-1);
        }
        const restantes=Math.max(0,limiteResumo-digitados);
        contador.textContent=`${digitados} ${digitados===1?'caractere digitado':'caracteres digitados'} · ${restantes} ${restantes===1?'restante':'restantes'}`;
        contador.classList.toggle('text-bg-secondary',restantes>0);
        contador.classList.toggle('text-bg-danger',restantes===0);
    };
    quill.on('text-change',atualizarResumo);
    atualizarResumo();
    form.addEventListener('submit',()=>{
        atualizarResumo();
        document.getElementById('conteudo').value=quill.root.innerHTML;
    });
}
const listaAutores=document.getElementById('listaAutores');
if(listaAutores){
    const limiteAutores=@json((int) $submissao->qtde_autores);
    const adicionar=document.getElementById('adicionarAutor');
    const limiteTexto=document.getElementById('limiteAutoresTexto');
    const primeiroNome=document.getElementById('primeiro_autor');
    const primeiraAfiliacao=document.getElementById('primeiro_autor_afiliacao');
    const visualizacaoNomes=document.getElementById('visualizacaoNomes');
    const visualizacaoAfiliacoes=document.getElementById('visualizacaoAfiliacoes');
    const placeholderAfiliacao=@json('Doutora em Ciências da Saúde. Professora da Faculdade de Ciências Odontológicas');
    let proximaChave=0;

    function linhas(){return Array.from(listaAutores.querySelectorAll('.autor-linha'))}
    function autoresAtuais(){
        return [{nome:primeiroNome.value.trim(),afiliacao:primeiraAfiliacao.value.trim()}].concat(linhas().map(linha=>({
            nome:linha.querySelector('[data-campo="nome"]').value.trim(),
            afiliacao:linha.querySelector('[data-campo="afiliacao"]').value.trim()
        })));
    }
    function atualizarVisualizacao(){
        const autores=autoresAtuais();
        visualizacaoNomes.replaceChildren();
        autores.forEach((autor,indice)=>{
            if(indice)visualizacaoNomes.append(document.createTextNode('; '));
            visualizacaoNomes.append(document.createTextNode(autor.nome||`Autor ${indice+1}`));
            const numero=document.createElement('sup');numero.textContent=String(indice+1);visualizacaoNomes.append(numero);
        });
        visualizacaoAfiliacoes.replaceChildren();
        autores.forEach((autor,indice)=>{
            const linha=document.createElement('div');
            const numero=document.createElement('sup');numero.textContent=String(indice+1);linha.append(numero,document.createTextNode(' '+(autor.afiliacao||'Afiliação não informada')));
            visualizacaoAfiliacoes.append(linha);
        });
        const total=autores.length;
        limiteTexto.textContent=`${total} de ${limiteAutores} autores utilizados.`;
        adicionar.disabled=total>=limiteAutores;
        linhas().forEach((linha,indice)=>{
            linha.querySelector('[data-acao="subir"]').disabled=indice===0;
            linha.querySelector('[data-acao="descer"]').disabled=indice===linhas().length-1;
            linha.querySelector('[data-numero]').textContent=String(indice+2);
        });
    }
    function criarLinha(autor={}){
        if(1+linhas().length>=limiteAutores)return;
        const chave=proximaChave++;
        const linha=document.createElement('div');linha.className='autor-linha';
        linha.innerHTML=`<div class="d-flex justify-content-between align-items-center gap-2 mb-3"><strong>Autor <span data-numero></span></strong><div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary" data-acao="subir" title="Subir autor"><i class="bi bi-arrow-up"></i></button><button type="button" class="btn btn-outline-secondary" data-acao="descer" title="Descer autor"><i class="bi bi-arrow-down"></i></button><button type="button" class="btn btn-outline-danger" data-acao="remover" title="Remover autor"><i class="bi bi-trash"></i></button></div></div><div class="row g-3"><div class="col-12 col-lg-4"><label class="form-label">Nome completo *</label><input class="form-control" data-campo="nome" name="outros_autores[${chave}][nome]" maxlength="255" required></div><div class="col-12 col-lg-3"><label class="form-label">E-mail *</label><input type="email" class="form-control" data-campo="email" name="outros_autores[${chave}][email]" maxlength="150" required></div><div class="col-12 col-lg-5"><label class="form-label">Afiliação *</label><input class="form-control" data-campo="afiliacao" name="outros_autores[${chave}][afiliacao]" maxlength="1000" required></div></div>`;
        linha.querySelector('[data-campo="nome"]').value=autor.nome||'';
        linha.querySelector('[data-campo="email"]').value=autor.email||'';
        const campoAfiliacao=linha.querySelector('[data-campo="afiliacao"]');campoAfiliacao.value=autor.afiliacao||'';campoAfiliacao.placeholder=placeholderAfiliacao;
        linha.addEventListener('input',atualizarVisualizacao);
        linha.addEventListener('click',evento=>{
            const botao=evento.target.closest('[data-acao]');if(!botao)return;
            const acao=botao.dataset.acao;
            if(acao==='remover')linha.remove();
            if(acao==='subir'&&linha.previousElementSibling)listaAutores.insertBefore(linha,linha.previousElementSibling);
            if(acao==='descer'&&linha.nextElementSibling)listaAutores.insertBefore(linha.nextElementSibling,linha);
            atualizarVisualizacao();
        });
        listaAutores.append(linha);atualizarVisualizacao();
    }
    adicionar.addEventListener('click',()=>criarLinha());
    primeiroNome.addEventListener('input',atualizarVisualizacao);
    primeiraAfiliacao.addEventListener('input',atualizarVisualizacao);
    let iniciais=[];try{iniciais=JSON.parse(document.getElementById('autoresIniciais').textContent)}catch(erro){}
    iniciais.slice(0,Math.max(0,limiteAutores-1)).forEach(criarLinha);
    atualizarVisualizacao();
}
const temApoio=document.getElementById('tem_apoio_financeiro'),campoApoiador=document.getElementById('campoApoiador'),apoiador=document.getElementById('apoiador');
if(temApoio){const alternarApoio=()=>{const possui=temApoio.value==='1';campoApoiador.classList.toggle('d-none',!possui);apoiador.required=possui;if(!possui)apoiador.value=''};temApoio.addEventListener('change',alternarApoio);alternarApoio()}
const aprovacaoEtica=document.getElementById('aprovacao_comite_etica'),campoProtocoloEtica=document.getElementById('campoProtocoloEtica'),protocoloEtica=document.getElementById('protocolo_comite_etica');
if(aprovacaoEtica){const alternarEtica=()=>{const aprovada=aprovacaoEtica.value==='1';campoProtocoloEtica.classList.toggle('d-none',!aprovada);protocoloEtica.required=aprovada;if(!aprovada)protocoloEtica.value=''};aprovacaoEtica.addEventListener('change',alternarEtica);alternarEtica()}
document.getElementById('renovarCaptchaRecuperacao')?.addEventListener('click',()=>{document.getElementById('captchaRecuperacaoImagem').src=@json(route('submissoes.publicas.captcha',$submissao))+'?novo=1&t='+Date.now();document.getElementById('captcha_recuperacao').value='';});
@if($errors->has('email_recuperacao') || $errors->has('website') || $errors->has('captcha')) new bootstrap.Modal('#recuperarSenhaModal').show(); @endif
});
</script>
@endpush
