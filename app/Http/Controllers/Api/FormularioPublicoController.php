<?php

namespace App\Http\Controllers\Api;

use App\Models\Atividade;
use App\Models\Participante;
use App\Rules\EmailValido;
use App\Services\FormularioInscricaoService;
use App\Services\ConteudoEditorFormularioService;
use App\Services\DistribuicaoVagasService;
use App\Services\IdentificacaoParticipanteService;
use App\Services\LimiteEnvioCodigoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FormularioPublicoController
{
    public function __construct(
        private readonly FormularioInscricaoService $inscricoes,
        private readonly IdentificacaoParticipanteService $identificacao,
        private readonly LimiteEnvioCodigoService $limites,
        private readonly ConteudoEditorFormularioService $editor,
        private readonly DistribuicaoVagasService $distribuicao,
    ) {}

    /**
     * Estrutura do formulario para que consumidores externos (ex.: WordPress) o renderizem.
     */
    public function mostrar(Request $request, Atividade $atividade): JsonResponse
    {
        $this->conferirAtividade($atividade);

        $config = $this->distribuicao->recalcular($atividade);
        $estado = $this->inscricoes->estado($atividade);
        $identificado = $this->identificado($request, $atividade);
        $campos = array_values(array_map(fn (array $campo) => [
            'nome' => $campo['nome'] ?? '',
            'label' => $campo['label'] ?? ($campo['nome'] ?? ''),
            'tipo' => $campo['tipo'] ?? 'text',
            'placeholder' => $campo['placeholder'] ?? '',
            'texto_opcao' => $campo['texto_opcao'] ?? null,
            'obrigatorio' => (bool) ($campo['obrigatorio'] ?? false),
            'criterio_vagas' => (bool) ($campo['criterio_vagas'] ?? false),
            'opcoes' => array_values($campo['opcoes'] ?? []),
            'grid' => (int) ($campo['grid'] ?? 6),
            'aceitos' => array_values($campo['aceitos'] ?? []),
            'max_arquivos' => min(10, max(1, (int) ($campo['max_arquivos'] ?? 1))),
            'validacao' => $campo['validacao'] ?? '',
        ], array_filter($config['campos'] ?? [], fn ($campo) => ! empty($campo['nome']))));
        if ($atividade->comSessoes()) {
            $sessoes = $atividade->sessoesAtivas()->withCount('inscricoes')->get();
            array_unshift($campos, [
                'nome' => 'sessao_atividade_id', 'label' => 'Sessão', 'tipo' => 'radio',
                'placeholder' => '', 'obrigatorio' => true, 'criterio_vagas' => false,
                'opcoes' => $sessoes->filter(fn ($sessao) => $sessao->vagasRestantes() !== 0)->map(fn ($sessao) => [
                    'valor' => (string) $sessao->id,
                    'texto' => $sessao->rotuloPublico().($sessao->vagasRestantes() === null ? '' : ' — '.$sessao->vagasRestantes().' vaga(s) restante(s)'),
                ])->values()->all(),
                'grid' => 12, 'aceitos' => [], 'max_arquivos' => 1, 'validacao' => '',
            ]);
        }

        return response()->json([
            'atividade' => [
                'id' => $atividade->id,
                'nome' => $atividade->nome,
                'formato' => $atividade->formato,
                'modalidade' => $atividade->modalidade,
                'data_inicio' => $atividade->data_inicio?->toIso8601String(),
                'data_fim' => $atividade->data_fim?->toIso8601String(),
            ],
            'titulo' => $config['titulo'] ?? $atividade->nome,
            'subtitulo' => $config['subtitulo'] ?? '',
            'editor' => [
                'exibir' => (bool) ($config['editor']['exibir'] ?? ! empty($config['editor']['conteudo'])),
                'conteudo' => $this->editor->sanitizar($config['editor']['conteudo'] ?? ''),
            ],
            'distribuicao_vagas' => $config['distribuicao_vagas'] ?? null,
            'campos' => $campos,
            'estado' => $estado,
            // Identificacao por e-mail: o consumidor externo exibe esta etapa antes dos campos.
            //
            // "estado" acima nao depende de quem esta olhando, para o consumidor poder guardar
            // a estrutura em cache; o que varia por visitante fica aqui e so vem com o token.
            'identificacao' => [
                'obrigatoria' => true,
                'mensagem' => $atividade->mensagemIdentificacao(),
                'minutos_validade' => IdentificacaoParticipanteService::MINUTOS_VALIDADE,
                'campos_participante' => $this->inscricoes->camposDoParticipante(),
                'campo_isca' => IdentificacaoParticipanteService::CAMPO_ISCA,
                'participante' => $identificado ? $this->dados($identificado['participante'], $identificado['email']) : null,
                'ja_inscrito' => $identificado
                    ? $this->inscricoes->jaInscrito($atividade, $identificado['participante'], $identificado['email'])
                    : false,
                'mensagem_ja_inscrito' => $atividade->mensagemJaInscrito(),
            ],
        ]);
    }

    /** Gera e envia por e-mail a senha temporária de inscrição. */
    public function solicitarCodigo(Request $request, Atividade $atividade): JsonResponse
    {
        $this->conferirAtividade($atividade);

        $dados = $request->validate(
            ['email' => ['required', 'max:150', new EmailValido]],
            ['required' => 'Informe o seu e-mail.'],
            ['email' => 'e-mail'],
        );

        $email = mb_strtolower(trim($dados['email']));

        // Robo que caiu na isca recebe a mesma resposta de sempre, sem disparo nenhum.
        if ($this->identificacao->pareceRobo($request)) {
            return response()->json([
                'email' => $email,
                'expira_em' => now()->addMinutes(IdentificacaoParticipanteService::MINUTOS_VALIDADE)->toIso8601String(),
                'mensagem' => 'A senha temporária foi enviada para seu e-mail.',
            ], 202);
        }

        // Limites antes da conferencia de duplicidade: a resposta dela revela se um
        // endereco esta inscrito, e sem cota isso viraria uma sondagem gratuita.
        $this->limites->conferir($request, $atividade, $email);

        // Duplicidade antes do envio: quem já se inscreveu recebe o aviso, não uma senha.
        if ($this->inscricoes->jaInscritoPorEmail($atividade, $email)) {
            throw ValidationException::withMessages(['email' => $atividade->mensagemJaInscrito()]);
        }

        $resultado = $this->identificacao->solicitarCodigo($request, $atividade, $email);

        return response()->json([
            'email' => $resultado['email'],
            'expira_em' => $resultado['expira_em']->toIso8601String(),
            'mensagem' => 'A senha temporária foi enviada para seu e-mail.',
        ], 202);
    }

    /**
     * Confere a senha temporária e devolve o token das chamadas seguintes.
     */
    public function identificar(Request $request, Atividade $atividade): JsonResponse
    {
        $this->conferirAtividade($atividade);

        $dados = $request->validate(
            ['email' => ['required', 'max:150', new EmailValido], 'codigo' => ['required', 'string', 'max:10']],
            ['required' => 'Informe o e-mail e a senha temporária recebida.'],
            ['email' => 'e-mail', 'codigo' => 'senha temporária'],
        );

        $resultado = $this->identificacao->emitirToken($request, $atividade, $dados['email'], $dados['codigo']);
        $email = mb_strtolower(trim($dados['email']));

        return response()->json([
            'token' => $resultado['token'],
            'expira_em' => $resultado['expira_em']->toIso8601String(),
            'criado' => $resultado['criado'],
            'unificados' => $resultado['unificados'],
            'ja_inscrito' => $this->inscricoes->jaInscrito($atividade, $resultado['participante'], $email),
            'mensagem_ja_inscrito' => $atividade->mensagemJaInscrito(),
            'participante' => $this->dados($resultado['participante'], $email),
        ]);
    }

    /** Dados do visitante identificado, para recompor o formulario a cada exibicao. */
    public function identificacao(Request $request, Atividade $atividade): JsonResponse
    {
        $this->conferirAtividade($atividade);
        $identificado = $this->identificado($request, $atividade);

        abort_unless($identificado, 401, 'Identificação expirada. Confirme o seu e-mail novamente.');

        return response()->json([
            'participante' => $this->dados($identificado['participante'], $identificado['email']),
            'ja_inscrito' => $this->inscricoes->jaInscrito($atividade, $identificado['participante'], $identificado['email']),
            'mensagem_ja_inscrito' => $atividade->mensagemJaInscrito(),
        ]);
    }

    /** Encerra a identificacao para que o visitante recomece com outro e-mail. */
    public function encerrarIdentificacao(Request $request, Atividade $atividade): JsonResponse
    {
        $this->conferirAtividade($atividade);
        $this->identificacao->revogarToken($atividade, $this->token($request));

        return response()->json(['mensagem' => 'Identificação encerrada.']);
    }

    public function inscrever(Request $request, Atividade $atividade): JsonResponse
    {
        $this->conferirAtividade($atividade);
        $identificado = $this->identificado($request, $atividade);

        abort_unless($identificado, 401, 'Identificação expirada. Confirme o seu e-mail novamente.');

        $resultado = $this->inscricoes->inscrever($request, $atividade, $identificado['participante'], $identificado['email']);

        if ($resultado['sucesso']) $this->identificacao->revogarToken($atividade, $this->token($request));

        return response()->json($resultado, $resultado['sucesso'] ? 201 : 422);
    }

    private function conferirAtividade(Atividade $atividade): void
    {
        abort_unless($atividade->formulario, 404, 'Esta atividade não possui formulário publicado.');
        abort_unless($atividade->ativo, 404, 'Esta atividade não está ativa.');
    }

    /**
     * @return array{participante: Participante, email: string}|null
     */
    private function identificado(Request $request, Atividade $atividade): ?array
    {
        return $this->identificacao->porToken($atividade, $this->token($request));
    }

    private function token(Request $request): string
    {
        return (string) ($request->header('X-Identificacao-Token') ?: $request->input('identificacao_token', ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function dados(Participante $participante, string $email): array
    {
        return [
            'id' => (int) $participante->id,
            'email' => $email,
            ...array_map(
                fn (string $campo) => $participante->getAttribute($campo),
                array_combine(FormularioInscricaoService::CAMPOS_PARTICIPANTE, FormularioInscricaoService::CAMPOS_PARTICIPANTE),
            ),
        ];
    }
}
