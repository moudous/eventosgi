<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\CodigoInscricao;
use App\Models\CredencialParticipante;
use App\Models\Participante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Identifica o visitante por e-mail antes de liberar o formulario da atividade.
 *
 * Fluxo: o visitante informa o e-mail, recebe um código global temporário e o digita.
 * Com o codigo conferido, o cadastro em participantes e resolvido (encontrado,
 * unificado quando ha duplicidade, ou criado) e fica gravado na sessao.
 */
class IdentificacaoParticipanteService
{
    /**
     * Colunas de participantes consultadas ao procurar o e-mail informado.
     *
     * Publica porque FormularioInscricaoService procura pelo mesmo criterio ao conferir
     * duplicidade a partir de um e-mail ainda nao identificado.
     */
    public const COLUNAS_EMAIL = ['email', 'email2', 'email_institucional'];

    public const MINUTOS_VALIDADE = 15;

    /** Validade do link de definição da senha global. */
    public const MINUTOS_VALIDADE_LINK_SENHA = 15;

    /** Tentativas de digitacao aceitas antes de o codigo ser invalidado. */
    public const MAX_TENTATIVAS = 5;

    /** Validade do token entregue a consumidores externos apos a conferencia do codigo. */
    public const HORAS_TOKEN = 2;

    /** Nome do campo isca. Visitante nao ve; robo que preenche formulario inteiro cai nele. */
    public const CAMPO_ISCA = 'confirmacao_inscricao';

    public function __construct(
        private readonly GiEmailService $email,
        private readonly ParticipanteUnificacaoService $unificacao,
        private readonly LimiteEnvioCodigoService $limites,
    ) {}

    /**
     * Se o campo isca veio preenchido, quem enviou foi um robo.
     *
     * Quem chama trata como envio aceito e nao dispara nada: dizer "voce e um robo"
     * so ensinaria o robo a contornar a armadilha.
     */
    public function pareceRobo(Request $request): bool
    {
        return trim((string) $request->input(self::CAMPO_ISCA, '')) !== '';
    }

    /**
     * Gera e envia um novo código global, invalidando os anteriores do mesmo e-mail.
     *
     * @return array{email: string, expira_em: \Illuminate\Support\Carbon}
     */
    public function solicitarCodigo(Request $request, Atividade $atividade, string $email, bool $ignorarLimites = false): array
    {
        $email = mb_strtolower(trim($email));

        // Todos os limites -- por e-mail, por sessao, por faixa de rede, por atividade --
        // ficam em LimiteEnvioCodigoService, que contabiliza o pedido ao aprova-lo.
        if (! $ignorarLimites) $this->limites->conferir($request, $atividade, $email);

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiraEm = now()->addMinutes(self::MINUTOS_VALIDADE);
        $expiraSenhaEm = now()->addMinutes(self::MINUTOS_VALIDADE_LINK_SENHA);
        $tokenRedefinicao = Str::random(64);

        $registro = DB::transaction(function () use ($email, $codigo, $expiraEm, $expiraSenhaEm, $tokenRedefinicao, $request): CodigoInscricao {
            CodigoInscricao::query()->where('email', $email)->where('expira_em', '>', now())
                ->update(['expira_em' => now()->subSecond(), 'redefinicao_expira_em' => now()->subSecond()]);

            return CodigoInscricao::create([
                'email' => $email,
                'codigo_hash' => hash('sha256', $codigo),
                'expira_em' => $expiraEm,
                'redefinicao_token_hash' => hash('sha256', $tokenRedefinicao),
                'redefinicao_expira_em' => $expiraSenhaEm,
                'ip' => $request->ip(),
            ]);
        });

        try {
            $this->email->enviar(
                $email,
                null,
                'Código de inscrição — '.$atividade->nome,
                $this->conteudo($atividade, $codigo, $tokenRedefinicao),
                "eventosgi-codigo-{$registro->id}",
            );
        } catch (Throwable $excecao) {
            report($excecao);
            $registro->update([
                'expira_em' => now()->subSecond(),
                'redefinicao_expira_em' => now()->subSecond(),
            ]);

            // Falta de configuracao nao se resolve tentando de novo: o aviso precisa dizer isso.
            throw ValidationException::withMessages([
                'email' => $excecao instanceof GiEmailNaoConfiguradoException
                    ? 'O envio de e-mails ainda não foi configurado nesta aplicação. Avise o organizador do evento.'
                    : 'Não foi possível enviar o código para este e-mail agora. Tente novamente em alguns instantes.',
            ])->errorBag('identificacao');
        }

        if (! $ignorarLimites) $this->limites->registrarEnvio($atividade, $email);

        return ['email' => $email, 'expira_em' => $expiraEm];
    }

