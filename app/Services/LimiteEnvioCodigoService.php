<?php

namespace App\Services;

use App\Models\Atividade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Limites do envio de codigos de inscricao.
 *
 * O formulario publico dispara e-mail para um endereco que o visitante digita, sem que
 * ninguem prove nada antes. E uma porta aberta: um robo pede codigos para enderecos
 * aleatorios, tudo entra na fila do GI, e as mensagens que voltam de enderecos
 * inexistentes desgastam a reputacao do dominio remetente -- que nao se recupera
 * desligando o formulario depois.
 *
 * As regras aqui atacam duas figuras diferentes:
 *
 * - A pessoa que fica tentando: limites por e-mail e por sessao do navegador.
 * - O robo que varia o endereco a cada pedido: limites por faixa de rede e por atividade,
 *   contando tambem enderecos distintos, nao so a quantidade de envios. Trocar de
 *   endereco a cada pedido e a assinatura do ataque; repetir o mesmo endereco e
 *   comportamento de quem errou a digitacao ou nao recebeu.
 *
 * Nenhum balde de origem usa o endereco IP exato -- veja
 * DispositivoVisitanteService::faixaDoVisitante() para o porque.
 */
class LimiteEnvioCodigoService
{
    /** Intervalo mínimo, em segundos, entre dois pedidos globais para o mesmo e-mail. */
    public const INTERVALO_REENVIO = 60;

    /** Códigos globais por hora para o mesmo e-mail. */
    public const MAX_POR_EMAIL = 5;

    /** Codigos por hora aceitos de uma mesma faixa de rede. */
    public const MAX_POR_FAIXA = 20;

    /**
     * Enderecos distintos por hora aceitos de uma mesma faixa de rede.
     *
     * Menor que MAX_POR_FAIXA de proposito: reenviar para o mesmo endereco continua
     * barato, estrear enderecos e o que fica caro. Confirmar o codigo devolve a vaga,
     * entao um lugar com muita gente atras do mesmo IP -- um campus, um auditorio -- vai
     * liberando espaco conforme as pessoas se identificam, enquanto o robo, que nunca
     * confirma nada, enche o balde e para.
     */
    public const MAX_ENDERECOS_POR_FAIXA = 8;

    /** Enderecos distintos por hora aceitos de uma mesma sessao do navegador. */
    public const MAX_ENDERECOS_POR_SESSAO = 3;

    /** Codigos por hora aceitos em uma mesma atividade, somadas todas as origens. */
    public const MAX_POR_ATIVIDADE = 200;

    /**
     * Codigos por hora aceitos da origem real da requisicao.
     *
     * Numa chamada de API o IP contado e o que o consumidor repassa em X-Visitante-Ip, e
     * quem tem o token escolhe esse valor a cada pedido. Este balde e o unico que o
     * cabecalho nao alcanca: um token vazado ainda esbarra em um teto, mesmo forjando IP
     * diferente toda vez.
     */
    public const MAX_POR_ORIGEM_REAL = 300;

    /** Marca, na requisicao, que os limites ja foram conferidos e contabilizados. */
    private const JA_CONFERIDO = 'limites_envio_codigo_conferidos';

    public function __construct(
        private readonly DispositivoVisitanteService $dispositivo,
        private readonly FaixaIpService $faixas,
    ) {}

    /**
     * Confere todos os limites e ja contabiliza o pedido. Lanca ValidationException na
     * primeira regra estourada, sempre no bag "identificacao" e no campo do e-mail.
     *
     * Contabiliza antes de o e-mail sair: uma falha no envio nao pode virar porta para
     * repetir sem limite.
     */
    public function conferir(Request $request, Atividade $atividade, string $email): void
    {
        // Um pedido consome cota uma vez so. Quem confere os limites cedo -- para que
        // regras posteriores, como a de duplicidade, nao respondam de graca -- nao faz
        // o mesmo pedido contar duas vezes quando o envio acontece logo adiante.
        if ($request->attributes->get(self::JA_CONFERIDO) === true) return;

        $email = mb_strtolower(trim($email));
        $chaveEmail = 'codigo-inscricao-global:'.sha1($email);

        if (RateLimiter::tooManyAttempts($chaveEmail, 1)) {
            $this->recusar('Aguarde '.RateLimiter::availableIn($chaveEmail).' segundos para pedir um novo código.');
        }

        if (RateLimiter::tooManyAttempts($chaveEmail.':hora', self::MAX_POR_EMAIL)) {
            $this->recusar('Muitos códigos foram solicitados para este e-mail. Tente novamente mais tarde.');
        }

        foreach ($this->origens($request) as [$chave, $maxEnvios, $maxEnderecos, $recusa, $recusaEndereco]) {
            if (RateLimiter::tooManyAttempts($chave, $maxEnvios)) $this->recusar($recusa);

            if ($maxEnderecos !== null && $this->enderecoInedito($chave, $email)
                && RateLimiter::tooManyAttempts($chave.':enderecos', $maxEnderecos)) {
                $this->recusar($recusaEndereco);
            }
        }

        // Disjuntor da atividade: acima deste ritmo nao ha inscricao acontecendo, ha
        // enxurrada. Fica no log para o organizador saber que houve, e nao descobrir
        // pela reputacao do dominio semanas depois.
        $chaveAtividade = 'codigo-inscricao-atividade:'.$atividade->id;
        if (RateLimiter::tooManyAttempts($chaveAtividade, self::MAX_POR_ATIVIDADE)) {
            Log::warning('Teto de códigos por hora atingido na atividade.', [
                'atividade_id' => $atividade->id,
                'limite' => self::MAX_POR_ATIVIDADE,
                'faixa' => $this->dispositivo->faixaDoVisitante($request),
            ]);

            $this->recusar('Estamos recebendo muitos pedidos de código nesta atividade. Tente novamente em alguns minutos.');
        }

        $this->contabilizar($request, $atividade, $email);
        $request->attributes->set(self::JA_CONFERIDO, true);
    }

