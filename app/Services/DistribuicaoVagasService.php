<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\InscricaoAtividade;
use Illuminate\Validation\ValidationException;

class DistribuicaoVagasService
{
    private const TIPOS_CRITERIO = ['select', 'radio'];

    public function prepararConfiguracao(array $config): array
    {
        $campos = collect($config['campos'] ?? [])->map(function (array $campo): array {
            $campo['criterio_vagas'] = in_array($campo['tipo'] ?? '', self::TIPOS_CRITERIO, true)
                && ! empty($campo['criterio_vagas']);
            $campo['opcoes'] = array_values(array_map(function ($opcao): array {
                $opcao = is_array($opcao) ? $opcao : ['valor' => (string) $opcao, 'texto' => (string) $opcao];
                $opcao['percentual_vagas'] = isset($opcao['percentual_vagas'])
                    ? round((float) $opcao['percentual_vagas'], 4)
                    : null;
                return $opcao;
            }, $campo['opcoes'] ?? []));
            return $campo;
        })->all();
        $habilitados = collect($campos)->filter(fn ($campo) => ! empty($campo['criterio_vagas']))
            ->pluck('nome')->filter()->values()->all();
        $ordem = array_values(array_unique(array_filter($config['criterios_vagas'] ?? [], fn ($nome) => in_array($nome, $habilitados, true))));
        $config['criterios_vagas'] = [...$ordem, ...array_values(array_diff($habilitados, $ordem))];
        $config['campos'] = $campos;

        return $config;
    }

    public function validarConfiguracao(array $config): void
    {
        if (empty($config['limitar_inscricoes'])) return;
        $erros = [];
        foreach ($config['campos'] ?? [] as $campo) {
            if (empty($campo['criterio_vagas'])) continue;
            $soma = 0;
            foreach ($campo['opcoes'] ?? [] as $indice => $opcao) {
                if (! isset($opcao['percentual_vagas']) || $opcao['percentual_vagas'] === '') {
                    $erros[] = 'Informe o percentual de todas as opções do critério “'.($campo['label'] ?? $campo['nome']).'”.';
                    continue;
                }
                if ((float) $opcao['percentual_vagas'] < 0 || (float) $opcao['percentual_vagas'] > 100) {
                    $erros[] = 'Cada percentual do critério “'.($campo['label'] ?? $campo['nome']).'” deve estar entre 0% e 100%.';
                }
                $soma += (float) $opcao['percentual_vagas'];
            }
            if ($soma > 100.001) $erros[] = 'A soma das opções do critério “'.($campo['label'] ?? $campo['nome']).'” não pode ultrapassar 100%.';
        }
        if ($erros !== []) throw ValidationException::withMessages(['formulario' => array_values(array_unique($erros))]);
    }

    public function recalcular(Atividade $atividade, ?array $config = null, bool $salvar = true): array
    {
        $config = $this->prepararConfiguracao($config ?? $atividade->formulario ?? []);
        $total = ! empty($config['limitar_inscricoes']) ? max(0, (int) ($config['limite_inscricoes'] ?? 0)) : 0;
        $inscricoes = InscricaoAtividade::query()->where('atividade_id', $atividade->id)->get(['resposta']);
        $distribuicao = [
            'total' => ['disponiveis' => $total, 'usadas' => $inscricoes->count(), 'restantes' => max(0, $total - $inscricoes->count())],
            'criterios' => array_values($config['criterios_vagas'] ?? []),
            'niveis' => [],
        ];
        if ($total > 0) {
            $campos = collect($config['campos'] ?? [])->keyBy('nome');
            $pais = [['valores' => [], 'disponiveis' => $total]];
            foreach ($distribuicao['criterios'] as $nivel => $nomeCampo) {
                $campo = $campos->get($nomeCampo);
                if (! $campo) continue;
                $contextos = [];
                $proximos = [];
                foreach ($pais as $pai) {
                    $chaveContexto = $this->chaveContexto($pai['valores']);
                    foreach ($campo['opcoes'] ?? [] as $opcao) {
                        $valor = (string) ($opcao['valor'] ?? '');
                        $percentual = (float) ($opcao['percentual_vagas'] ?? 0);
                        $disponiveis = (int) floor($pai['disponiveis'] * $percentual / 100 + 0.000001);
                        $valores = [...$pai['valores'], $valor];
                        $usadas = $inscricoes->filter(fn ($inscricao) => $this->corresponde($inscricao->resposta ?? [], $distribuicao['criterios'], $valores))->count();
                        $contextos[$chaveContexto]['opcoes'][$valor] = [
                            'disponiveis' => $disponiveis,
                            'usadas' => $usadas,
                            'restantes' => max(0, $disponiveis - $usadas),
                        ];
                        $proximos[] = ['valores' => $valores, 'disponiveis' => $disponiveis];
                    }
                }
                $distribuicao['niveis'][] = ['campo' => $nomeCampo, 'contextos' => $contextos];
                $pais = $proximos;
            }
        }
        $config['distribuicao_vagas'] = $distribuicao;
        if ($salvar && $atividade->exists && ($atividade->formulario ?? []) !== $config) {
            $atividade->update(['formulario' => $config]);
        }

        return $config;
    }

    public function conferirDisponibilidade(Atividade $atividade, array $respostas): void
    {
        $config = $this->recalcular($atividade, salvar: false);
        $distribuicao = $config['distribuicao_vagas'];
        $anteriores = [];
        foreach ($distribuicao['niveis'] as $nivel) {
            $campo = $nivel['campo'];
            $valor = $respostas[$campo] ?? null;
            if (! is_scalar($valor) || trim((string) $valor) === '') {
                throw ValidationException::withMessages([$campo => 'Selecione uma opção para este critério de vagas.']);
            }
            $cota = $nivel['contextos'][$this->chaveContexto($anteriores)]['opcoes'][(string) $valor] ?? null;
            if (! $cota || $cota['restantes'] < 1) {
                throw ValidationException::withMessages([$campo => 'Não há mais vagas disponíveis para esta opção.']);
            }
            $anteriores[] = (string) $valor;
        }
    }

    private function corresponde(array $resposta, array $criterios, array $valores): bool
    {
        foreach ($valores as $indice => $valor) {
            if ((string) ($resposta[$criterios[$indice]] ?? '') !== $valor) return false;
        }
        return true;
    }

    private function chaveContexto(array $valores): string
    {
        return json_encode(array_values($valores), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
