<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\InscricaoAtividade;
use App\Models\Participante;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\HeaderUtils;

class InscricoesExportService
{
    /** @return list<array{chave: string, rotulo: string, grupo: string, marcado: bool}> */
    public function camposDisponiveis(Atividade $atividade): array
    {
        $principais = [
            ['id', 'ID'], ['data_inscricao', 'Data da inscrição'], ['participante', 'Participante'],
            ['participante_id', 'ID do participante'], ['email', 'E-mail identificado'],
            ['presenca', 'Presença'], ['data_presenca', 'Data da presença'],
            ['presenca_validada_por', 'Presença validada pelo usuário GI'],
        ];
        $tecnicos = [
            ['codigo_qr', 'Código QR'], ['ip', 'IP'], ['navegador', 'Navegador'],
            ['sistema', 'Sistema operacional'], ['aparelho', 'Aparelho'], ['idioma', 'Idioma'],
            ['origem', 'Origem'], ['sessao', 'Sessão do navegador'], ['user_agent', 'User-Agent'],
        ];
        $respostas = $this->camposFormulario($atividade);

        return [
            ...array_map(fn ($campo) => ['chave' => $campo[0], 'rotulo' => $campo[1], 'grupo' => 'Campos principais', 'marcado' => true], $principais),
            ...array_map(fn ($rotulo, $chave) => ['chave' => 'resposta:'.$chave, 'rotulo' => $rotulo, 'grupo' => 'Respostas do formulário', 'marcado' => true], array_values($respostas), array_keys($respostas)),
            ...array_map(fn ($campo) => ['chave' => $campo[0], 'rotulo' => $campo[1], 'grupo' => 'Dados técnicos', 'marcado' => false], $tecnicos),
        ];
    }

    /** @param list<string>|null $selecionados */
    public function download(Atividade $atividade, string $formato, ?array $selecionados = null)
    {
        abort_unless(in_array($formato, ['csv', 'ods', 'xls', 'xlsx'], true), 404);
        $inscricoes = InscricaoAtividade::where('atividade_id', $atividade->id)->orderBy('id')->get();
        $campos = $this->camposFormulario($atividade, $inscricoes);
        $disponiveis = collect($this->camposDisponiveis($atividade))->keyBy('chave');
        $selecionados ??= $disponiveis->where('marcado', true)->keys()->all();
        $selecionados = array_values(array_filter($selecionados, fn ($chave) => $disponiveis->has($chave)));
        abort_if($selecionados === [], 422, 'Selecione pelo menos um campo para exportar.');
        $participantes = Participante::query()
            ->whereIn('id', $inscricoes->pluck('participante_id')->filter()->unique()->all())
            ->pluck('nome', 'id');
        $configuracoes = collect($atividade->formulario['campos'] ?? [])->keyBy('nome');

        $linhas = [array_map(fn ($chave) => $disponiveis[$chave]['rotulo'], $selecionados)];
        foreach ($inscricoes as $inscricao) {
            $dispositivo = $inscricao->dispositivo ?? [];
            $valores = [
                'id' => (string) $inscricao->id,
                'data_inscricao' => $inscricao->created_at?->format('d/m/Y H:i:s') ?? '',
                'participante' => (string) $participantes->get($inscricao->participante_id, ''),
                'participante_id' => $inscricao->participante_id ? (string) $inscricao->participante_id : '',
                'email' => (string) ($inscricao->participante_email ?? ''),
                'presenca' => $inscricao->presente ? 'Presente' : 'Não',
                'data_presenca' => $inscricao->data_presenca?->format('d/m/Y H:i:s') ?? '',
                'presenca_validada_por' => $inscricao->presenca_validada_por ? (string) $inscricao->presenca_validada_por : '',
                'codigo_qr' => (string) ($inscricao->codigo_qr ?? ''),
                'ip' => (string) ($inscricao->ip ?? ''),
                'navegador' => trim(($dispositivo['navegador'] ?? '').' '.($dispositivo['navegador_versao'] ?? '')),
                'sistema' => trim(($dispositivo['sistema'] ?? '').' '.($dispositivo['sistema_versao'] ?? '')),
                'aparelho' => (string) ($dispositivo['plataforma'] ?? ''),
                'idioma' => (string) ($dispositivo['idioma'] ?? ''),
                'origem' => (string) ($dispositivo['origem'] ?? ''),
                'sessao' => (string) ($dispositivo['sessao'] ?? ''),
                'user_agent' => (string) ($inscricao->user_agent ?? ''),
            ];
            foreach ($campos as $chave => $label) {
                $configuracao = $configuracoes->get($chave, []);
                $valores['resposta:'.$chave] = $this->textoCampo($inscricao->resposta[$chave] ?? null, $configuracao);
            }
            $linhas[] = array_map(fn ($chave) => $valores[$chave] ?? '', $selecionados);
        }
        $nome = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]/u', '-', $atividade->nome);
        $nome = mb_substr(trim($nome, ' .'), 0, 120) ?: 'Atividade';
        $arquivo = $nome . ' - respostas - ' . now()->format('Y-m-d H-i-s') . '.' . $formato;
        $mime = match ($formato) {
            'csv' => 'text/csv; charset=UTF-8',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };
        $conteudo = $this->planilha($linhas, $formato);

