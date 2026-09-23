<?php

namespace App\Http\Controllers;

use App\Models\Atividade;
use App\Models\AutoPresencaLink;
use App\Models\InscricaoAtividade;
use App\Models\Participante;
use App\Services\IdentificacaoParticipanteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AutoPresencaController
{
    public function criar(Request $request, Atividade $atividade): JsonResponse
    {
        $dados = $request->validate([
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date', 'after:inicio'],
        ], ['fim.after' => 'A data e hora final deve ser posterior à inicial.']);

        $link = AutoPresencaLink::create([
            'atividade_id' => $atividade->id,
            'hash' => Str::random(64),
            'inicio' => $dados['inicio'],
            'fim' => $dados['fim'],
        ]);

        return response()->json($this->dadosLink($link), 201);
    }

    public function ajustar(Atividade $atividade, AutoPresencaLink $link, Request $request): JsonResponse
    {
        abort_unless((int) $link->atividade_id === (int) $atividade->id, 404);
        $dados = $request->validate(['minutos' => ['required', 'integer', 'in:-1,1']]);
        DB::transaction(function () use ($link, $dados): void {
            $link = AutoPresencaLink::query()->lockForUpdate()->findOrFail($link->id);
            $minutos = (int) $dados['minutos'];
            $link->update([
                'ajuste_minutos' => $link->ajuste_minutos + $minutos,
                'fim' => $link->fim->copy()->addMinutes($minutos),
            ]);
        });

        return response()->json($this->dadosLink($link->fresh()));
    }

    public function alterarPeriodo(Atividade $atividade, AutoPresencaLink $link, Request $request): JsonResponse
    {
        abort_unless((int) $link->atividade_id === (int) $atividade->id, 404);
        $dados = $request->validate([
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date', 'after:inicio'],
        ], ['fim.after' => 'A data e hora final deve ser posterior à inicial.']);

        $link->update($dados);

        return response()->json($this->dadosLink($link->fresh()));
    }

    public function excluir(Atividade $atividade, AutoPresencaLink $link): JsonResponse
    {
        abort_unless((int) $link->atividade_id === (int) $atividade->id, 404);
        $link->delete();

        return response()->json(['message' => 'Link de auto registro excluído definitivamente.']);
    }

    public function abrir(Request $request, AutoPresencaLink $link): View
    {
        $link->load('atividade.evento');
        $link->increment('cliques');
        $link->refresh();

        $identificacao = $this->identificacaoAtual($request, $link->atividade);
        $inscricao = $identificacao ? $this->inscricaoDaPessoa($link->atividade, $identificacao['participante'], $identificacao['email']) : null;

        return view('atividades.auto-presenca-publica', compact('link', 'identificacao', 'inscricao'));
    }

    public function participantes(AutoPresencaLink $link, Request $request): JsonResponse
    {
        abort_unless($link->estaAberto(), 410, 'O prazo para confirmação de presença já passou.');
        $busca = trim((string) $request->query('q', ''));

        $consultaInscricoes = InscricaoAtividade::query()->where('atividade_id', $link->atividade_id);
        $porEmail = (clone $consultaInscricoes)
            ->where('participante_email', 'like', "%{$busca}%")->limit(20)->get(['participante_id', 'participante_email']);
        $porNome = collect();
        if ($busca !== '') {
            $idsInscritos = (clone $consultaInscricoes)->whereNotNull('participante_id')->pluck('participante_id')->all();
            $idsPorNome = $idsInscritos === [] ? [] : Participante::query()->whereIn('id', $idsInscritos)
                ->where('nome', 'like', "%{$busca}%")->limit(20)->pluck('id')->all();
            if ($idsPorNome !== []) {
                $porNome = (clone $consultaInscricoes)->whereIn('participante_id', $idsPorNome)
                    ->get(['participante_id', 'participante_email']);
            }
        }
        // Eloquent Collection::merge usa a chave primária do model. Como esta consulta
        // seleciona apenas participante/e-mail, todos os models ficam sem chave e seriam
        // descartados. A coleção base preserva corretamente os resultados encontrados.
        $inscricoes = collect($porEmail->all())->concat($porNome->all())
            ->unique('participante_email')->take(20)->values();
        $porId = Participante::query()->whereIn('id', $inscricoes->pluck('participante_id')->filter()->all())
            ->get(['id', 'nome', 'email'])->keyBy('id');

        return response()->json(['results' => $inscricoes->map(function (InscricaoAtividade $inscricao) use ($porId): array {
            $participante = $porId->get($inscricao->participante_id);
            return ['id' => $inscricao->participante_email, 'text' => $participante?->nome ?: $inscricao->participante_email, 'email' => $inscricao->participante_email];
        })->values()]);
    }

    public function confirmar(Request $request, AutoPresencaLink $link, IdentificacaoParticipanteService $identificacao): RedirectResponse
    {
        $link->load('atividade');
        abort_unless($link->estaAberto(), 410, 'O prazo para confirmação de presença já passou.');
        $dados = $request->validate([
            'email' => ['nullable', 'email', 'max:150'],
            'senha' => ['nullable', 'string', 'max:200'],
        ]);

        $atual = $this->identificacaoAtual($request, $link->atividade);
        if (! $atual) {
            if (empty($dados['email']) || empty($dados['senha'])) {
                throw ValidationException::withMessages(['email' => 'Selecione seu nome e informe sua senha.']);
            }
            $resultado = $identificacao->validarSenha($request, $link->atividade, $dados['email'], $dados['senha']);
            $atual = ['participante' => Participante::query()->findOrFail($resultado['id']), 'email' => $resultado['email'], 'nome' => $resultado['nome']];
        }

        DB::transaction(function () use ($link, $atual): void {
            $vigente = AutoPresencaLink::query()->lockForUpdate()->findOrFail($link->id);
            abort_unless($vigente->estaAberto(), 410, 'O prazo para confirmação de presença já passou.');
            $inscricao = $this->inscricaoDaPessoa($vigente->atividade, $atual['participante'], $atual['email']);
            abort_unless($inscricao, 403, 'Você não possui uma inscrição ativa nesta atividade.');
            if (! $inscricao->presente) {
                $inscricao->update(['presente' => true, 'data_presenca' => now(), 'presenca_validada_por' => null]);
            }
        });

        return back()->with('auto_presenca_confirmada', 'Presença confirmada com sucesso.');
    }

    private function identificacaoAtual(Request $request, Atividade $atividade): ?array
    {
        $servico = app(IdentificacaoParticipanteService::class);
        $sessao = $servico->daSessao($request, $atividade);
        $participante = $sessao ? $servico->participanteDaSessao($request, $atividade) : null;
        return $participante ? ['participante' => $participante, 'email' => $sessao['email'], 'nome' => $participante->nome] : null;
    }

    private function inscricaoDaPessoa(Atividade $atividade, Participante $participante, string $email): ?InscricaoAtividade
    {
        return InscricaoAtividade::query()->where('atividade_id', $atividade->id)
            ->where(fn ($query) => $query->where('participante_id', $participante->id)->orWhere('participante_email', mb_strtolower(trim($email))))
            ->first();
    }

    /** @return array<string, mixed> */
    private function dadosLink(AutoPresencaLink $link): array
    {
        return [
            'id' => $link->id,
            'url' => route('auto-presenca.abrir', ['link' => $link->hash]),
            'inicio' => $link->inicio->format('d/m/Y H:i'),
            'fim' => $link->fim->format('d/m/Y H:i'),
            'inicio_input' => $link->inicio->format('Y-m-d\TH:i'),
            'fim_input' => $link->fim->format('Y-m-d\TH:i'),
            'ajuste_minutos' => $link->ajuste_minutos,
            'cliques' => $link->cliques,
            'ajustar_url' => route('atividades.auto-presenca.ajustar', [$link->atividade_id, $link]),
            'periodo_url' => route('atividades.auto-presenca.periodo', [$link->atividade_id, $link]),
            'excluir_url' => route('atividades.auto-presenca.excluir', [$link->atividade_id, $link]),
        ];
    }
}
