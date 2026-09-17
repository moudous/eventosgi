@extends('layouts.app')
@section('title', 'Trabalhos submetidos')
@push('styles')
<link href="https://cdn.datatables.net/2.3.2/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<style>
    #historicoModal .history-data-item { align-items: flex-start; flex-wrap: wrap; }
    #historicoModal .history-data-values { flex-wrap: wrap; justify-content: flex-start; max-width: 100%; }
    #historicoModal .history-data-value { max-width: 100%; white-space: normal; overflow-wrap: anywhere; }
    #historicoModal .history-data-toggle { display: block; text-decoration: none; }
</style>
@endpush
@section('content')
<div class="mb-4 d-flex flex-wrap justify-content-between gap-3">
    <div><h1 class="page-title">Trabalhos submetidos</h1><p class="page-description mb-0">{{ $submissao->titulo }} — {{ $submissao->evento?->nome }}</p></div>
    <div class="d-flex flex-wrap gap-2">
        @if($permissoes->permite('submissoes.inscritos.notificacoes'))
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#notificarAprovadosModal"><i class="bi bi-envelope-check me-1"></i>Notificar autores de trabalhos aprovados</button>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#notificarReprovadosModal"><i class="bi bi-envelope-x me-1"></i>Notificar reprovados</button>
        @endif
        <a href="{{ route('submissoes.index') }}" class="btn btn-outline-secondary">Voltar</a>
    </div>
</div>
<div id="actionFeedback" class="alert alert-dismissible fade d-none"><span></span><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<div class="card content-card"><div class="card-body p-0"><div class="table-responsive"><table id="inscritosTable" class="table table-hover align-middle w-100 mb-0"><thead><tr><th>ID</th><th>Título do trabalho</th><th>E-mail</th><th>Autores</th><th>Status</th><th>Situação</th><th>Alterado em</th><th data-dt-order="disable">Ações</th></tr></thead></table></div></div></div>

