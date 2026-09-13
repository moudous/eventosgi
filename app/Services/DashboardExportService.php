<?php

namespace App\Services;

class DashboardExportService
{
    /** @return array{titulo:string,filtros:list<string>,secoes:list<array{titulo:string,colunas:list<string>,linhas:list<list<string>>,grafico:list<array{rotulo:string,valor:int}>}>} */
    public function relatorio(string $card, array $dados): array
    {
        return match ($card) {
            'inscricoes-categoria' => $this->categorias($dados),
            'inscritos-opcao' => $this->opcoes($dados),
            'evolucao-inscricoes' => $this->evolucao($dados),
            'inscricoes-dispositivo' => $this->dispositivos($dados),
            default => abort(404),
        };
    }

    public function png(array $relatorio): string
    {
        abort_unless(function_exists('imagecreatetruecolor'), 501, 'A geração de imagens não está disponível.');

        $largura = 1400;
        $totalLinhas = array_sum(array_map(fn (array $secao) => min(250, count($secao['linhas'])), $relatorio['secoes']));
        $altura = min(20000, 210 + count($relatorio['filtros']) * 32 + count($relatorio['secoes']) * 95 + $totalLinhas * 34);
        $imagem = imagecreatetruecolor($largura, max(600, $altura));
        $branco = imagecolorallocate($imagem, 255, 255, 255);
        $texto = imagecolorallocate($imagem, 31, 45, 61);
        $muted = imagecolorallocate($imagem, 88, 97, 116);
        $azul = imagecolorallocate($imagem, 37, 99, 235);
        $azulClaro = imagecolorallocate($imagem, 219, 234, 254);
        $borda = imagecolorallocate($imagem, 222, 226, 230);
        imagefill($imagem, 0, 0, $branco);

        $fonte = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        $fonteNegrito = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
        $y = 54;
        $this->texto($imagem, $relatorio['titulo'], 24, 44, $y, $texto, $fonteNegrito);
        $y += 40;
        $this->texto($imagem, 'Eventos GI · gerado em '.now()->format('d/m/Y H:i'), 12, 44, $y, $muted, $fonte);
        $y += 38;
        foreach ($relatorio['filtros'] as $filtro) {
            $this->texto($imagem, $filtro, 12, 44, $y, $muted, $fonte);
            $y += 30;
        }

        foreach ($relatorio['secoes'] as $secao) {
            $y += 24;
            $this->texto($imagem, $secao['titulo'], 17, 44, $y, $texto, $fonteNegrito);
            $y += 25;
            $maximo = max(1, ...array_map(fn (array $item) => $item['valor'], $secao['grafico']));
            foreach (array_slice($secao['linhas'], 0, 250) as $indice => $linha) {
                if ($y > imagesy($imagem) - 40) break 2;
                if ($indice % 2 === 0) imagefilledrectangle($imagem, 36, $y - 20, $largura - 36, $y + 12, imagecolorallocate($imagem, 248, 250, 252));
                $rotulo = mb_strimwidth((string) ($linha[0] ?? ''), 0, 64, '…');
                $this->texto($imagem, $rotulo, 11, 48, $y, $texto, $fonte);
                $valor = (int) ($secao['grafico'][$indice]['valor'] ?? 0);
                $inicioBarra = 720;
                imagefilledrectangle($imagem, $inicioBarra, $y - 14, $largura - 115, $y + 4, $azulClaro);
                $fim = $inicioBarra + (int) (($largura - 835) * $valor / $maximo);
                imagefilledrectangle($imagem, $inicioBarra, $y - 14, max($inicioBarra, $fim), $y + 4, $azul);
                $this->texto($imagem, implode(' · ', array_slice($linha, 1)), 10, $largura - 105, $y, $muted, $fonte, alinhamentoDireita: true);
                imageline($imagem, 36, $y + 13, $largura - 36, $y + 13, $borda);
                $y += 34;
            }
        }

        ob_start();
        imagepng($imagem, null, 8);
        $conteudo = (string) ob_get_clean();
        imagedestroy($imagem);

        return $conteudo;
    }

    private function categorias(array $dados): array
    {
        $linhas = collect($dados['contagensCategorias'])->map(fn (array $item) => [
            (string) $item['nome'], (string) $item['inscricoes'], (string) $item['atividades'],
        ])->values()->all();

        return $this->montar('Inscrições por categoria', [], 'Categorias', ['Categoria', 'Inscrições', 'Atividades'], $linhas, 1);
    }

