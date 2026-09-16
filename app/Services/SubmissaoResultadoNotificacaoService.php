<?php

namespace App\Services;

use App\Models\InscricaoSubmissaoTrabalho;
use App\Models\Submissao;
use App\Models\SubmissaoResultadoNotificacao;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SubmissaoResultadoNotificacaoService
{
    public function __construct(private readonly GiEmailService $email) {}

    public function modelos(Submissao $submissao, string $tipo): array
    {
        if ($tipo === 'aprovados') {
            return [
                'assunto_principal' => 'Trabalho aprovado — [TITULO_TRABALHO]',
                'mensagem_principal' => "Olá, [NOME].\n\nSeu trabalho \"[TITULO_TRABALHO]\" foi aprovado. Como primeiro autor, você é responsável pelo envio do e-pôster em PDF.\n\nPasso a passo:\n1. Acesse [LINK].\n2. Entre com seu e-mail e senha.\n3. Selecione o trabalho aprovado.\n4. Na seção \"Arquivo da apresentação — e-pôster\", selecione o PDF.\n5. Clique em \"Enviar\". Você poderá visualizar e substituir o arquivo durante o prazo.\n\nPrazo para envio: [PRAZO_EPOSTER].",
                'assunto_coautor' => 'Trabalho aprovado — [TITULO_TRABALHO]',
                'mensagem_coautor' => "Olá, [NOME].\n\nO trabalho \"[TITULO_TRABALHO]\", do qual você é coautor(a), foi aprovado.\n\nAvise o primeiro autor para acessar [LINK] e enviar o e-pôster em PDF até [PRAZO_EPOSTER]. Somente o primeiro autor pode realizar ou substituir o envio.",
            ];
        }

        return [
            'assunto' => 'Resultado da avaliação — [TITULO_TRABALHO]',
            'mensagem' => "Olá, [NOME].\n\nO trabalho \"[TITULO_TRABALHO]\" não foi aprovado para apresentação. Por esse motivo, não será necessário enviar o arquivo de e-pôster.\n\nAgradecemos a participação na submissão [SUBMISSAO].",
        ];
    }

    public function resumo(Submissao $submissao, string $tipo): array
    {
        $pendentes = $this->destinatariosPendentes($submissao, $tipo);

        return [
            'trabalhos' => $pendentes->pluck('trabalho.id')->unique()->count(),
            'primeiros_autores' => $pendentes->where('principal', true)->count(),
            'coautores' => $pendentes->where('principal', false)->count(),
            'destinatarios' => $pendentes->count(),
            'modelos' => $this->modelos($submissao, $tipo),
        ];
    }

    public function enviar(Submissao $submissao, string $tipo, array $mensagens, Request $request): array
    {
        $pendentes = $this->destinatariosPendentes($submissao, $tipo);
        $enviados = collect();
        $falhas = 0;

        foreach ($pendentes as $destinatario) {
            /** @var InscricaoSubmissaoTrabalho $trabalho */
            $trabalho = $destinatario['trabalho'];
            $principal = $destinatario['principal'];
            $assuntoModelo = $tipo === 'aprovados'
                ? $mensagens[$principal ? 'assunto_principal' : 'assunto_coautor']
                : $mensagens['assunto'];
            $mensagemModelo = $tipo === 'aprovados'
                ? $mensagens[$principal ? 'mensagem_principal' : 'mensagem_coautor']
                : $mensagens['mensagem'];

            try {
                $disparoId = $this->email->enviar(
                    $destinatario['email'],
                    $destinatario['nome'],
                    $this->substituir($assuntoModelo, $submissao, $trabalho, $destinatario, false),
                    $this->substituir($mensagemModelo, $submissao, $trabalho, $destinatario, true),
                    'submissao-resultado-'.$tipo.'-'.$trabalho->id.'-v'.$trabalho->notificacao_resultado_versao.'-'.md5($destinatario['email']),
                );
                SubmissaoResultadoNotificacao::firstOrCreate([
                    'inscrito_submissao_trabalho_id' => $trabalho->id,
                    'versao' => $trabalho->notificacao_resultado_versao,
                    'tipo' => $tipo,
                    'email' => $destinatario['email'],
                ], [
                    'nome' => $destinatario['nome'],
                    'principal' => $principal,
                    'disparo_gi_id' => $disparoId,
                    'enviado_em' => now(),
                ]);
                $enviados->push($destinatario);
            } catch (Throwable $erro) {
                $falhas++;
                Log::warning('Falha ao notificar resultado de trabalho.', [
                    'tipo' => $tipo, 'trabalho' => $trabalho->id,
                    'email' => $destinatario['email'], 'erro' => $erro->getMessage(),
                ]);
            }
        }

        $usuario = trim((string) $request->session()->get('gi_context.usuario.nome'))
            ?: 'Usuário GI '.(string) $request->session()->get('gi_context.usuario.id', 'não identificado');
        $usuario = Str::limit($usuario, 255, '');
        foreach ($enviados->groupBy(fn (array $item): int => $item['trabalho']->id) as $itens) {
            $trabalho = $itens->first()['trabalho'];
            $trabalho->historicos()->create([
                'historico' => Str::limit($usuario.' disparou e-mail aos autores do trabalho '.($tipo === 'aprovados' ? 'aprovado' : 'reprovado'), 255, ''),
                'usuario' => $usuario,
                'dados' => [
                    'tipo' => $tipo,
                    'primeiros_autores' => $itens->where('principal', true)->count(),
                    'coautores' => $itens->where('principal', false)->count(),
                ],
                'data_hora' => now(),
            ]);
        }

        return [
            'trabalhos' => $enviados->pluck('trabalho.id')->unique()->count(),
            'enviados' => $enviados->count(),
            'falhas' => $falhas,
        ];
    }

    private function destinatariosPendentes(Submissao $submissao, string $tipo): Collection
    {
        $situacao = $tipo === 'aprovados' ? 'aprovado' : 'reprovado';
        $trabalhos = $submissao->trabalhos()->where('status', 'avaliado')->where('situacao', $situacao)
            ->with(['inscricao', 'autores', 'notificacoesResultado'])->get();

        return $trabalhos->flatMap(function (InscricaoSubmissaoTrabalho $trabalho) use ($tipo): array {
            $principal = $trabalho->autores->firstWhere('principal', true) ?? $trabalho->autores->first();
            $destinatarios = [[
                'trabalho' => $trabalho,
                'email' => mb_strtolower(trim((string) $trabalho->inscricao?->email)),
                'nome' => $principal?->nome ?: 'Primeiro autor',
                'principal' => true,
            ]];
            foreach ($trabalho->autores->where('principal', false) as $autor) {
                $email = mb_strtolower(trim((string) $autor->email));
                if ($email !== '') $destinatarios[] = ['trabalho' => $trabalho, 'email' => $email, 'nome' => $autor->nome, 'principal' => false];
            }

            $unicos = collect($destinatarios)->filter(fn (array $item): bool => filter_var($item['email'], FILTER_VALIDATE_EMAIL) !== false)
                ->unique('email');

            return $unicos->reject(function (array $item) use ($trabalho, $tipo): bool {
                return $trabalho->notificacoesResultado->contains(fn ($registro): bool =>
                    $registro->versao === $trabalho->notificacao_resultado_versao
                    && $registro->tipo === $tipo
                    && mb_strtolower($registro->email) === $item['email']
                );
            })->values()->all();
        })->values();
    }

    private function substituir(string $modelo, Submissao $submissao, InscricaoSubmissaoTrabalho $trabalho, array $destinatario, bool $html): string
    {
        $link = route('submissoes.publicas.formulario', $submissao).'?trabalho='.$trabalho->id;
        $prazo = $submissao->periodoEposterConfigurado()
            ? $submissao->eposter_data_inicio->format('d/m/Y H:i').' a '.$submissao->eposter_data_fim->format('d/m/Y H:i')
            : 'consulte a organização do evento';
        $texto = $html ? nl2br(e($modelo)) : trim(str_replace(["\r", "\n"], ' ', $modelo));
        $escapar = fn (string $valor): string => $html ? e($valor) : $valor;
        $valores = [
            '[NOME]' => $escapar($destinatario['nome']),
            '[TITULO_TRABALHO]' => $escapar($trabalho->titulo_trabalho),
            '[SUBMISSAO]' => $escapar($submissao->titulo),
            '[PRAZO_EPOSTER]' => $escapar($prazo),
            '[LINK]' => $html ? '<a href="'.e($link).'">acessar o formulário da submissão</a>' : $link,
        ];

        return str_replace(array_keys($valores), array_values($valores), $texto);
    }
}