@if($permissoes->permite('submissoes.inscritos.notificacoes'))
    @php($podeEditarEmailsNotificacao = $permissoes->permite('submissoes.inscritos.editar_emails_notificacao'))
    @foreach(['aprovados' => ['id' => 'notificarAprovadosModal', 'titulo' => 'Notificar autores de trabalhos aprovados', 'cor' => 'success'], 'reprovados' => ['id' => 'notificarReprovadosModal', 'titulo' => 'Notificar autores de trabalhos reprovados', 'cor' => 'danger']] as $tipo => $modal)
        <div class="modal fade" id="{{ $modal['id'] }}" tabindex="-1" aria-hidden="true" data-notification-modal data-type="{{ $tipo }}" data-summary-url="{{ route('submissoes.inscritos.notificacoes.resumo', [$submissao, $tipo]) }}" data-send-url="{{ route('submissoes.inscritos.notificacoes.enviar', [$submissao, $tipo]) }}">
            <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><h2 class="modal-title fs-5">{{ $modal['titulo'] }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-secondary" data-notification-counts>Carregando contagens...</div>
                    <div data-notification-editor>
                        @if($tipo === 'aprovados')
                            <h3 class="h6 fw-bold text-success">Mensagem para os primeiros autores</h3>
                            <div class="mb-3"><label class="form-label">Assunto</label><input class="form-control" name="assunto_principal" maxlength="255" @readonly(!$podeEditarEmailsNotificacao)></div>
                            <div class="mb-4"><label class="form-label">Mensagem</label><textarea class="form-control" name="mensagem_principal" rows="10" maxlength="20000" @readonly(!$podeEditarEmailsNotificacao)></textarea></div>
                            <h3 class="h6 fw-bold text-success">Mensagem para os coautores</h3>
                            <div class="mb-3"><label class="form-label">Assunto</label><input class="form-control" name="assunto_coautor" maxlength="255" @readonly(!$podeEditarEmailsNotificacao)></div>
                            <div><label class="form-label">Mensagem</label><textarea class="form-control" name="mensagem_coautor" rows="7" maxlength="20000" @readonly(!$podeEditarEmailsNotificacao)></textarea></div>
                        @else
                            <div class="mb-3"><label class="form-label">Assunto</label><input class="form-control" name="assunto" maxlength="255" @readonly(!$podeEditarEmailsNotificacao)></div>
                            <div><label class="form-label">Mensagem</label><textarea class="form-control" name="mensagem" rows="9" maxlength="20000" @readonly(!$podeEditarEmailsNotificacao)></textarea></div>
                        @endif
                        @unless($podeEditarEmailsNotificacao)<div class="alert alert-info mt-3 mb-0"><i class="bi bi-lock me-1"></i>Você pode visualizar os modelos, mas não possui permissão para editar os e-mails de notificação.</div>@endunless
                        <div class="form-text mt-2">Variáveis disponíveis: [NOME], [TITULO_TRABALHO], [SUBMISSAO], [LINK] e [PRAZO_EPOSTER]. Cada destinatário receberá um e-mail individual.</div>
                    </div>
                    <div class="alert alert-warning d-none" data-notification-confirmation><h3 class="h6 fw-bold">Confirmar o disparo?</h3><p class="mb-0" data-confirmation-text></p></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-outline-secondary d-none" data-confirm-no>Não, revisar</button><button type="button" class="btn btn-{{ $modal['cor'] }}" data-prepare-send>Enviar para todos</button><button type="button" class="btn btn-{{ $modal['cor'] }} d-none" data-confirm-send>Sim, enviar</button></div>
            </div></div>
        </div>
    @endforeach
@endif

@include('partials.historico-modal', ['historicoUsuarioRotulo' => 'Responsável'])
@endsection
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script><script src="https://cdn.datatables.net/2.3.2/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const table=new DataTable('#inscritosTable',{processing:true,serverSide:true,order:[[0,'desc']],pageLength:@json($estadoTabela['por_pagina']),displayStart:@json(($estadoTabela['page']-1)*$estadoTabela['por_pagina']),search:{search:@json($estadoTabela['pesquisar'])},ajax:@json(route('submissoes.inscritos.dados',$submissao, false)),columns:[{data:'id'},{data:'titulo_trabalho'},{data:'email'},{data:'autores'},{data:'status'},{data:'situacao'},{data:'updated_at'},{data:'acoes',orderable:false,searchable:false}],language:{processing:'Carregando...',emptyTable:'Nenhum trabalho enviado.',info:'Exibindo _START_ a _END_ de _TOTAL_ trabalhos',infoEmpty:'Nenhum trabalho encontrado',lengthMenu:'Exibir _MENU_',search:'Pesquisar:',zeroRecords:'Nenhum trabalho encontrado.',paginate:{next:'Próxima',previous:'Anterior'}}});
    const feedback=document.getElementById('actionFeedback');
    const mostrarFeedback=(mensagem,erro=false)=>{feedback.querySelector('span').textContent=mensagem;feedback.classList.remove('d-none','alert-danger','alert-success');feedback.classList.add('show',erro?'alert-danger':'alert-success')};
    let historyTable=null;const historyModal=new bootstrap.Modal('#historicoModal');
    const historyElement = document.getElementById('historicoModal');
    const prepararDadosHistorico = () => {
        historyElement.querySelectorAll('.history-data-value:not([data-recolhivel])').forEach(valor => {
            const completo = valor.textContent;
            const caracteres = Array.from(completo);
            if (caracteres.length <= 120) return;
            valor.dataset.recolhivel = 'true';
            const resumo = caracteres.slice(0, 120).join('').trimEnd() + '...';
            const texto = document.createElement('span');
            texto.textContent = resumo;
            texto.title = 'Duplo clique para ver mais ou ver menos';
            const botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'btn btn-sm btn-link p-0 mt-1 history-data-toggle';
            botao.setAttribute('aria-expanded', 'false');
            botao.innerHTML = '<i class="bi bi-chevron-down me-1" aria-hidden="true"></i><span>Ver mais</span>';
            const alternar = () => {
                const expandido = botao.getAttribute('aria-expanded') !== 'true';
                texto.textContent = expandido ? completo : resumo;
                botao.setAttribute('aria-expanded', String(expandido));
                botao.querySelector('i').className = 'bi me-1 bi-chevron-' + (expandido ? 'up' : 'down');
                botao.querySelector('span').textContent = expandido ? 'Ver menos' : 'Ver mais';
            };
            botao.addEventListener('click', alternar);
            texto.addEventListener('dblclick', alternar);
            valor.replaceChildren(texto, botao);
        });
    };

    document.getElementById('inscritosTable').onclick=async e=>{
        const history=e.target.closest('[data-history-url]');
        if(history){document.getElementById('historicoRegistro').textContent=history.dataset.historyName;if(historyTable)historyTable.destroy();historyTable=new DataTable('#historicoTable',{processing:true,serverSide:true,searching:false,ordering:false,drawCallback:prepararDadosHistorico,ajax:history.dataset.historyUrl,columns:[{data:'numero'},{data:'historico'},{data:'usuario'},{data:'dados'},{data:'data_hora'}],language:{processing:'Carregando...',info:'Exibindo _START_ a _END_ de _TOTAL_ alterações',infoEmpty:'Nenhuma alteração',emptyTable:'Nenhuma alteração registrada.',lengthMenu:'Exibir _MENU_',paginate:{next:'Próxima',previous:'Anterior'}}});historyModal.show();return}
        const botao=e.target.closest('[data-action-url]');if(!botao)return;const definitivo=botao.dataset.action==='force-delete';const decisao=botao.dataset.decisao;const pergunta=definitivo?'Excluir este trabalho definitivamente? Os dados e autores não poderão ser recuperados.':decisao?`Deseja ${decisao} este trabalho?`:'Restaurar este trabalho para o usuário?';if(!confirm(pergunta))return;botao.disabled=true;try{const cabecalhos={Accept:'application/json','X-CSRF-TOKEN':@json(csrf_token())};if(decisao)cabecalhos['Content-Type']='application/json';const resposta=await fetch(botao.dataset.actionUrl,{method:botao.dataset.method,credentials:'same-origin',headers:cabecalhos,body:decisao?JSON.stringify({situacao:decisao}):undefined});const dados=await resposta.json();if(!resposta.ok)throw new Error(dados.message);mostrarFeedback(dados.message);table.ajax.reload(null,false)}catch(erro){mostrarFeedback(erro.message||'Falha na operação.',true);botao.disabled=false}
    };
    document.getElementById('inscritosTable').addEventListener('change',async e=>{const campo=e.target.closest('[data-status-url]');if(!campo)return;const anterior=campo.dataset.statusAtual;campo.disabled=true;try{const resposta=await fetch(campo.dataset.statusUrl,{method:'PATCH',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':@json(csrf_token())},body:JSON.stringify({status:campo.value})});const dados=await resposta.json();if(!resposta.ok)throw new Error(dados.message);campo.dataset.statusAtual=campo.value;mostrarFeedback(dados.message);table.ajax.reload(null,false)}catch(erro){campo.value=anterior;mostrarFeedback(erro.message||'Falha ao alterar o status.',true)}finally{campo.disabled=false}});

    document.querySelectorAll('[data-notification-modal]').forEach(modal=>{
        let resumo=null;
        const editor=modal.querySelector('[data-notification-editor]'),confirmacao=modal.querySelector('[data-notification-confirmation]'),preparar=modal.querySelector('[data-prepare-send]'),confirmar=modal.querySelector('[data-confirm-send]'),nao=modal.querySelector('[data-confirm-no]');
        const mostrarEditor=()=>{editor.classList.remove('d-none');confirmacao.classList.add('d-none');preparar.classList.remove('d-none');confirmar.classList.add('d-none');nao.classList.add('d-none')};
        modal.addEventListener('show.bs.modal',async()=>{mostrarEditor();modal.querySelector('[data-notification-counts]').textContent='Carregando contagens...';try{const resposta=await fetch(modal.dataset.summaryUrl,{headers:{Accept:'application/json'},credentials:'same-origin'});resumo=await resposta.json();if(!resposta.ok)throw new Error(resumo.message);modal.querySelector('[data-notification-counts]').textContent=`Pendentes: ${resumo.trabalhos} trabalho(s), ${resumo.primeiros_autores} primeiro(s) autor(es) e ${resumo.coautores} coautor(es). Destinatários já notificados para a decisão atual serão ignorados.`;Object.entries(resumo.modelos).forEach(([campo,valor])=>{const controle=modal.querySelector(`[name="${campo}"]`);if(controle)controle.value=valor});preparar.disabled=resumo.destinatarios===0}catch(erro){modal.querySelector('[data-notification-counts]').textContent=erro.message||'Não foi possível carregar as contagens.';preparar.disabled=true}});
        preparar.addEventListener('click',()=>{if(!resumo||!resumo.destinatarios)return;const vazios=Array.from(editor.querySelectorAll('input,textarea')).some(campo=>!campo.value.trim());if(vazios){mostrarFeedback('Preencha todos os assuntos e mensagens antes de continuar.',true);return}editor.classList.add('d-none');confirmacao.classList.remove('d-none');modal.querySelector('[data-confirmation-text]').textContent=`Serão considerados ${resumo.trabalhos} trabalho(s), com envio individual para ${resumo.primeiros_autores} primeiro(s) autor(es) e ${resumo.coautores} coautor(es). Deseja continuar?`;preparar.classList.add('d-none');confirmar.classList.remove('d-none');nao.classList.remove('d-none')});
        nao.addEventListener('click',mostrarEditor);
        confirmar.addEventListener('click',async()=>{confirmar.disabled=true;nao.disabled=true;const payload={};editor.querySelectorAll('input,textarea').forEach(campo=>payload[campo.name]=campo.value);try{const resposta=await fetch(modal.dataset.sendUrl,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':@json(csrf_token())},body:JSON.stringify(payload)});const dados=await resposta.json();if(!resposta.ok)throw new Error(dados.message||Object.values(dados.errors||{}).flat().join(' '));bootstrap.Modal.getInstance(modal).hide();mostrarFeedback(dados.message);table.ajax.reload(null,false)}catch(erro){mostrarFeedback(erro.message||'Falha ao enviar as notificações.',true)}finally{confirmar.disabled=false;nao.disabled=false}});
    });
});
</script>
@endpush
