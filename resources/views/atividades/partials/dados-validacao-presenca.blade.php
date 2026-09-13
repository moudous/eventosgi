<dl class="row mb-0 small">
    <dt class="col-sm-4">Participante</dt><dd class="col-sm-8 fw-semibold">{{ $resultado['participante'] }}</dd>
    <dt class="col-sm-4">E-mail</dt><dd class="col-sm-8">{{ $resultado['email'] ?: '—' }}</dd>
    <dt class="col-sm-4">CPF</dt><dd class="col-sm-8">{{ $resultado['cpf'] ?: '—' }}</dd>
    <dt class="col-sm-4">Atividade</dt><dd class="col-sm-8">{{ $resultado['atividade'] }}</dd>
    @if(!empty($resultado['sessao_atividade']))<dt class="col-sm-4">Sessão</dt><dd class="col-sm-8">{{ $resultado['sessao_atividade'] }}</dd>@endif
    <dt class="col-sm-4">Evento</dt><dd class="col-sm-8">{{ $resultado['evento'] ?: '—' }}</dd>
    <dt class="col-sm-4">Inscrição</dt><dd class="col-sm-8">#{{ $resultado['inscricao'] }} · {{ $resultado['data_inscricao'] ?: '—' }}</dd>
    @if($resultado['data_presenca'])<dt class="col-sm-4">Presença registrada</dt><dd class="col-sm-8">{{ $resultado['data_presenca'] }}</dd>@endif
</dl>
