@if($podeDistribuir)
<select class="form-select form-select-sm avaliador-trabalho" data-avaliador-url="{{ route('submissoes.inscritos.alterar-avaliador',[$submissao,$trabalho->id]) }}" data-avaliador-atual="{{ $trabalho->avaliador_id }}" aria-label="Avaliador do trabalho {{ $trabalho->titulo_trabalho }}">
    <option value="">-</option>
    @foreach($avaliadores as $avaliador)<option value="{{ $avaliador->id }}" @selected($trabalho->avaliador_id===$avaliador->id)>{{ $avaliador->nome }}</option>@endforeach
</select>
@else
{{ $trabalho->avaliador?->nome ?? '—' }}
@endif
