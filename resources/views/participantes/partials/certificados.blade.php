{{-- Duas gerações de certificado convivem: os da tabela certificados (antigos) e os
     vínculos em lista_participantes (novos). A linha mostra as duas contagens. --}}
@if($contagem['total'] === 0)
    <span class="text-muted">—</span>
@else
    <div class="text-nowrap">{{ $contagem['antigos'] }} antigos</div>
    <div class="text-nowrap">{{ $contagem['novos'] }} novos</div>
@endif