    /**
     * Comeca a contar o intervalo de reenvio, depois de o e-mail ter saido de fato.
     *
     * Separado de conferir() porque so faz sentido travar o reenvio quando houve envio:
     * se o disparo falhou, o visitante precisa poder tentar de novo na hora.
     */
    public function registrarEnvio(Atividade $atividade, string $email): void
    {
        RateLimiter::hit(
            'codigo-inscricao-global:'.sha1(mb_strtolower(trim($email))),
            self::INTERVALO_REENVIO,
        );
    }

    /**
     * Devolve a vaga de endereco novo quando o codigo e confirmado.
     *
     * E o que separa o auditorio cheio do robo: os dois pedem codigos para muitos
     * enderecos diferentes, mas so no auditorio alguem digita o codigo de volta.
     */
    public function liberar(Request $request, string $email): void
    {
        $email = mb_strtolower(trim($email));

        foreach ($this->origens($request) as [$chave, , $maxEnderecos]) {
            if ($maxEnderecos === null) continue;

            // Uma vaga so volta uma vez por endereco e origem: sem esta marca, confirmar
            // o mesmo codigo repetidas vezes zeraria o contador de enderecos novos.
            $devolvida = $chave.':devolvida:'.sha1($email);
            if (RateLimiter::attempts($devolvida) > 0) continue;
            if (RateLimiter::attempts($chave.':enderecos') <= 0) continue;

            RateLimiter::hit($devolvida, 3600);
            RateLimiter::decrement($chave.':enderecos', 3600);
        }
    }

    /**
     * Baldes de origem aplicaveis a esta requisicao.
     *
     * Cada item traz a chave, o teto de envios, o teto de enderecos distintos (null
     * quando a origem nao conta enderecos) e as mensagens de recusa correspondentes.
     *
     * @return list<array{0: string, 1: int, 2: ?int, 3: string, 4: string}>
     */
    private function origens(Request $request): array
    {
        $origens = [];

        // Rede declarada como da instituicao em /configuracao: o balde por rede nao se
        // aplica. Ali um IP publico so representa um lugar cheio de gente, nao uma origem.
        // Tudo o que nao depende da rede -- limite por e-mail, por sessao, teto da
        // atividade, isca e selo -- continua valendo.
        $faixa = $this->faixas->liberado($this->dispositivo->ipDoVisitante($request))
            ? null
            : $this->dispositivo->faixaDoVisitante($request);

        if ($faixa !== null) {
            $origens[] = ['codigo-inscricao-faixa:'.sha1($faixa), self::MAX_POR_FAIXA, self::MAX_ENDERECOS_POR_FAIXA,
                'Muitos códigos foram solicitados a partir desta conexão. Tente novamente mais tarde.',
                'Muitos e-mails diferentes foram usados a partir desta conexão. Confirme o código de um deles ou tente novamente mais tarde.'];
        }

        $sessao = $this->identificadorDaSessao($request);
        if ($sessao !== null) {
            // A sessao e uma pessoa. Quem estreia um quarto endereco em uma hora nao esta
            // se inscrevendo, esta procurando qual endereco recebe.
            $origens[] = ['codigo-inscricao-sessao:'.$sessao, self::MAX_POR_FAIXA, self::MAX_ENDERECOS_POR_SESSAO,
                'Muitos códigos foram solicitados nesta sessão. Tente novamente mais tarde.',
                'Você pediu códigos para e-mails demais. Confirme o código de um deles ou tente novamente mais tarde.'];
        }

        $origemReal = $request->ip();
        if ($origemReal !== null) {
            $origens[] = ['codigo-inscricao-origem:'.sha1($origemReal), self::MAX_POR_ORIGEM_REAL, null,
                'Muitos códigos foram solicitados a partir desta conexão. Tente novamente mais tarde.', ''];
        }

        return $origens;
    }

    /**
     * Este endereco ainda nao apareceu nesta origem dentro da janela?
     *
     * Reenviar para um endereco ja visto nao gasta vaga de endereco novo -- e o caso de
     * quem nao recebeu o primeiro e-mail.
     */
    private function enderecoInedito(string $chaveOrigem, string $email): bool
    {
        return RateLimiter::attempts($chaveOrigem.':visto:'.sha1($email)) === 0;
    }

    private function contabilizar(Request $request, Atividade $atividade, string $email): void
    {
        RateLimiter::hit('codigo-inscricao-global:'.sha1($email).':hora', 3600);
        RateLimiter::hit('codigo-inscricao-atividade:'.$atividade->id, 3600);

        foreach ($this->origens($request) as [$chave, , $maxEnderecos]) {
            RateLimiter::hit($chave, 3600);

            if ($maxEnderecos === null) continue;

            $visto = $chave.':visto:'.sha1($email);
            if (RateLimiter::attempts($visto) > 0) continue;

            RateLimiter::hit($visto, 3600);
            RateLimiter::hit($chave.':enderecos', 3600);
        }
    }

    /** Identificador estavel da sessao do navegador, quando existe uma. */
    private function identificadorDaSessao(Request $request): ?string
    {
        if (! $request->hasSession() || ! $request->session()->isStarted()) return null;

        return substr(hash('sha256', $request->session()->getId()), 0, 32);
    }

    private function recusar(string $mensagem): never
    {
        throw ValidationException::withMessages(['email' => $mensagem])->errorBag('identificacao');
    }
}
