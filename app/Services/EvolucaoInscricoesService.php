<?php

namespace App\Services;

use App\Models\Atividade;
use Carbon\CarbonImmutable;

class EvolucaoInscricoesService
{
    public function paraAtividade(Atividade $atividade): array
    {
        return $this->agrupar($atividade->formulario ?? [], $atividade->inscricoes()->orderBy('created_at')->pluck('created_at')->all());
    }

    public function agrupar(array $config, array $datas, ?CarbonImmutable $agora = null): array
    {
        $agora ??= CarbonImmutable::now();
        $datas = array_map(fn ($data) => CarbonImmutable::parse($data), $datas);
        $inicio = ! empty($config['abertura']) ? CarbonImmutable::parse($config['abertura']) : ($datas ? min($datas) : $agora);
        $fim = ! empty($config['fechamento']) ? CarbonImmutable::parse($config['fechamento']) : max($inicio, $agora);
        if ($fim < $inicio) return ['erro' => 'O fechamento das inscrições é anterior à abertura.'];
        $dentro = array_values(array_filter($datas, fn ($data) => $data >= $inicio && $data <= $fim && $data <= $agora));
        usort($dentro, fn ($a, $b) => $a <=> $b);
        $duracao = max(1, $fim->getTimestamp() - $inicio->getTimestamp());
        $alvo = max(12, min(60, (int) ceil(sqrt(count($dentro)) * 4)));
        $opcoes = [[60, '1 minuto'], [300, '5 minutos'], [900, '15 minutos'], [1800, '30 minutos'], [3600, '1 hora'], [10800, '3 horas'], [21600, '6 horas'], [43200, '12 horas'], [86400, '1 dia'], [604800, '7 dias'], [2592000, '1 mês'], [7776000, '3 meses'], [31536000, '1 ano']];
        [$segundos, $intervalo] = end($opcoes);
        foreach ($opcoes as $opcao) {
            if ($duracao / $opcao[0] <= $alvo) { [$segundos, $intervalo] = $opcao; break; }
        }
        $itens = [];
        $cursor = $inicio;
        $indice = 0;
        do {
            $proximo = match ($intervalo) {
                '1 mês' => $cursor->addMonthNoOverflow(),
                '3 meses' => $cursor->addMonthsNoOverflow(3),
                '1 ano' => $cursor->addYearNoOverflow(),
                default => $cursor->addSeconds($segundos),
            };
            $limite = min($proximo, $fim);
            $quantidade = 0;
            while (isset($dentro[$indice]) && ($dentro[$indice] < $limite || ($limite == $fim && $dentro[$indice] == $fim))) {
                $quantidade++;
                $indice++;
            }
            $itens[] = [
                'rotulo' => $cursor->format($segundos < 86400 ? 'd/m H:i' : 'd/m/Y'),
                'dia_semana' => $cursor->locale('pt_BR')->isoFormat('dddd'),
                'inicio' => $cursor->format('d/m/Y H:i'), 'fim' => $limite->format('d/m/Y H:i'),
                'quantidade' => $cursor > $agora ? null : $quantidade,
                'parcial' => $cursor <= $agora && ($limite > $agora || $limite < $proximo),
            ];
            $cursor = $proximo;
        } while ($cursor < $fim);
        $pico = max(array_column($itens, 'quantidade'));
        return ['erro' => null, 'intervalo' => $intervalo, 'inicio' => $inicio->format('d/m/Y H:i'),
            'fim' => $fim->format('d/m/Y H:i'), 'total' => count($dentro), 'fora' => count($datas) - count($dentro),
            'itens' => $itens, 'pico' => $pico ?? 0];
    }
}
