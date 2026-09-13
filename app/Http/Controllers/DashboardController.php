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

        $dispositivoEventoId = max(0, (int) $request->query('dispositivo_evento'));
        if (! $eventos->contains('id', $dispositivoEventoId)) $dispositivoEventoId = 0;
        $dispositivoAtividades = Atividade::query()
            ->whereHas('evento')
            ->when($dispositivoEventoId, fn ($query) => $query->where('evento_id', $dispositivoEventoId))
            ->with('evento:id,nome')->orderBy('nome')->get(['id', 'nome', 'evento_id']);
        $dispositivoAtividadeId = max(0, (int) $request->query('dispositivo_atividade'));
        if (! $dispositivoAtividades->contains('id', $dispositivoAtividadeId)) $dispositivoAtividadeId = 0;
        $dimensoesDispositivo = \App\Services\InscricoesDispositivoService::DIMENSOES;
        $inscricoesDispositivo = InscricaoAtividade::query()
            ->whereIn('atividade_id', $dispositivoAtividades->pluck('id'))
            ->when($dispositivoAtividadeId, fn ($query) => $query->where('atividade_id', $dispositivoAtividadeId))
            ->get(['dispositivo']);
        $graficosDispositivo = [];
        foreach ($dimensoesDispositivo as $dimensao => $titulo) {
            $graficosDispositivo[$dimensao] = app(\App\Services\InscricoesDispositivoService::class)->agrupar($inscricoesDispositivo, $dimensao);
        }
        $totalDispositivo = $inscricoesDispositivo->count();

        $filtrosExportacao = [
            'inscricoes-categoria' => [],
            'inscritos-opcao' => ['evento' => $eventoId, 'atividade' => $atividadeId, 'campo' => $campo['nome'] ?? ''],
            'evolucao-inscricoes' => ['evento' => $eventoId, 'atividade' => $atividadeId],
            'inscricoes-dispositivo' => ['dispositivo_evento' => $dispositivoEventoId, 'dispositivo_atividade' => $dispositivoAtividadeId],
        ];

        $categorias = \App\Models\Categoria::query()->orderBy('nome')->get(['id', 'nome']);
        $atividadesPorCategoria = Atividade::query()->selectRaw('categoria_id, COUNT(*) as total')
            ->groupBy('categoria_id')->pluck('total', 'categoria_id');
        $inscricoesPorCategoria = InscricaoAtividade::query()
            ->join('atividades', 'atividades.id', '=', 'inscricoes_atividade.atividade_id')
            ->whereNull('atividades.deleted_at')->selectRaw('atividades.categoria_id, COUNT(*) as total')
            ->groupBy('atividades.categoria_id')->pluck('total', 'categoria_id');
        $contagensCategorias = $categorias->map(fn ($categoria) => [
            'nome' => $categoria->nome,
            'icone' => \App\Models\Categoria::iconeParaNome($categoria->nome),
            'atividades' => (int) ($atividadesPorCategoria[$categoria->id] ?? 0),
            'inscricoes' => (int) ($inscricoesPorCategoria[$categoria->id] ?? 0),
        ])->filter(fn ($categoria) => $categoria['inscricoes'] > 0 || $categoria['atividades'] > 0)->values();
        if (($inscricoesPorCategoria[''] ?? 0) > 0 || ($atividadesPorCategoria[''] ?? 0) > 0) {
            $contagensCategorias->push(['nome' => 'Sem categoria',
                'icone' => 'bi-tags',
                'atividades' => (int) ($atividadesPorCategoria[''] ?? 0),
                'inscricoes' => (int) ($inscricoesPorCategoria[''] ?? 0)]);
        }

        return view('dashboard', [
            'contagensCategorias' => $contagensCategorias,
            'filtrosExportacao' => $filtrosExportacao,
            ...compact('dispositivoEventoId', 'dispositivoAtividades', 'dispositivoAtividadeId', 'dimensoesDispositivo', 'graficosDispositivo', 'totalDispositivo'),
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
            'evolucao' => $atividade ? app(\App\Services\EvolucaoInscricoesService::class)->paraAtividade($atividade) : null,
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