    /**
     * Confere o codigo digitado e grava o participante identificado na sessao.
     *
     * @return array{id: int, nome: string, email: string, criado: bool, unificados: int}
     */
    public function validarCodigo(Request $request, Atividade $atividade, string $email, string $codigo): array
    {
        $email = mb_strtolower(trim($email));
        $registro = $this->conferirCodigo($atividade, $email, $codigo);

        // Endereco confirmado e endereco de gente: devolve a vaga que o pedido consumiu.
        $this->limites->liberar($request, $email);

        $resolucao = $this->resolverParticipante($email);
        $participante = $resolucao['participante'];

        $registro->update(['participante_id' => (int) $participante->id]);

        $request->session()->regenerate();
        $request->session()->put($this->chaveSessao($atividade), [
            'participante_id' => (int) $participante->id,
            'nome' => (string) $participante->nome,
            'email' => $email,
            'codigo_id' => $registro->id,
            'validado_em' => now()->toIso8601String(),
            'ultimo_acesso' => now()->timestamp,
        ]);

        return [
            'id' => (int) $participante->id,
            'nome' => (string) $participante->nome,
            'email' => $email,
            'criado' => $resolucao['criado'],
            'unificados' => $resolucao['unificados'],
        ];
    }

    /** Identifica com a senha global definida pelo link recebido por e-mail. */
    public function validarSenha(Request $request, Atividade $atividade, string $email, string $senha): array
    {
        $email = mb_strtolower(trim($email));
        $chaveLimite = 'senha-atividade:'.sha1($email.'|'.(string) $request->ip());
        if (RateLimiter::tooManyAttempts($chaveLimite, 5)) {
            throw ValidationException::withMessages([
                'senha' => 'Muitas tentativas. Aguarde um minuto para tentar novamente.',
            ])->errorBag('identificacao');
        }
        $credencial = CredencialParticipante::query()->where('email', $email)->first();
        if (! $credencial || ! Hash::check($senha, $credencial->senha)) {
            RateLimiter::hit($chaveLimite, 60);
            throw ValidationException::withMessages([
                'senha' => 'E-mail ou senha inválidos.',
            ])->errorBag('identificacao');
        }
        RateLimiter::clear($chaveLimite);

        $resolucao = $this->resolverParticipante($email);
        $participante = $resolucao['participante'];
        if ((int) $credencial->participante_id !== (int) $participante->id) {
            $credencial->update(['participante_id' => (int) $participante->id]);
        }
        $request->session()->regenerate();
        $request->session()->put($this->chaveSessao($atividade), [
            'participante_id' => (int) $participante->id,
            'nome' => (string) $participante->nome,
            'email' => $email,
            'validado_em' => now()->toIso8601String(),
            'ultimo_acesso' => now()->timestamp,
        ]);

        return [
            'id' => (int) $participante->id,
            'nome' => (string) $participante->nome,
            'email' => $email,
            'criado' => $resolucao['criado'],
            'unificados' => $resolucao['unificados'],
        ];
    }

