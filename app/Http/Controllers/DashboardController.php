<?php

namespace App\Http\Controllers;

use App\Models\Atividade;
use App\Models\Convidado;
use App\Models\Evento;
use App\Models\InscricaoAtividade;
use App\Models\InscricaoSubmissaoTrabalho;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController
{
    public function __invoke(Request $request): View
    {
        $eventos = Evento::query()->orderBy('nome')->get(['id', 'nome']);
        $eventoId = max(0, (int) $request->query('evento'));
        if (! $eventos->contains('id', $eventoId)) $eventoId = (int) ($eventos->first()?->id ?? 0);

        $atividades = Atividade::query()->where('evento_id', $eventoId)->orderBy('nome')
            ->get(['id', 'evento_id', 'nome', 'formulario']);
        $atividadeId = max(0, (int) $request->query('atividade'));
        $atividade = $atividades->firstWhere('id', $atividadeId);
        if (! $atividade) {
            $atividade = $atividades->first(fn (Atividade $item) => $this->camposCombo($item) !== []) ?? $atividades->first();
            $atividadeId = (int) ($atividade?->id ?? 0);
        }

        $campos = $atividade ? $this->camposCombo($atividade) : [];
        $campoNome = trim((string) $request->query('campo', ''));
        $campo = collect($campos)->firstWhere('nome', $campoNome) ?? ($campos[0] ?? null);

        return view('dashboard', [
            'indicadores' => [
                ['rotulo' => 'Eventos', 'valor' => Evento::query()->count(), 'icone' => 'bi-calendar-event', 'cor' => 'primary'],
                ['rotulo' => 'Atividades', 'valor' => Atividade::query()->count(), 'icone' => 'bi-list-check', 'cor' => 'success'],
                ['rotulo' => 'Convidados', 'valor' => Convidado::query()->count(), 'icone' => 'bi-people', 'cor' => 'warning'],
                ['rotulo' => 'Trabalhos submetidos', 'valor' => InscricaoSubmissaoTrabalho::query()->whereIn('status', ['submetido', 'avaliado'])->count(), 'icone' => 'bi-file-earmark-check', 'cor' => 'info'],
            ],
            'eventos' => $eventos,
            'eventoId' => $eventoId,
            'atividades' => $atividades,
            'atividadeId' => $atividadeId,
            'campos' => $campos,
            'campoNome' => $campo['nome'] ?? '',
            'grafico' => $atividade && $campo ? $this->grafico($atividade, $campo) : ['total' => 0, 'itens' => []],
            'ultimasInscricoes' => InscricaoAtividade::query()
                ->with([
                    'atividade' => fn ($query) => $query->withTrashed()->with([
                        'evento' => fn ($evento) => $evento->withTrashed(),
                    ]),
                    'participante' => fn ($query) => $query->withTrashed(),
                ])->latest('created_at')->latest('id')->limit(10)->get(),
        ]);
    }

    private function camposCombo(Atividade $atividade): array
    {
        return collect($atividade->formulario['campos'] ?? [])->filter(
            fn (array $campo) => ($campo['tipo'] ?? '') === 'select' && ! empty($campo['nome']) && ! empty($campo['opcoes']),
        )->map(fn (array $campo) => [
            'nome' => (string) $campo['nome'],
            'label' => (string) ($campo['label'] ?? $campo['nome']),
            'opcoes' => $campo['opcoes'],
        ])->values()->all();
    }

    private function grafico(Atividade $atividade, array $campo): array
    {
        $opcoes = collect($campo['opcoes'])->map(fn ($opcao) => is_array($opcao)
            ? ['valor' => (string) ($opcao['valor'] ?? ''), 'rotulo' => (string) ($opcao['texto'] ?? $opcao['valor'] ?? '')]
            : ['valor' => (string) $opcao, 'rotulo' => (string) $opcao])
            ->filter(fn (array $opcao) => $opcao['valor'] !== '')->unique('valor')->values();
        $contagens = array_fill_keys($opcoes->pluck('valor')->all(), 0);
        $respostas = InscricaoAtividade::query()->where('atividade_id', $atividade->id)->get(['resposta']);

        foreach ($respostas as $inscricao) {
            $resposta = $inscricao->resposta ?? [];
            $valor = $resposta[$campo['nome']] ?? null;
            if (is_scalar($valor) && array_key_exists((string) $valor, $contagens)) $contagens[(string) $valor]++;
        }

        $total = $respostas->count();
        $itens = $opcoes->map(fn (array $opcao) => [
            'rotulo' => $opcao['rotulo'],
            'quantidade' => $contagens[$opcao['valor']],
            'percentual' => $total ? round($contagens[$opcao['valor']] * 100 / $total, 1) : 0,
        ])->all();
        $semResposta = $total - array_sum($contagens);
        if ($semResposta > 0) $itens[] = [
            'rotulo' => 'Não informado',
            'quantidade' => $semResposta,
            'percentual' => round($semResposta * 100 / $total, 1),
        ];

        return ['total' => $total, 'itens' => $itens];
    }
}
