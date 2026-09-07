<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\CodigoInscricao;
use App\Models\Participante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Identifica o visitante por e-mail antes de liberar o formulario da atividade.
 *
 * Fluxo: o visitante informa o e-mail, recebe um codigo de uso unico e o digita.
 * Com o codigo conferido, o cadastro em participantes e resolvido (encontrado,
 * unificado quando ha duplicidade, ou criado) e fica gravado na sessao.
 */
class IdentificacaoParticipanteService
{
    /** Colunas de participantes consultadas ao procurar o e-mail informado. */
    private const COLUNAS_EMAIL = ['email', 'email2', 'email_institucional'];

    public const MINUTOS_VALIDADE = 15;

    /** Tentativas de digitacao aceitas antes de o codigo ser invalidado. */
    public const MAX_TENTATIVAS = 5;

    /** Intervalo minimo, em segundos, entre dois envios para o mesmo e-mail e atividade. */
    public const INTERVALO_REENVIO = 60;

    /** Validade do token entregue a consumidores externos apos a conferencia do codigo. */
    public const HORAS_TOKEN = 2;

    /**
     * Codigos por hora aceitos de um mesmo IP. Folgado de proposito: redes de instituicoes
     * saem por um unico endereco, e varias pessoas se inscrevem do mesmo lugar.
     */
    public const MAX_POR_IP = 20;

    /** Nome do campo isca. Visitante nao ve; robo que preenche formulario inteiro cai nele. */
    public const CAMPO_ISCA = 'confirmacao_inscricao';