    private function opcoes(array $dados): array
    {
        $linhas = collect($dados['grafico']['itens'] ?? [])->map(fn (array $item) => [
            (string) $item['rotulo'], (string) $item['quantidade'], number_format((float) $item['percentual'], 1, ',', '.').'%',
        ])->all();
        $filtros = ['Evento: '.$this->evento($dados), 'Atividade: '.$this->atividade($dados)];
        $campo = collect($dados['campos'] ?? [])->firstWhere('nome', $dados['campoNome'] ?? '');
        $filtros[] = 'Campo: '.($campo['label'] ?? 'Não selecionado');

        return $this->montar('Inscritos por opção', $filtros, 'Opções', ['Opção', 'Inscrições', 'Percentual'], $linhas, 1);
    }

    private function evolucao(array $dados): array
    {
        $evolucao = $dados['evolucao'] ?? null;
        $linhas = collect($evolucao['itens'] ?? [])->map(fn (array $item) => [
            trim($item['rotulo'].' · '.$item['dia_semana']),
            $item['quantidade'] === null ? 'Futuro' : (string) $item['quantidade'],
            ! empty($item['parcial']) ? 'Intervalo parcial' : '',
        ])->all();
        $filtros = ['Evento: '.$this->evento($dados), 'Atividade: '.$this->atividade($dados)];
        if ($evolucao && empty($evolucao['erro'])) $filtros[] = 'Período: '.$evolucao['inicio'].' até '.$evolucao['fim'].' · intervalos de '.$evolucao['intervalo'];

        return $this->montar('Evolução do Nº de inscrições', $filtros, 'Evolução', ['Período', 'Inscrições', 'Observação'], $linhas, 1);
    }

    private function dispositivos(array $dados): array
    {
        $filtros = [
            'Evento: '.($dados['dispositivoEventoId'] ? $this->nomePorId($dados['eventos'], $dados['dispositivoEventoId']) : 'Todos os eventos'),
            'Atividade: '.($dados['dispositivoAtividadeId'] ? $this->nomePorId($dados['dispositivoAtividades'], $dados['dispositivoAtividadeId']) : 'Todas as atividades'),
        ];
        $secoes = [];
        foreach ($dados['dimensoesDispositivo'] as $dimensao => $titulo) {
            $linhas = collect($dados['graficosDispositivo'][$dimensao]['itens'] ?? [])->map(fn (array $item) => [
                (string) $item['rotulo'], (string) $item['quantidade'], number_format((float) $item['percentual'], 1, ',', '.').'%',
            ])->all();
            $secoes[] = $this->secao($titulo, [$titulo, 'Inscrições', 'Percentual'], $linhas, 1);
        }

        return ['titulo' => 'Inscrições por dispositivo', 'filtros' => $filtros, 'secoes' => $secoes];
    }

    private function montar(string $titulo, array $filtros, string $secao, array $colunas, array $linhas, int $indiceValor): array
    {
        return ['titulo' => $titulo, 'filtros' => $filtros, 'secoes' => [$this->secao($secao, $colunas, $linhas, $indiceValor)]];
    }

    private function secao(string $titulo, array $colunas, array $linhas, int $indiceValor): array
    {
        return [
            'titulo' => $titulo,
            'colunas' => $colunas,
            'linhas' => $linhas,
            'grafico' => array_map(fn (array $linha) => ['rotulo' => (string) ($linha[0] ?? ''), 'valor' => is_numeric($linha[$indiceValor] ?? null) ? (int) $linha[$indiceValor] : 0], $linhas),
        ];
    }

    private function evento(array $dados): string
    {
        return $this->nomePorId($dados['eventos'] ?? [], (int) ($dados['eventoId'] ?? 0));
    }

    private function atividade(array $dados): string
    {
        return $this->nomePorId($dados['atividades'] ?? [], (int) ($dados['atividadeId'] ?? 0));
    }

    private function nomePorId(iterable $itens, int $id): string
    {
        $item = collect($itens)->firstWhere('id', $id);
        return (string) ($item?->nome ?? 'Não selecionado');
    }

    private function texto($imagem, string $texto, int $tamanho, int $x, int $y, int $cor, string $fonte, bool $alinhamentoDireita = false): void
    {
        if ($alinhamentoDireita) {
            $caixa = imagettfbbox($tamanho, 0, $fonte, $texto);
            $x -= (int) (($caixa[2] ?? 0) - ($caixa[0] ?? 0));
        }
        imagettftext($imagem, $tamanho, 0, $x, $y, $cor, $fonte, $texto);
    }
}