        return response($conteudo, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $arquivo, \Illuminate\Support\Str::ascii($arquivo)),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function texto(mixed $valor): string
    {
        if (is_array($valor)) return implode('; ', array_map(fn ($item) => $this->texto($item), $valor));
        if (is_bool($valor)) return $valor ? 'Sim' : 'Não';
        return (string) $valor;
    }

    /** @param array<string, mixed> $campo */
    private function textoCampo(mixed $valor, array $campo): string
    {
        if (($campo['tipo'] ?? '') === 'checkbox' && empty($campo['opcoes'])) {
            $valores = is_array($valor) ? $valor : [$valor];
            $marcado = collect($valores)->contains(fn ($item) => $item === true || (string) $item === '1');

            return $marcado ? 'Sim' : 'Não';
        }

        return $this->texto($valor ?? '');
    }

    /** @param iterable<InscricaoAtividade>|null $inscricoes @return array<string, string> */
    private function camposFormulario(Atividade $atividade, ?iterable $inscricoes = null): array
    {
        $campos = [];
        foreach ($atividade->formulario['campos'] ?? [] as $campo) {
            if (! empty($campo['nome'])) $campos[$campo['nome']] = ($campo['label'] ?? '') ?: $campo['nome'];
        }
        $inscricoes ??= InscricaoAtividade::where('atividade_id', $atividade->id)->get(['resposta']);
        foreach ($inscricoes as $inscricao) {
            foreach ($inscricao->resposta ?? [] as $chave => $valor) $campos[$chave] ??= $chave;
        }

        return $campos;
    }

    private function planilha(array $linhas, string $formato): string
    {
        $planilha = new Spreadsheet();
        $stream = fopen('php://temp', 'w+');
        try {
            $aba = $planilha->getActiveSheet();
            $aba->setTitle('Respostas');
            foreach ($linhas as $r => $linha) {
                foreach ($linha as $c => $valor) {
                    // Preserve leading zeros and prevent answers from becoming formulas.
                    if ($formato === 'csv' && preg_match('/^[\s\x{FEFF}]*[=+@-]/u', $valor)) $valor = "'" . $valor;
                    $aba->setCellValueExplicit([$c + 1, $r + 1], $valor, DataType::TYPE_STRING);
                }
            }
            $aba->freezePane('A2');
            $aba->getStyle('A1:' . $aba->getHighestColumn() . '1')->getFont()->setBold(true);
            $writer = match ($formato) {
                'csv' => (new Csv($planilha))->setExcelCompatibility(true),
                'ods' => new Ods($planilha),
                'xls' => new Xls($planilha),
                'xlsx' => new Xlsx($planilha),
            };
            $writer->setPreCalculateFormulas(false);
            $writer->save($stream);
            rewind($stream);
            return stream_get_contents($stream);
        } finally {
            fclose($stream);
            $planilha->disconnectWorksheets();
        }
    }
}
