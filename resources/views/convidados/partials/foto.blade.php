@if($convidado->foto_nome)
    <img class="convidado-miniatura" src="{{ route('convidados.foto', $convidado->foto_nome) }}" alt="Foto de {{ $convidado->nome }}">
@else
    <span class="convidado-sem-foto d-inline-flex align-items-center justify-content-center" title="Sem foto">
        <i class="bi bi-person-fill" aria-hidden="true"></i><span class="visually-hidden">Sem foto</span>
    </span>
@endif
