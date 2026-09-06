<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\InscricaoAtividade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormularioInscricaoService
{
    /**
     * Estado atual do formulario: se aceita inscricoes e, se nao aceitar, o motivo.
     *
     * @return array{aberto: bool, motivo: ?string, mensagem: ?string}
     */
    public function estado(Atividade $atividade): array
    {
        $config = $atividade->formulario ?? [];
        $agora = now();

        if (! empty($config['abertura']) && $agora->lt($config['abertura'])) {
            return ['aberto' => false, 'motivo' => 'antes', 'mensagem' => $config['mensagem_antes'] ?? 'As inscrições ainda não foram abertas.'];
        }

        if (! empty($config['fechamento']) && $agora->gt($config['fechamento'])) {
            return ['aberto' => false, 'motivo' => 'fechado', 'mensagem' => $config['mensagem_fechado'] ?? 'As inscrições estão encerradas.'];
        }

        if ($atividade->vagasEsgotadas()) {
            return ['aberto' => false, 'motivo' => 'esgotado', 'mensagem' => $atividade->mensagemVagasEsgotadas()];
        }

        return ['aberto' => true, 'motivo' => null, 'mensagem' => null];
    }

    /**
     * Regras de validacao derivadas dos campos configurados no construtor de formularios.
     */
    public function regras(Atividade $atividade): array
    {
        $regras = [];

        foreach (($atividade->formulario['campos'] ?? []) as $campo) {
            if (empty($campo['nome'])) continue;

            $regras[$campo['nome']] = ! empty($campo['obrigatorio']) ? ['required'] : ['nullable'];

            if (($campo['tipo'] ?? '') === 'file') {
                $maxArquivos = min(10, max(1, (int) ($campo['max_arquivos'] ?? 1)));
                $destino = $campo['nome'];
                if ($maxArquivos > 1) {
                    $regras[$destino][] = 'array';
                    $regras[$destino][] = 'max:'.$maxArquivos;
                    $destino .= '.*';
                }
                $regras[$destino][] = 'file';
                if (! empty($campo['aceitos'])) $regras[$destino][] = 'mimes:'.implode(',', $campo['aceitos']);
            }

            if (($campo['validacao'] ?? '') === 'email') $regras[$campo['nome']][] = 'email';
            if (($campo['validacao'] ?? '') === 'cpf') $regras[$campo['nome']][] = 'regex:/^\d{11}$/';
            if (($campo['validacao'] ?? '') === 'telefone') $regras[$campo['nome']][] = 'regex:/^[0-9()+\s-]{8,20}$/';
        }

        return $regras;
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
            'max.file' => 'O arquivo em :attribute não pode ser maior que :max kilobytes.',
            'regex' => 'O campo :attribute está em um formato inválido.',
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

        return $atributos;
    }

    /**
     * Registra a inscricao. Lanca ValidationException quando os dados enviados nao passam nas regras.
     *
     * @return array{sucesso: bool, motivo: ?string, mensagem: string, inscricao_id: ?int}
     */
    public function inscrever(Request $request, Atividade $atividade): array
    {
        return DB::transaction(function () use ($request, $atividade) {
            // Serializa os envios da mesma atividade antes da conferencia final de vagas.
            $atividade = Atividade::whereKey($atividade->id)->lockForUpdate()->firstOrFail();

            $estado = $this->estado($atividade);
            if (! $estado['aberto']) {
                return ['sucesso' => false, 'motivo' => $estado['motivo'], 'mensagem' => (string) $estado['mensagem'], 'inscricao_id' => null];
            }

            $resposta = $request->validate($this->regras($atividade), $this->mensagens(), $this->atributos($atividade));

            // Guarda apenas os arquivos de campos declarados no formulario; qualquer outro upload e descartado.
            foreach ($this->camposDeArquivo($atividade) as $nome) {
                $arquivos = $request->file($nome);
                if ($arquivos === null) continue;
                $lista = is_array($arquivos) ? $arquivos : [$arquivos];
                $resposta[$nome] = array_map(fn ($arquivo) => $arquivo->store('inscricoes', 'public'), $lista);
            }

            $inscricao = InscricaoAtividade::create(['atividade_id' => $atividade->id, 'resposta' => $resposta]);

            return [
                'sucesso' => true,
                'motivo' => null,
                'mensagem' => $atividade->formulario['mensagem_sucesso'] ?? 'Inscrição realizada com sucesso.',
                'inscricao_id' => $inscricao->id,
            ];
        });
    }
}