    public function __construct(
        private readonly GiEmailService $email,
        private readonly ParticipanteUnificacaoService $unificacao,
        private readonly DispositivoVisitanteService $dispositivo,
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
     * Gera e envia um novo codigo, invalidando os anteriores da mesma atividade e e-mail.
     *
     * @return array{email: string, expira_em: \Illuminate\Support\Carbon}
     */
    public function solicitarCodigo(Request $request, Atividade $atividade, string $email): array
    {
        $email = mb_strtolower(trim($email));
        $chave = 'codigo-inscricao:'.$atividade->id.':'.sha1($email);

        if (RateLimiter::tooManyAttempts($chave, 1)) {
            throw ValidationException::withMessages([
                'email' => 'Aguarde '.RateLimiter::availableIn($chave).' segundos para pedir um novo código.',
            ])->errorBag('identificacao');
        }

        if (RateLimiter::tooManyAttempts($chave.':hora', 5)) {
            throw ValidationException::withMessages([
                'email' => 'Muitos códigos foram solicitados para este e-mail. Tente novamente mais tarde.',
            ])->errorBag('identificacao');
        }

        // Limite por origem: sem ele, bastaria variar o e-mail a cada envio para escapar do limite acima.
        $ip = $this->dispositivo->ipDoVisitante($request);
        $chaveIp = $ip !== null ? 'codigo-inscricao-ip:'.sha1($ip) : null;

        if ($chaveIp !== null && RateLimiter::tooManyAttempts($chaveIp, self::MAX_POR_IP)) {
            throw ValidationException::withMessages([
                'email' => 'Muitos códigos foram solicitados a partir desta conexão. Tente novamente mais tarde.',
            ])->errorBag('identificacao');
        }

        // Conta antes de disparar: uma falha no envio nao pode virar porta para repetir sem limite.
        RateLimiter::hit($chave.':hora', 3600);
        if ($chaveIp !== null) RateLimiter::hit($chaveIp, 3600);

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiraEm = now()->addMinutes(self::MINUTOS_VALIDADE);

        $registro = DB::transaction(function () use ($atividade, $email, $codigo, $expiraEm, $request): CodigoInscricao {
            CodigoInscricao::query()->where('atividade_id', $atividade->id)->where('email', $email)
                ->whereNull('validado_em')->update(['expira_em' => now()->subSecond()]);

            return CodigoInscricao::create([
                'atividade_id' => $atividade->id,
                'email' => $email,
                'codigo_hash' => hash('sha256', $codigo),
                'expira_em' => $expiraEm,
                'ip' => $request->ip(),
            ]);
        });

        try {
            $this->email->enviar(
                $email,
                null,
                'Código de inscrição — '.$atividade->nome,
                $this->conteudo($atividade, $codigo),
                "eventosgi-codigo-{$registro->id}",
            );
        } catch (Throwable $excecao) {
            report($excecao);
            $registro->update(['expira_em' => now()->subSecond()]);

            // Falta de configuracao nao se resolve tentando de novo: o aviso precisa dizer isso.
            throw ValidationException::withMessages([
                'email' => $excecao instanceof GiEmailNaoConfiguradoException
                    ? 'O envio de e-mails ainda não foi configurado nesta aplicação. Avise o organizador do evento.'
                    : 'Não foi possível enviar o código para este e-mail agora. Tente novamente em alguns instantes.',
            ])->errorBag('identificacao');
        }

        RateLimiter::hit($chave, self::INTERVALO_REENVIO);

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
        $resolucao = $this->resolverParticipante($email);
        $participante = $resolucao['participante'];

        $registro->update(['participante_id' => (int) $participante->id]);

        $request->session()->put($this->chaveSessao($atividade), [
            'participante_id' => (int) $participante->id,
            'nome' => (string) $participante->nome,
            'email' => $email,
            'codigo_id' => $registro->id,
            'validado_em' => now()->toIso8601String(),
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
     * Confere o codigo digitado e o marca como usado. Lanca ValidationException a cada recusa.
     */
    public function conferirCodigo(Atividade $atividade, string $email, string $codigo): CodigoInscricao
    {
        $email = mb_strtolower(trim($email));
        $codigo = preg_replace('/\D/', '', $codigo) ?? '';

        // Sem transacao: as tentativas erradas precisam ficar gravadas, e a corrida por
        // consumir o mesmo codigo duas vezes e resolvida pelo UPDATE condicional abaixo.
        $registro = CodigoInscricao::query()
            ->where('atividade_id', $atividade->id)->where('email', $email)->whereNull('validado_em')
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

        $consumido = CodigoInscricao::query()->whereKey($registro->id)
            ->whereNull('validado_em')->where('expira_em', '>', now())
            ->update(['validado_em' => now()]);

        if ($consumido === 0) {
            throw ValidationException::withMessages([
                'codigo' => 'Este código já foi utilizado. Peça um novo código.',
            ])->errorBag('identificacao');
        }

        return $registro->refresh();
    }

    /**
     * Fluxo dos consumidores externos (plugin do WordPress), que nao compartilham a sessao
     * desta aplicacao: confere o codigo e devolve um token para acompanhar a inscricao.
     *
     * @return array{token: string, expira_em: \Illuminate\Support\Carbon, participante: Participante, criado: bool, unificados: int}
     */
    public function emitirToken(Atividade $atividade, string $email, string $codigo): array
    {
        $email = mb_strtolower(trim($email));
        $registro = $this->conferirCodigo($atividade, $email, $codigo);
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
     * nao existe, expirou ou pertence a outra atividade.
     *
     * @return array{participante: Participante, email: string}|null
     */
    public function porToken(Atividade $atividade, string $token): ?array
    {
        $token = trim($token);
        if ($token === '') return null;

        $registro = CodigoInscricao::query()
            ->where('atividade_id', $atividade->id)
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
            ->where('atividade_id', $atividade->id)
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

        return is_array($dados) && ! empty($dados['participante_id']) && ! empty($dados['email']) ? $dados : null;
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
        return 'identificacao_inscricao.'.$atividade->id;
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

    private function conteudo(Atividade $atividade, string $codigo): string
    {
        return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#22303f;line-height:1.6">'
            .'<p>Olá!</p>'
            .'<p>Recebemos um pedido de inscrição em <strong>'.e($atividade->nome).'</strong>.</p>'
            .'<p>Use o código abaixo para continuar o preenchimento do formulário:</p>'
            .'<p style="font-size:30px;font-weight:bold;letter-spacing:8px;margin:24px 0">'.e($codigo).'</p>'
            .'<p>O código vale por '.self::MINUTOS_VALIDADE.' minutos e só pode ser usado uma vez.</p>'
            .'<p style="color:#748096;font-size:13px">Se você não pediu este código, ignore esta mensagem.</p>'
            .'</div>';
    }
}
