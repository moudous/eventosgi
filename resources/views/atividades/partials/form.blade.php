@php
    $tipoAtividade = old('tipo', $atividade?->tipo ?? 'somente_inscricao');
    $formatoAtividade = old('formato', $atividade?->formato ?? 'simples');
    $sessoesAtividadeIniciais = old('sessoes', $atividade?->sessoes?->map(fn ($sessao) => [
        'id' => $sessao->id,
        'nome' => $sessao->nome,
        'data_inicio' => $sessao->data_inicio?->format('Y-m-d\TH:i'),
        'data_fim' => $sessao->data_fim?->format('Y-m-d\TH:i'),
        'limite_vagas' => $sessao->limite_vagas,
    ])->values()->all() ?? []);
@endphp
<div class="card content-card"><div class="card-header"><h2 class="h5 fw-bold mb-0">Dados da atividade</h2></div><div class="card-body p-4"><div class="row g-4">
<div class="col-12 col-lg-3"><label class="form-label fw-semibold" for="tipo_atividade">Tipo</label>
<select class="form-select @error('tipo') is-invalid @enderror" id="tipo_atividade" name="tipo" required>
<option value="somente_inscricao" @selected($tipoAtividade === 'somente_inscricao')>Somente inscrição</option>
<option value="atividade_evento" @selected($tipoAtividade === 'atividade_evento')>Atividade do Evento</option>
</select>@error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
<div class="col-12 col-lg-3"><label class="form-label fw-semibold" for="formato_atividade">Formato *</label><select class="form-select @error('formato') is-invalid @enderror" id="formato_atividade" name="formato" required><option value="simples" @selected($formatoAtividade === 'simples')>Atividade simples</option><option value="com_sessoes" @selected($formatoAtividade === 'com_sessoes')>Atividade com sessões</option></select>@error('formato')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
<div class="col-12 col-lg-6"><label class="form-label fw-semibold" for="nome">Nome *</label><input class="form-control @error('nome') is-invalid @enderror" id="nome" name="nome" maxlength="255" required value="{{old('nome',$atividade?->nome)}}">@error('nome')<div class="invalid-feedback">{{$message}}</div>@enderror</div>
<div class="col-12 col-lg-4"><label class="form-label fw-semibold" for="evento_id">Evento *</label><select class="form-select @error('evento_id') is-invalid @enderror" id="evento_id" name="evento_id" required><option value=""></option>@foreach($eventos as $evento)<option value="{{$evento->id}}" @selected((int)old('evento_id',$atividade?->evento_id)===$evento->id)>{{$evento->nome}}</option>@endforeach</select>@error('evento_id')<div class="invalid-feedback">{{$message}}</div>@enderror</div>
<div class="col-12 col-lg-4" data-campo-atividade @if($tipoAtividade === 'somente_inscricao') hidden @endif><label class="form-label fw-semibold" for="categoria_id">Categoria</label><select class="form-select @error('categoria_id') is-invalid @enderror" id="categoria_id" name="categoria_id" @disabled($tipoAtividade === 'somente_inscricao')><option value="">Sem categoria</option>@foreach($categorias as $categoria)<option value="{{$categoria->id}}" @selected((int)old('categoria_id',$atividade?->categoria_id)===$categoria->id)>{{$categoria->nome}}</option>@endforeach</select>@error('categoria_id')<div class="invalid-feedback">{{$message}}</div>@enderror</div>
<div class="col-12 col-lg-2"><label class="form-label fw-semibold" for="ativo">Status *</label><select class="form-select" id="ativo" name="ativo"><option value="1" @selected((int)old('ativo',isset($atividade)?(int)$atividade->ativo:1)===1)>Ativo</option><option value="0" @selected((int)old('ativo',isset($atividade)?(int)$atividade->ativo:1)===0)>Inativo</option></select></div>
<div class="col-12 col-md-4" data-campo-atividade @if($tipoAtividade === 'somente_inscricao') hidden @endif><label class="form-label fw-semibold" for="modalidade">Modalidade</label><select class="form-select @error('modalidade') is-invalid @enderror" id="modalidade" name="modalidade" @disabled($tipoAtividade === 'somente_inscricao')><option value="">Selecione...</option><option value="ead" @selected(old('modalidade',$atividade?->modalidade)==='ead')>EAD</option><option value="presencial" @selected(old('modalidade',$atividade?->modalidade)==='presencial')>Presencial</option></select>@error('modalidade')<div class="invalid-feedback">{{$message}}</div>@enderror</div>
<div class="col-12 col-md-4" data-campo-simples><label class="form-label fw-semibold" for="data_inicio">Data de início</label><input type="datetime-local" class="form-control @error('data_inicio') is-invalid @enderror" id="data_inicio" name="data_inicio" value="{{old('data_inicio',$atividade?->data_inicio?->format('Y-m-d\TH:i'))}}">@error('data_inicio')<div class="invalid-feedback">{{$message}}</div>@enderror</div>
<div class="col-12 col-md-4" data-campo-simples><label class="form-label fw-semibold" for="data_fim">Data de fim</label><input type="datetime-local" class="form-control @error('data_fim') is-invalid @enderror" id="data_fim" name="data_fim" value="{{old('data_fim',$atividade?->data_fim?->format('Y-m-d\TH:i'))}}">@error('data_fim')<div class="invalid-feedback">{{$message}}</div>@enderror</div>
<div class="col-12" id="sessoesAtividade" @if($formatoAtividade !== 'com_sessoes') hidden @endif>
<hr><div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h3 class="h6 fw-bold mb-1">Sessões e cotas</h3><p class="small text-muted mb-0">O participante escolhe uma sessão. Deixe a cota vazia para não limitar as vagas daquela sessão.</p></div><button class="btn btn-sm btn-outline-primary" type="button" id="adicionarSessao"><i class="bi bi-plus-lg me-1"></i>Adicionar sessão</button></div>
@error('sessoes')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
<div id="listaSessoes" class="d-flex flex-column gap-3"></div>
</div>
</div></div></div>
@if(app(\App\Services\GiPermissionService::class)->permite('atividades.personalizar'))
@include('atividades.partials.personalizacao')
@endif
<div class="mt-4 d-flex justify-content-end gap-2"><a href="{{route('atividades.index')}}" class="btn btn-outline-secondary">Cancelar</a><button class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Salvar</button></div>
@push('scripts')
<script src="{{ asset('tipo-atividade.js') }}?v={{ filemtime(public_path('tipo-atividade.js')) }}" defer></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
const formato=document.getElementById('formato_atividade'),area=document.getElementById('sessoesAtividade'),lista=document.getElementById('listaSessoes');if(!formato||!area||!lista)return;
const inicial=@json($sessoesAtividadeIniciais);
const esc=v=>String(v??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('"','&quot;');let sequencia=0;
function adicionar(s={}){const chave=sequencia++,bloco=document.createElement('div');bloco.className='sessao-item border rounded-3 p-3 bg-light';bloco.innerHTML=`${s.id?`<input type="hidden" name="sessoes[${chave}][id]" value="${Number(s.id)}">`:''}<div class="row g-3 align-items-end"><div class="col-12 col-lg-4"><label class="form-label fw-semibold">Nome da sessão *</label><input class="form-control" name="sessoes[${chave}][nome]" maxlength="255" value="${esc(s.nome)}" required></div><div class="col-12 col-md-4 col-lg-3"><label class="form-label">Início *</label><input class="form-control" type="datetime-local" name="sessoes[${chave}][data_inicio]" value="${esc(s.data_inicio)}" required></div><div class="col-12 col-md-4 col-lg-3"><label class="form-label">Fim *</label><input class="form-control" type="datetime-local" name="sessoes[${chave}][data_fim]" value="${esc(s.data_fim)}" required></div><div class="col-9 col-md-3 col-lg-1"><label class="form-label">Cota</label><input class="form-control" type="number" min="1" step="1" name="sessoes[${chave}][limite_vagas]" value="${esc(s.limite_vagas)}"></div><div class="col-3 col-md-1"><button class="btn btn-outline-danger remover-sessao" type="button" title="Remover sessão"><i class="bi bi-trash"></i></button></div></div>`;lista.append(bloco)}
function atualizar(){const comSessoes=formato.value==='com_sessoes';area.hidden=!comSessoes;document.querySelectorAll('[data-campo-simples]').forEach(el=>el.hidden=comSessoes);lista.querySelectorAll('input').forEach(el=>el.disabled=!comSessoes);if(comSessoes&&!lista.children.length)adicionar()}
inicial.forEach(adicionar);document.getElementById('adicionarSessao').addEventListener('click',()=>adicionar());lista.addEventListener('click',e=>{const botao=e.target.closest('.remover-sessao');if(botao){botao.closest('.sessao-item').remove();if(!lista.children.length)adicionar()}});formato.addEventListener('change',atualizar);atualizar();
});
</script>
@endpush
