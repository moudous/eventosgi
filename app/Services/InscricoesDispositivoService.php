<?php

namespace App\Services;

class InscricoesDispositivoService
{
    public const DIMENSOES = [
        'plataforma' => 'Tipo de dispositivo',
        'sistema' => 'Sistema operacional',
        'navegador' => 'Navegador',
        'idioma' => 'Idioma',
        'origem' => 'Origem do acesso',
        'sistema_versao' => 'Sistema operacional e versão',
        'navegador_versao' => 'Navegador e versão',
    ];

    public function agrupar(iterable $inscricoes, string $dimensao): array
    {
        $contagens = [];
        $total = 0;
        foreach ($inscricoes as $inscricao) {
            $dados = $inscricao->dispositivo ?? [];
            $valor = $dados[$dimensao] ?? null;
            $rotulo = is_scalar($valor) ? trim((string) $valor) : '';
            if (in_array($dimensao, ['sistema_versao', 'navegador_versao'], true)) {
                $base = $dados[str_replace('_versao', '', $dimensao)] ?? '';
                $rotulo = trim((is_scalar($base) ? $base : '').' '.$rotulo);
            }
            $rotulo = $rotulo === '' ? 'Não informado' : $rotulo;
            $contagens[$rotulo] = ($contagens[$rotulo] ?? 0) + 1;
            $total++;
        }
        arsort($contagens);
        $itens = [];
        foreach ($contagens as $rotulo => $quantidade) {
            $itens[] = ['rotulo' => (string) $rotulo, 'quantidade' => $quantidade, 'percentual' => round($quantidade * 100 / $total, 1)];
        }
        return ['total' => $total, 'itens' => $itens];
    }
}
