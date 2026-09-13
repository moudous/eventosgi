<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\InscricaoAtividade;
use App\Models\Participante;
use App\Rules\Cpf;
use App\Rules\EmailValido;
use App\Rules\NomeCompleto;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FormularioInscricaoService
{
    public function __construct(
        private readonly DispositivoVisitanteService $dispositivo,
        private readonly DistribuicaoVagasService $distribuicao,
        private readonly PresencaQrService $presencaQr,
    ) {}

    /** Disco privado dos anexos: fora de public/, para nao serem servidos direto pelo servidor web. */
    public const DISCO_ANEXOS = 'local';

    /** Pasta dos anexos dentro do disco. Mantida igual a antiga para os caminhos ja gravados continuarem valendo. */
    public const PASTA_ANEXOS = 'inscricoes';

    /** Campos de participantes que o visitante completa antes dos campos da atividade. */
    public const CAMPOS_PARTICIPANTE = ['nome', 'cpf', 'sexo', 'instituicao_ensino', 'email2', 'email_institucional'];

    /**
     * Estado atual do formulario: se aceita inscricoes e, se nao aceitar, o motivo
     * (duplicada, antes, fechado ou esgotado).
     *
     * O participante e opcional porque so o fluxo identificado consegue saber se
     * aquela pessoa ja se inscreveu.
     *
     * @return array{aberto: bool, motivo: ?string, mensagem: ?string, lista_reserva: bool}
     */
    public function estado(Atividade $atividade, ?Participante $participante = null, ?string $email = null): array
    {
        $config = $atividade->formulario ?? [];
        $agora = now();

        // Antes das datas e das vagas: quem já se inscreveu não deve nem ver o formulário.
        if ($participante && $this->jaInscrito($atividade, $participante, $email)) {
            return ['aberto' => false, 'motivo' => 'duplicada', 'mensagem' => $atividade->mensagemJaInscrito(), 'lista_reserva' => false];
        }

        if (! empty($config['abertura']) && $agora->lt($config['abertura'])) {
            return ['aberto' => false, 'motivo' => 'antes', 'mensagem' => $config['mensagem_antes'] ?? 'As inscrições ainda não foram abertas.', 'lista_reserva' => false];
        }

        if (! empty($config['fechamento']) && $agora->gt($config['fechamento'])) {
            return ['aberto' => false, 'motivo' => 'fechado', 'mensagem' => $config['mensagem_fechado'] ?? 'As inscrições estão encerradas.', 'lista_reserva' => false];
        }

        if ($atividade->vagasEsgotadas()) {
            return ['aberto' => false, 'motivo' => 'esgotado', 'mensagem' => $atividade->mensagemVagasEsgotadas(), 'lista_reserva' => false];
        }

        return ['aberto' => true, 'motivo' => null, 'mensagem' => null, 'lista_reserva' => $atividade->aceitandoListaReserva()];
    }

    /**
     * Regras de validacao derivadas dos campos configurados no construtor de formularios.
     */
    public function regras(Atividade $atividade): array
    {
        $regras = [];

        if ($atividade->comSessoes()) {
            $regras['sessao_atividade_id'] = [
                'required', 'integer',
                Rule::exists('sessoes_atividade', 'id')->where(fn ($q) => $q
                    ->where('atividade_id', $atividade->id)->where('ativo', true)->whereNull('deleted_at')),
            ];
        }

        foreach (($atividade->formulario['campos'] ?? []) as $campo) {
            if (empty($campo['nome'])) continue;

            $nome = $campo['nome'];
            $tipo = $campo['tipo'] ?? 'text';
            $opcoes = array_map(
                fn ($opcao) => (string) (is_array($opcao) ? ($opcao['valor'] ?? '') : $opcao),
                $campo['opcoes'] ?? [],
            );
            $checkboxSimples = $tipo === 'checkbox' && $opcoes === [];
            $multiplo = $tipo === 'multiselect' || ($tipo === 'checkbox' && ! $checkboxSimples);

            $regras[$nome] = $multiplo
                ? (! empty($campo['obrigatorio']) ? ['required', 'array', 'min:1'] : ['nullable', 'array'])
                : (! empty($campo['obrigatorio']) ? ['required'] : ['nullable']);

            if ($multiplo) {
                $regras[$nome.'.*'] = [Rule::in($opcoes)];
            } elseif ($checkboxSimples) {
                $regras[$nome][] = Rule::in(['1']);
            } elseif (in_array($tipo, ['select', 'radio'], true)) {
                $regras[$nome][] = Rule::in($opcoes);
            }

            if (! empty($campo['criterio_vagas']) && in_array($tipo, ['select', 'radio'], true)) {
                $regras[$nome] = ['required', Rule::in($opcoes)];
            }

            if ($tipo === 'file') {
                $maxArquivos = min(10, max(1, (int) ($campo['max_arquivos'] ?? 1)));
                $destino = $nome;
                if ($maxArquivos > 1) {
                    $regras[$destino][] = 'array';
                    $regras[$destino][] = 'max:'.$maxArquivos;
                    if (! empty($campo['obrigatorio'])) $regras[$destino][] = 'size:'.$maxArquivos;
                    $destino .= '.*';
                }
                $regras[$destino][] = 'file';
                if (! empty($campo['aceitos'])) $regras[$destino][] = 'mimes:'.implode(',', $campo['aceitos']);
            }

            if (($campo['validacao'] ?? '') === 'email') $regras[$nome][] = new EmailValido;
            if (($campo['validacao'] ?? '') === 'cpf') $regras[$nome][] = new Cpf;
            if (($campo['validacao'] ?? '') === 'telefone') $regras[$nome][] = 'regex:/^[0-9()+\s-]{8,20}$/';
        }

        return $regras;
    }

    /**
     * Se este participante ja tem inscricao nesta atividade.
     *
     * Confere tambem pelo e-mail identificado: inscricoes gravadas antes de uma unificacao
     * de cadastros podem ter ficado com outro participante_id, mas o e-mail e o mesmo.
     */
    public function jaInscrito(Atividade $atividade, ?Participante $participante, ?string $email = null): bool
    {
        $email = trim((string) ($email ?: $participante?->email));

        return $this->existeInscricao($atividade, $participante ? [$participante->id] : [], $email);
    }

    public function inscricaoDoParticipante(Atividade $atividade, ?Participante $participante, ?string $email = null): ?InscricaoAtividade
    {
        $email = mb_strtolower(trim((string) ($email ?: $participante?->email)));
        if (! $participante && $email === '') return null;

        return InscricaoAtividade::query()
            ->where('atividade_id', $atividade->id)
            ->where(function ($consulta) use ($participante, $email): void {
                if ($participante) $consulta->orWhere('participante_id', $participante->id);
                if ($email !== '') $consulta->orWhere('participante_email', $email);
            })->latest('id')->first();
    }

    /**
     * Mesma conferencia, porem a partir apenas do e-mail digitado, antes de existir
     * participante identificado.
     *
     * Serve a etapa em que o visitante so informou o e-mail: assim quem ja se inscreveu
     * recebe o aviso em vez de um codigo. Alem da inscricao gravada com o proprio e-mail,
     * alcanca a feita por cadastro que o tenha em qualquer coluna de e-mail — e, ao
     * contrario de resolverParticipante(), nao cria cadastro nenhum para descobrir isso.
     */
    public function jaInscritoPorEmail(Atividade $atividade, string $email): bool
    {
        $email = mb_strtolower(trim($email));

        if ($email === '') return false;

        $cadastros = Participante::query()
            ->where(function ($consulta) use ($email): void {
                foreach (IdentificacaoParticipanteService::COLUNAS_EMAIL as $coluna) $consulta->orWhere($coluna, $email);
            })
            ->pluck('id')->all();

        return $this->existeInscricao($atividade, $cadastros, $email);
    }

    /**
     * @param list<int> $participantes
     */
    private function existeInscricao(Atividade $atividade, array $participantes, string $email): bool
    {
        // Sem nenhum criterio a consulta encontraria a atividade inteira: nao ha o que conferir.
        if ($participantes === [] && $email === '') return false;

        return InscricaoAtividade::query()
            ->where('atividade_id', $atividade->id)
            ->where(function ($consulta) use ($participantes, $email): void {
                if ($participantes !== []) $consulta->orWhereIn('participante_id', $participantes);
                if ($email !== '') $consulta->orWhere('participante_email', $email);
            })
            ->exists();
    }

    /**
     * Definicao do bloco "Seus dados", usada tanto pela tela desta aplicacao quanto pela
     * API que descreve o formulario a consumidores externos (plugin do WordPress), para
     * que rotulos e obrigatoriedade nao divirjam entre as duas.
     *
     * "colunas" e a largura em uma grade de 12, seguida por ambas as telas.
     *
     * @return list<array<string, mixed>>
     */
    public function camposDoParticipante(): array
    {
        return [
            ['nome' => 'nome', 'label' => 'Nome completo', 'tipo' => 'text', 'obrigatorio' => true, 'colunas' => 8,
                'maxlength' => 100, 'placeholder' => 'Nome e sobrenome', 'ajuda' => '', 'opcoes' => []],
            ['nome' => 'cpf', 'label' => 'CPF', 'tipo' => 'text', 'obrigatorio' => true, 'colunas' => 4,
                'maxlength' => 14, 'placeholder' => '000.000.000-00', 'ajuda' => 'solicitado para a emissão de certificado quando for o caso.', 'opcoes' => []],
            ['nome' => 'email2', 'label' => 'E-mail alternativo', 'tipo' => 'email', 'obrigatorio' => false, 'colunas' => 4,
                'maxlength' => 150, 'placeholder' => '', 'ajuda' => '', 'opcoes' => []],
            ['nome' => 'email_institucional', 'label' => 'E-mail institucional', 'tipo' => 'email', 'obrigatorio' => false, 'colunas' => 4,
                'maxlength' => 150, 'placeholder' => '', 'ajuda' => '', 'opcoes' => []],
            ['nome' => 'instituicao_ensino', 'label' => 'Instituição de ensino', 'tipo' => 'text', 'obrigatorio' => false, 'colunas' => 6,
                'maxlength' => 80, 'placeholder' => '', 'ajuda' => '', 'opcoes' => []],
            ['nome' => 'sexo', 'label' => 'Sexo', 'tipo' => 'select', 'obrigatorio' => false, 'colunas' => 3,
                'maxlength' => 1, 'placeholder' => 'Não informado', 'ajuda' => 'Solicitado para emissão automatizada de certificado com o pronome correto, quando for o caso.', 'opcoes' => ['M' => 'Masculino', 'F' => 'Feminino']],
        ];
    }

    /**
     * Regras do bloco "Seus dados", preenchido apenas quando ha participante identificado.
     */
    public function regrasParticipante(): array
    {
        return [
            'participante.nome' => ['required', 'string', 'max:100', new NomeCompleto],
            'participante.cpf' => ['required', new Cpf],
            'participante.sexo' => ['nullable', 'in:M,F'],
            'participante.instituicao_ensino' => ['nullable', 'string', 'max:80'],
            'participante.email2' => ['nullable', 'max:150', new EmailValido],
            'participante.email_institucional' => ['nullable', 'max:150', new EmailValido],
        ];
    }

    /**
     * Nomes dos campos configurados como upload de arquivo.
     *
     * @return list<string>
     */
    public function camposDeArquivo(Atividade $atividade): array
    {
        return array_values(array_map(
            fn (array $campo) => (string) $campo['nome'],
            array_filter(
                $atividade->formulario['campos'] ?? [],
                fn (array $campo) => ! empty($campo['nome']) && ($campo['tipo'] ?? '') === 'file',
            ),
        ));
    }

    /**
     * Mensagens em portugues para as regras usadas pelos formularios.
     * O projeto nao publica os arquivos de traducao do Laravel, entao sem isto o
     * visitante receberia chaves cruas como "validation.required".
     */
    public function mensagens(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'email' => 'Informe um e-mail válido em :attribute.',
            'file' => 'O campo :attribute deve conter um arquivo.',
            'mimes' => 'O arquivo em :attribute deve ser do tipo: :values.',
            'array' => 'O campo :attribute deve conter uma lista de valores.',
            'max.array' => 'O campo :attribute aceita no máximo :max arquivos.',
            'size.array' => 'O campo :attribute deve conter exatamente :size arquivos.',
            'max.file' => 'O arquivo em :attribute não pode ser maior que :max kilobytes.',
            'regex' => 'O campo :attribute está em um formato inválido.',
            'in' => 'Selecione uma opção válida em :attribute.',
        ];
    }

    /**
     * Usa os rotulos configurados no construtor de formularios no lugar dos nomes tecnicos dos campos.
     */
    public function atributos(Atividade $atividade): array
    {
        $atributos = [];

        foreach (($atividade->formulario['campos'] ?? []) as $campo) {
            if (empty($campo['nome'])) continue;
            $rotulo = trim((string) ($campo['label'] ?? '')) ?: $campo['nome'];
            $atributos[$campo['nome']] = $rotulo;
            $atributos[$campo['nome'].'.*'] = $rotulo;
        }

        return $atributos + [
            'sessao_atividade_id' => 'sessão',
            'participante.nome' => 'nome completo',
            'participante.cpf' => 'CPF',
            'participante.sexo' => 'sexo',
            'participante.instituicao_ensino' => 'instituição de ensino',
            'participante.email2' => 'e-mail alternativo',
            'participante.email_institucional' => 'e-mail institucional',
        ];
    }

    /**
     * Reduz os CPFs a digitos antes da validacao: o visitante pode digitar 000.000.000-00,
     * mas a coluna guarda apenas os 11 numeros.
     */
    private function normalizarCpfs(Request $request, Atividade $atividade, bool $comParticipante): void
    {
        if ($comParticipante && is_array($participante = $request->input('participante')) && isset($participante['cpf'])) {
            $participante['cpf'] = $this->apenasDigitos($participante['cpf']);
            $request->merge(['participante' => $participante]);
        }

        foreach (($atividade->formulario['campos'] ?? []) as $campo) {
            if (($campo['validacao'] ?? '') !== 'cpf' || empty($campo['nome'])) continue;
            if ($request->has($campo['nome'])) {
                $request->merge([$campo['nome'] => $this->apenasDigitos($request->input($campo['nome']))]);
            }
        }
    }

    private function apenasDigitos(mixed $valor): mixed
    {
        if (! is_scalar($valor)) return $valor;

        $texto = trim((string) $valor);
        $digitos = preg_replace('/\D/', '', $texto) ?? '';

        // Sem nenhum digito, devolve o que foi digitado para a regra recusar,
        // em vez de limpar o campo em silencio.
        return $digitos !== '' ? $digitos : ($texto === '' ? null : $texto);
    }

    /**
     * Registra a inscricao. Lanca ValidationException quando os dados enviados nao passam nas regras.
     *
     * Quando o visitante foi identificado por e-mail, o bloco "Seus dados" tambem e validado,
     * o cadastro em participantes e atualizado e a inscricao guarda a identificacao dele.
     *
     * @return array{sucesso: bool, motivo: ?string, mensagem: string, inscricao_id: ?int, lista_reserva: bool}
     */
    public function inscrever(Request $request, Atividade $atividade, ?Participante $participante = null, ?string $emailIdentificado = null): array
    {
        return DB::transaction(function () use ($request, $atividade, $participante, $emailIdentificado) {
            // Serializa os envios da mesma atividade antes da conferencia final de vagas.
            $atividade = Atividade::whereKey($atividade->id)->lockForUpdate()->firstOrFail();

            $estado = $this->estado($atividade, $participante, $emailIdentificado);
            if (! $estado['aberto']) {
                return ['sucesso' => false, 'motivo' => $estado['motivo'], 'mensagem' => (string) $estado['mensagem'], 'inscricao_id' => null, 'lista_reserva' => false];
            }

            $listaReserva = ! empty($estado['lista_reserva']);

            $this->normalizarCpfs($request, $atividade, $participante !== null);

            $regras = $this->regras($atividade) + ($participante ? $this->regrasParticipante() : []);
            $validados = $request->validate($regras, $this->mensagens(), $this->atributos($atividade));
            $sessao = null;
            if ($atividade->comSessoes()) {
                $sessao = $atividade->sessoesAtivas()->whereKey((int) $validados['sessao_atividade_id'])->lockForUpdate()->firstOrFail();
                if (! $listaReserva && $sessao->limite_vagas !== null
                    && $sessao->inscricoes()->where('lista_reserva', false)->count() >= $sessao->limite_vagas) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['sessao_atividade_id' => 'Não há mais vagas disponíveis nesta sessão.']);
                }
            }
            $resposta = Arr::except($validados, ['participante', 'sessao_atividade_id']);
            if (! $listaReserva) $this->distribuicao->conferirDisponibilidade($atividade, $resposta);

            // Guarda apenas os arquivos de campos declarados no formulario; qualquer outro upload e descartado.
            // Disco privado: anexos de inscricao so saem por rota assinada, nunca por URL direta.
            foreach ($this->camposDeArquivo($atividade) as $nome) {
                $arquivos = $request->file($nome);
                if ($arquivos === null) continue;
                $lista = is_array($arquivos) ? $arquivos : [$arquivos];
                $resposta[$nome] = array_map(fn (UploadedFile $arquivo) => $this->guardarAnexo($arquivo), $lista);
            }

            if ($participante) {
                $participante->fill(Arr::only((array) ($validados['participante'] ?? []), self::CAMPOS_PARTICIPANTE))->save();
            }

            try {
                $inscricao = InscricaoAtividade::create([
                    'atividade_id' => $atividade->id,
                    'sessao_atividade_id' => $sessao?->id,
                    'participante_id' => $participante?->id,
                    'participante_email' => $participante ? ($emailIdentificado ?: $participante->email) : null,
                    'lista_reserva' => $listaReserva,
                    'resposta' => $resposta,
                    'codigo_qr' => ! empty($atividade->formulario['registrar_presenca_qrcode']) ? $this->presencaQr->novoCodigo() : null,
                    ...$this->dispositivo->capturar($request),
                ]);
            } catch (UniqueConstraintViolationException) {
                // Dois envios ao mesmo tempo: o índice único decide qual entra.
                return ['sucesso' => false, 'motivo' => 'duplicada', 'mensagem' => $atividade->mensagemJaInscrito(), 'inscricao_id' => null, 'lista_reserva' => false];
            }

            $this->distribuicao->recalcular($atividade->refresh());

            return [
                'sucesso' => true,
                'motivo' => null,
                'mensagem' => $atividade->formulario['mensagem_sucesso'] ?? 'Inscrição realizada com sucesso.',
                'inscricao_id' => $inscricao->id,
                'lista_reserva' => $listaReserva,
            ];
        });
    }

    private function guardarAnexo(UploadedFile $arquivo): string
    {
        $original = pathinfo($arquivo->getClientOriginalName(), PATHINFO_FILENAME);
        $nome = Str::slug($original) ?: 'arquivo';
        $extensao = mb_strtolower((string) $arquivo->getClientOriginalExtension());
        $extensao = preg_replace('/[^a-z0-9]+/', '', $extensao) ?: $arquivo->extension();

        return (string) $arquivo->storeAs(
            self::PASTA_ANEXOS,
            Str::uuid().'-'.$nome.($extensao ? '.'.$extensao : ''),
            self::DISCO_ANEXOS,
        );
    }
}