    /**
     * Confere o código digitado. Ele pode ser reutilizado em outras atividades até expirar.
     */
    public function conferirCodigo(Atividade $atividade, string $email, string $codigo): CodigoInscricao
    {
        $email = mb_strtolower(trim($email));
        $codigo = preg_replace('/\D/', '', $codigo) ?? '';

        // Sem transacao: as tentativas erradas precisam ficar gravadas, e a corrida por
        // consumir o mesmo codigo duas vezes e resolvida pelo UPDATE condicional abaixo.
        $registro = CodigoInscricao::query()
            ->where('email', $email)
            ->where('expira_em', '>', now())->latest('id')->first();

        if (! $registro) {
            throw ValidationException::withMessages([
                'codigo' => 'O código expirou ou ainda não foi enviado para este e-mail. Peça um novo código.',
            ])->errorBag('identificacao');
        }

        if ($registro->tentativas >= self::MAX_TENTATIVAS) {
            $registro->update(['expira_em' => now()->subSecond()]);

            throw ValidationException::withMessages([
                'codigo' => 'O código foi digitado incorretamente muitas vezes. Peça um novo código.',
            ])->errorBag('identificacao');
        }

        if ($codigo === '' || ! hash_equals($registro->codigo_hash, hash('sha256', $codigo))) {
            $registro->increment('tentativas');

            throw ValidationException::withMessages([
                'codigo' => 'Código inválido. Restam '.max(0, self::MAX_TENTATIVAS - $registro->tentativas).' tentativas.',
            ])->errorBag('identificacao');
        }

        // O código identifica o e-mail em qualquer atividade enquanto estiver válido.
        // validado_em registra o primeiro uso, sem consumi-lo antes dos 15 minutos.
        CodigoInscricao::query()->whereKey($registro->id)->whereNull('validado_em')
            ->update(['validado_em' => now()]);

        return $registro->refresh();
    }

    /**
     * Fluxo dos consumidores externos (plugin do WordPress), que nao compartilham a sessao
     * desta aplicacao: confere o codigo e devolve um token para acompanhar a inscricao.
     *
     * @return array{token: string, expira_em: \Illuminate\Support\Carbon, participante: Participante, criado: bool, unificados: int}
     */
    public function emitirToken(Request $request, Atividade $atividade, string $email, string $codigo): array
    {
        $email = mb_strtolower(trim($email));
        $registro = $this->conferirCodigo($atividade, $email, $codigo);

        // Vale para o consumidor externo o mesmo do fluxo desta aplicacao: codigo
        // confirmado devolve a vaga de endereco novo daquela origem.
        $this->limites->liberar($request, $email);
        $resolucao = $this->resolverParticipante($email);

        $token = Str::random(64);
        $expiraEm = now()->addHours(self::HORAS_TOKEN);

        $registro->update([
            'participante_id' => (int) $resolucao['participante']->id,
            'token_hash' => hash('sha256', $token),
            'token_expira_em' => $expiraEm,
        ]);

        return [
            'token' => $token,
            'expira_em' => $expiraEm,
            'participante' => $resolucao['participante'],
            'criado' => $resolucao['criado'],
            'unificados' => $resolucao['unificados'],
        ];
    }

    /**
     * Participante por tras de um token emitido em emitirToken(), ou null se o token
     * não existe ou expirou. O token também é global entre atividades.
     *
     * @return array{participante: Participante, email: string}|null
     */
    public function porToken(Atividade $atividade, string $token): ?array
    {
        $token = trim($token);
        if ($token === '') return null;

        $registro = CodigoInscricao::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('token_expira_em', '>', now())
            ->whereNotNull('participante_id')
            ->first();

        if (! $registro) return null;

        $participante = Participante::query()->where('id', $registro->participante_id)->first();

        return $participante ? ['participante' => $participante, 'email' => (string) $registro->email] : null;
    }

    /** Invalida o token, para o visitante poder recomecar com outro e-mail. */
    public function revogarToken(Atividade $atividade, string $token): void
    {
        if (trim($token) === '') return;

        CodigoInscricao::query()
            ->where('token_hash', hash('sha256', $token))
            ->update(['token_hash' => null, 'token_expira_em' => null]);
    }

    /**
     * Cadastro correspondente ao e-mail: o unico existente, o resultado da unificacao
     * dos duplicados, ou um novo registro quando o e-mail ainda nao esta na base.
     *
     * @return array{participante: Participante, criado: bool, unificados: int}
     */
    public function resolverParticipante(string $email): array
    {
        $encontrados = Participante::query()
            ->where(function ($consulta) use ($email): void {
                foreach (self::COLUNAS_EMAIL as $coluna) $consulta->orWhere($coluna, $email);
            })
            ->orderBy('id')->get();

        if ($encontrados->isEmpty()) {
            return ['participante' => $this->criar($email), 'criado' => true, 'unificados' => 0];
        }

        if ($encontrados->count() === 1) {
            return ['participante' => $encontrados->first(), 'criado' => false, 'unificados' => 0];
        }

        $resultado = $this->unificacao->unificar(
            $encontrados->pluck('id')->all(),
            'Unificação automática pelo formulário de inscrição',
        );

        return [
            'participante' => Participante::query()->where('id', $resultado['participante_id'])->firstOrFail(),
            'criado' => false,
            'unificados' => $resultado['removidos'],
        ];
    }

