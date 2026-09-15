<?php

namespace App\Services;

use App\Models\Atividade;
use App\Models\PixCobranca;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\HeaderUtils;

class RecebimentosPixService
{
    public function consulta(Request $request, ?Atividade $atividade = null): Builder
    {
        $consulta = PixCobranca::query()
            ->whereNotNull('pago_em')
            ->with(['inscricao.atividade.evento', 'inscricao.participante']);

        if ($atividade) {
            $consulta->whereHas('inscricao', fn (Builder $q) => $q->where('atividade_id', $atividade->id));
        } elseif ($request->integer('atividade_id') > 0) {
            $consulta->whereHas('inscricao', fn (Builder $q) => $q->where('atividade_id', $request->integer('atividade_id')));
        }
        if (! $atividade && $request->integer('evento_id') > 0) {
            $consulta->whereHas('inscricao.atividade', fn (Builder $q) => $q->where('evento_id', $request->integer('evento_id')));
        }

        $dataInicial = $this->data($request->input('data_inicial'));
        $dataFinal = $this->data($request->input('data_final'));
        if ($dataInicial && $dataFinal) {
            $inicio = ($dataInicial->lte($dataFinal) ? $dataInicial : $dataFinal)->copy()->startOfDay();
            $fim = ($dataInicial->lte($dataFinal) ? $dataFinal : $dataInicial)->copy()->endOfDay();
            $consulta->whereBetween('pago_em', [$inicio, $fim]);
        } elseif ($dataInicial) {
            $consulta->where('pago_em', '>=', $dataInicial->startOfDay());
        } elseif ($dataFinal) {
            $consulta->where('pago_em', '<=', $dataFinal->endOfDay());
        }

        $valor = $this->numero($request->input('valor'));
        $valorAte = $this->numero($request->input('valor_ate'));
        if ($valor !== null) {
            match ((string) $request->input('valor_operador', 'igual')) {
                'maior' => $consulta->where('valor', '>', $valor),
                'menor' => $consulta->where('valor', '<', $valor),
                'entre' => $consulta->whereBetween('valor', [min($valor, $valorAte ?? $valor), max($valor, $valorAte ?? $valor)]),
                default => $consulta->where('valor', $valor),
            };
        }

        $pagador = trim((string) $request->input('pagador', ''));
        if ($pagador !== '') {
            $documento = preg_replace('/\D+/', '', $pagador);
            $consulta->where(function (Builder $q) use ($pagador, $documento): void {
                $q->where('pagador_nome', 'like', "%{$pagador}%");
                if ($documento !== '') $q->orWhere('pagador_documento', 'like', "%{$documento}%");
            });
        }

        return $consulta;
    }

    public function aplicarPesquisa(Builder $consulta, string $pesquisa): void
    {
        $pesquisa = trim($pesquisa);
        if ($pesquisa === '') return;
        $documento = preg_replace('/\D+/', '', $pesquisa);
        $consulta->where(function (Builder $q) use ($pesquisa, $documento): void {
            $q->where('pagador_nome', 'like', "%{$pesquisa}%")
                ->orWhere('txid', 'like', "%{$pesquisa}%")
                ->orWhere('end_to_end_id', 'like', "%{$pesquisa}%")
                ->orWhereHas('inscricao', fn (Builder $i) => $i->where('participante_email', 'like', "%{$pesquisa}%"))
                ->orWhereHas('inscricao.atividade', fn (Builder $a) => $a->where('nome', 'like', "%{$pesquisa}%")
                    ->orWhereHas('evento', fn (Builder $e) => $e->where('nome', 'like', "%{$pesquisa}%")));
            if ($documento !== '') $q->orWhere('pagador_documento', 'like', "%{$documento}%");
        });
    }

    public function exportar(Request $request, ?Atividade $atividade = null)
    {
        $recebimentos = $this->consulta($request, $atividade)->orderByDesc('pago_em')->orderByDesc('id')->get();
        $linhas = [['ID', 'Data e hora', 'Evento', 'Atividade', 'Pagador', 'CPF/CNPJ', 'E-mail da inscrição', 'Valor', 'TXID', 'EndToEndId', 'Status']];
        foreach ($recebimentos as $cobranca) {
            $inscricao = $cobranca->inscricao;
            $linhas[] = [
                $cobranca->id,
                $cobranca->pago_em?->format('d/m/Y H:i:s') ?? '',
                $inscricao?->atividade?->evento?->nome ?? '',
                $inscricao?->atividade?->nome ?? '',
                $cobranca->pagador_nome ?: $inscricao?->participante?->nome ?: '',
                self::formatarDocumento($cobranca->pagador_documento ?: $inscricao?->participante?->cpf),
                $inscricao?->participante_email ?? '',
                number_format((float) $cobranca->valor, 2, ',', '.'),
                $cobranca->txid,
                $cobranca->end_to_end_id ?? '',
                $cobranca->status,
            ];
        }

        $planilha = new Spreadsheet;
        $aba = $planilha->getActiveSheet();
        $aba->setTitle('Recebimentos PIX');
        foreach ($linhas as $linha => $valores) {
            foreach ($valores as $coluna => $valor) {
                $aba->setCellValueExplicit([$coluna + 1, $linha + 1], (string) $valor, DataType::TYPE_STRING);
            }
        }
        $aba->getStyle('A1:K1')->getFont()->setBold(true);
        $aba->freezePane('A2');
        foreach (range('A', 'K') as $coluna) $aba->getColumnDimension($coluna)->setAutoSize(true);
        $arquivo = fopen('php://temp', 'w+b');
        (new Xlsx($planilha))->save($arquivo);
        rewind($arquivo);
        $conteudo = stream_get_contents($arquivo);
        fclose($arquivo);
        $planilha->disconnectWorksheets();
        $nome = 'recebimentos-pix-'.($atividade ? (\Illuminate\Support\Str::slug($atividade->nome) ?: $atividade->id).'-' : '').now()->format('Y-m-d-H-i-s').'.xlsx';

        return response($conteudo, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $nome),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public static function formatarDocumento(?string $documento): string
    {
        $numero = preg_replace('/\D+/', '', (string) $documento);
        if (strlen($numero) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $numero);
        if (strlen($numero) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $numero);
        return $numero;
    }

    private function numero(mixed $valor): ?float
    {
        $texto = trim((string) $valor);
        if ($texto === '') return null;
        if (str_contains($texto, ',') && str_contains($texto, '.')) $texto = str_replace('.', '', $texto);
        $texto = str_replace(',', '.', $texto);
        return is_numeric($texto) ? round((float) $texto, 2) : null;
    }

    private function data(mixed $valor): ?Carbon
    {
        $texto = trim((string) $valor);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) return null;
        try {
            $data = Carbon::createFromFormat('Y-m-d', $texto)->startOfDay();
            return $data->format('Y-m-d') === $texto ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
