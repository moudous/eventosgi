<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\InscricaoAtividade;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\HeaderUtils;

class InscricoesExportService
{
    public function download(Atividade $atividade, string $formato)
    {
        abort_unless(in_array($formato, ['csv', 'ods', 'xls', 'xlsx'], true), 404);
        $inscricoes = InscricaoAtividade::where('atividade_id', $atividade->id)->orderBy('id')->get();
        $campos = [];
        foreach ($atividade->formulario['campos'] ?? [] as $campo) {
            if (!empty($campo['nome'])) $campos[$campo['nome']] = ($campo['label'] ?? '') ?: $campo['nome'];
        }
        // Include answers to fields that have since been removed from the form.
        foreach ($inscricoes as $inscricao) {
            foreach ($inscricao->resposta ?? [] as $chave => $valor) {
                $campos[$chave] ??= $chave;
            }
        }
        $linhas = [['ID', 'Data da inscrição', ...array_values($campos)]];
        foreach ($inscricoes as $inscricao) {
            $linha = [(string) $inscricao->id, $inscricao->created_at?->format('d/m/Y H:i:s') ?? ''];
            foreach ($campos as $chave => $label) $linha[] = $this->texto($inscricao->resposta[$chave] ?? '');
            $linhas[] = $linha;
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