    /**
     * Dados do participante identificado nesta atividade, ou null quando ainda nao houve identificacao.
     *
     * @return array{participante_id: int, nome: string, email: string}|null
     */
    public function daSessao(Request $request, Atividade $atividade): ?array
    {
        $dados = $request->session()->get($this->chaveSessao($atividade));

        if (! is_array($dados) || empty($dados['participante_id']) || empty($dados['email'])) return null;

        if (now()->timestamp - (int) ($dados['ultimo_acesso'] ?? 0) >= 30 * 60) {
            $this->esquecer($request, $atividade);
            $request->session()->flash('identificacao_expirada', 'Sua sessão expirou após 30 minutos de inatividade. Entre novamente com seu e-mail e senha.');
            return null;
        }

        $dados['ultimo_acesso'] = now()->timestamp;
        $request->session()->put($this->chaveSessao($atividade), $dados);
        return $dados;
    }

    /** Cadastro identificado, recarregado do banco para refletir alteracoes feitas depois da validacao. */
    public function participanteDaSessao(Request $request, Atividade $atividade): ?Participante
    {
        $dados = $this->daSessao($request, $atividade);

        return $dados ? Participante::query()->where('id', $dados['participante_id'])->first() : null;
    }

    public function esquecer(Request $request, Atividade $atividade): void
    {
        $request->session()->forget($this->chaveSessao($atividade));
    }

    private function chaveSessao(Atividade $atividade): string
    {
        return 'identificacao_formularios';
    }

    private function criar(string $email): Participante
    {
        return Participante::create([
            'nome' => $this->nomeProvisorio($email),
            'email' => $email,
            'ativo' => true,
        ]);
    }

    /**
     * Nome de partida para o cadastro novo, derivado do e-mail. A coluna nome nao aceita
     * nulo (compoe a chave primaria) e o visitante corrige o valor ao completar o cadastro.
     *
     * Sai apenas com letras: um palpite como "Joaquim2024" seria recusado por
     * App\Rules\NomeCompleto, e o visitante veria um erro sobre algo que nao digitou.
     */
    private function nomeProvisorio(string $email): string
    {
        $local = str_replace(['.', '_', '-', '+'], ' ', strstr($email, '@', true) ?: $email);
        $letras = preg_replace("/[^\p{L}\p{M}' ]/u", '', $local) ?? '';
        $nome = trim(preg_replace('/\s+/u', ' ', $letras) ?? '');

        return $nome === '' ? 'Participante' : mb_substr(mb_convert_case($nome, MB_CASE_TITLE, 'UTF-8'), 0, 100);
    }

    private function conteudo(Atividade $atividade, string $codigo, string $tokenRedefinicao): string
    {
        $urlSenha = route('senha-participante.editar', ['token' => $tokenRedefinicao]);
        return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#22303f;line-height:1.6">'
            .'<p>Olá!</p>'
            .'<p>Recebemos um pedido de inscrição em <strong>'.e($atividade->nome).'</strong>.</p>'
            .'<p>Use o código abaixo para continuar o preenchimento do formulário:</p>'
            .'<p style="font-size:30px;font-weight:bold;letter-spacing:8px;margin:24px 0">'.e($codigo).'</p>'
            .'<p>O código vale por '.self::MINUTOS_VALIDADE.' minutos e pode ser usado em qualquer atividade ou evento durante esse período.</p>'
            .'<p>Você também pode definir uma senha para suas próximas inscrições:</p>'
            .'<p><a href="'.e($urlSenha).'" style="display:inline-block;padding:10px 16px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:6px">Definir nova senha</a></p>'
            .'<p style="color:#748096;font-size:13px">O link para definir a senha vale por '.self::MINUTOS_VALIDADE_LINK_SENHA.' minutos e funciona uma única vez.</p>'
            .'<p style="color:#748096;font-size:13px">Se você não pediu este código, ignore esta mensagem.</p>'
            .'</div>';
    }
}
